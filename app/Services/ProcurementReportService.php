<?php

namespace App\Services;

use App\Models\Master\Ticket;
use App\Support\ProcurementAccess;
use App\Support\TicketWorkflowPresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ProcurementReportService
{
    public const STATUSES = [
        'WAITING_MANAGER_APPROVAL' => 'Menunggu kadep/manager',
        'WAITING_PROCUREMENT_REVIEW' => 'Review Procurement',
        'WAITING_PROCUREMENT' => 'Menunggu ketersediaan',
        'PARTIALLY_FULFILLED' => 'Terpenuhi sebagian',
        'CLOSED' => 'Selesai',
        'REJECTED_BY_MANAGER' => 'Ditolak atasan',
    ];

    public const RANKINGS = [
        'budget' => 'Anggaran diajukan',
        'funds' => 'Dana diberikan',
        'count' => 'Jumlah pengajuan',
    ];

    public function build(array $filters): array
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $rows = Ticket::query()->where('payload->request_type', ProcurementAccess::TYPE)
            ->whereBetween('created_at', [$from, $to])
            ->with(['requester:id,name', 'approvals' => fn ($query) => $query->where('level', 1)])
            ->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (Ticket $ticket) => $this->row($ticket));

        // Options come from the period before applying the department/status filters.
        $departments = $rows->pluck('department')->unique(fn ($name) => Str::lower($name))->sort()->values()->all();
        $rows = $rows->filter(fn ($row) => (empty($filters['department']) || Str::lower($row['department']) === Str::lower(Str::squish($filters['department'])))
            && (empty($filters['status']) || $row['status'] === $filters['status'])
        )->values();
        $ranking = $filters['ranking'] ?? 'budget';
        $departmentRows = $this->group($rows, fn ($row) => Str::lower($row['department']), 'department', $ranking);
        $requesterRows = $this->group($rows, fn ($row) => $row['requester_id'].'|'.Str::lower($row['department']), 'requester_name', $ranking);
        $summary = $this->summarize($rows);
        $months = [];
        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
            $period = $rows->filter(fn ($row) => $row['month'] === $month->format('Y-m'));
            $months[] = ['label' => $month->format('M Y')] + $this->summarize($period);
        }
        $distribution = collect(self::STATUSES)->map(fn ($label, $status) => [
            'label' => $label, 'count' => $rows->where('status', $status)->count(), 'status' => $status,
        ])->values()->all();
        $backlog = $rows->where('active', true)->sortByDesc('age_days')->values();
        $completedDays = $rows->where('status', 'CLOSED')->pluck('completion_days')->filter(fn ($days) => $days !== null);

        return compact('rows', 'departments', 'departmentRows', 'requesterRows', 'months', 'distribution', 'backlog') + [
            'summary' => $summary + [
                'completion_rate' => $rows->isEmpty() ? 0 : round($summary['completed'] / $rows->count() * 100, 1),
                'average_completion_days' => $completedDays->isEmpty() ? null : round($completedDays->avg(), 1),
                'aging_count' => $backlog->where('age_days', '>=', 7)->count(),
            ],
            'generated_at' => now(),
        ];
    }

    private function row(Ticket $ticket): array
    {
        $payload = $ticket->payload ?? [];
        $items = collect($payload['items'] ?? []);
        $records = collect($payload['fulfillments'] ?? []);
        $status = (string) ($payload['workflow_status'] ?? 'WAITING_MANAGER_APPROVAL');
        $rejected = in_array($status, ['REJECTED_BY_MANAGER', 'REJECTED_BY_BUM', 'REJECTED', 'CANCELLED'], true);
        $active = ! $rejected && $status !== 'CLOSED';
        $budget = $items->isNotEmpty() ? $items->sum(fn ($item) => $this->cents($item['estimated_budget'] ?? 0)) : $this->cents($payload['total_estimated_amount'] ?? 0);
        $approved = ! $rejected && $ticket->approvals->contains(fn ($approval) => strtolower($approval->status) === 'approved');
        $unfulfilled = $items->sum(fn ($item) => max(0, (int) ($item['quantity'] ?? 0) - (int) ($item['fulfilled_quantity'] ?? 0)));
        $outstanding = $active ? $items->sum(fn ($item) => (int) round($this->cents($item['estimated_budget'] ?? 0)
            * max(0, (int) ($item['quantity'] ?? 0) - (int) ($item['fulfilled_quantity'] ?? 0)) / max(1, (int) ($item['quantity'] ?? 0)))) : 0;

        return [
            'ticket' => $ticket, 'ticket_no' => $ticket->ticket_no, 'title' => $ticket->title,
            'submitted_at' => $ticket->created_at, 'month' => $ticket->created_at->format('Y-m'),
            'requester_id' => $ticket->requester_id,
            'requester_name' => trim((string) ($payload['requester_name'] ?? '')) ?: ($ticket->requester?->name ?: 'Pemohon tidak tersedia'),
            'department' => Str::squish((string) ($payload['requester_department'] ?? '')) ?: 'Departemen belum tercatat',
            'status' => $status, 'status_label' => TicketWorkflowPresenter::make($payload)['label'],
            'budget' => $budget, 'approved_budget' => $approved ? $budget : 0,
            'pending_budget' => $status === 'WAITING_MANAGER_APPROVAL' ? $budget : 0,
            'funds' => $records->where('method', 'funds')->sum(fn ($record) => $this->cents($record['amount'] ?? 0)),
            'stock_quantity' => $records->where('method', 'stock')->sum(fn ($record) => (int) ($record['quantity'] ?? 0)),
            'funded_quantity' => $records->where('method', 'funds')->sum(fn ($record) => (int) ($record['quantity'] ?? 0)),
            'remaining_quantity' => $unfulfilled, 'outstanding_budget' => $outstanding,
            'active' => $active, 'rejected' => $rejected,
            'age_days' => max(0, (int) $ticket->created_at->diffInDays(now())),
            'completion_days' => $status === 'CLOSED' && $ticket->closed_at ? max(0, $ticket->created_at->diffInHours(Carbon::parse($ticket->closed_at)) / 24) : null,
        ];
    }

    private function group(Collection $rows, callable $key, string $labelKey, string $ranking): array
    {
        return $rows->groupBy($key)->map(fn ($group) => [
            'label' => $group->first()[$labelKey], 'department' => $group->first()['department'],
        ] + $this->summarize($group))->sort(function ($a, $b) use ($ranking) {
            return ($b[$ranking] <=> $a[$ranking]) ?: strcmp($a['label'], $b['label']);
        })->values()->all();
    }

    private function summarize(Collection $rows): array
    {
        return [
            'count' => $rows->count(), 'budget' => (int) $rows->sum('budget'),
            'approved_budget' => (int) $rows->sum('approved_budget'), 'pending_budget' => (int) $rows->sum('pending_budget'),
            'funds' => (int) $rows->sum('funds'), 'outstanding_budget' => (int) $rows->sum('outstanding_budget'),
            'stock_quantity' => (int) $rows->sum('stock_quantity'), 'funded_quantity' => (int) $rows->sum('funded_quantity'),
            'remaining_quantity' => (int) $rows->where('active', true)->sum('remaining_quantity'),
            'completed' => $rows->where('status', 'CLOSED')->count(), 'active' => $rows->where('active', true)->count(),
            'rejected' => $rows->where('rejected', true)->count(),
            'pending' => $rows->where('status', 'WAITING_MANAGER_APPROVAL')->count(),
        ];
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
