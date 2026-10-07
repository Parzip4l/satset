<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Status;
use App\Models\Master\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExecutiveSummaryController extends Controller
{
    private const COMPLETED_STATUSES = ['resolved', 'closed', 'completed', 'selesai'];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'request_type' => ['nullable', 'in:consumption,atk_rtk,ga_request_finding'],
            'status_id' => ['nullable', 'integer', 'exists:statuses,id'],
        ]);

        $dateTo = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();
        $dateFrom = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : $dateTo->copy()->subDays(29)->startOfDay();

        if ($dateFrom->diffInDays($dateTo) > 366) {
            $dateFrom = $dateTo->copy()->subDays(366)->startOfDay();
        }

        $requestType = $validated['request_type'] ?? null;
        $statusId = isset($validated['status_id']) ? (int) $validated['status_id'] : null;
        $query = $this->ticketQuery($dateFrom, $dateTo, $requestType, $statusId);
        $tickets = $this->reportTickets($query);
        $summary = $this->summarize($tickets, $query);

        $periodDays = max(1, $dateFrom->diffInDays($dateTo) + 1);
        $previousTo = $dateFrom->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subDays($periodDays - 1)->startOfDay();
        $previousQuery = $this->ticketQuery($previousFrom, $previousTo, $requestType, $statusId);
        $previousTickets = $this->reportTickets($previousQuery);
        $previous = $this->summarize($previousTickets, $previousQuery);

        $trend = $this->trend($tickets, $dateFrom, $dateTo);
        $statusDistribution = $this->distribution($tickets, fn (Ticket $ticket) => $ticket->status?->name ?: 'Tanpa Status');
        $priorityDistribution = $this->distribution($tickets, fn (Ticket $ticket) => $ticket->priority?->name ?: 'Tanpa Prioritas');
        $requestTypeDistribution = $this->distribution($tickets, fn (Ticket $ticket) => $this->requestTypeLabel($ticket));
        $topCategories = $this->categorySummary($tickets);
        $recommendations = $this->recommendations($summary);
        $narrative = $this->narrative($summary, $previous);
        $statuses = Status::orderBy('name')->get();
        $requestTypes = [
            'consumption' => 'Permintaan Konsumsi',
            'atk_rtk' => 'Permintaan ATK / RTK',
            'ga_request_finding' => 'GA Permintaan & Temuan',
        ];

        return view('bum.executive-summary', compact(
            'dateFrom',
            'dateTo',
            'previousFrom',
            'previousTo',
            'requestType',
            'statusId',
            'summary',
            'previous',
            'trend',
            'statusDistribution',
            'priorityDistribution',
            'requestTypeDistribution',
            'topCategories',
            'recommendations',
            'narrative',
            'statuses',
            'requestTypes',
        ));
    }

    private function ticketQuery(Carbon $from, Carbon $to, ?string $requestType, ?int $statusId): Builder
    {
        return Ticket::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($requestType, fn (Builder $query) => $query->where('payload->request_type', $requestType))
            ->when($statusId, fn (Builder $query) => $query->where('status_id', $statusId));
    }

    private function reportTickets(Builder $query): Collection
    {
        return (clone $query)
            ->with(['status:id,name', 'priority:id,name', 'category:id,name', 'department:id,name'])
            ->get([
                'id',
                'status_id',
                'priority_id',
                'category_id',
                'department_id',
                'payload',
                'created_at',
                'resolved_at',
                'closed_at',
            ]);
    }

    private function summarize(Collection $tickets, Builder $query): array
    {
        $completed = $tickets->filter(fn (Ticket $ticket) => in_array(strtolower((string) $ticket->status?->name), self::COMPLETED_STATUSES, true));
        $open = $tickets->filter(fn (Ticket $ticket) => strtolower((string) $ticket->status?->name) === 'open')->count();
        $inProgress = $tickets->filter(fn (Ticket $ticket) => str_contains(strtolower((string) $ticket->status?->name), 'progress'))->count();
        $resolutionHours = $completed
            ->map(function (Ticket $ticket) {
                $finishedAt = $ticket->closed_at ?: $ticket->resolved_at;

                return $finishedAt ? $ticket->created_at->diffInMinutes(Carbon::parse($finishedAt)) / 60 : null;
            })
            ->filter(fn ($value) => $value !== null);
        $total = $tickets->count();

        return [
            'total' => $total,
            'completed' => $completed->count(),
            'completion_rate' => $total ? round(($completed->count() / $total) * 100, 1) : 0,
            'open' => $open,
            'in_progress' => $inProgress,
            'pending_approval' => (clone $query)->whereHas('approvals', fn (Builder $approval) => $approval->where('status', 'Pending'))->count(),
            'avg_resolution_hours' => $resolutionHours->isNotEmpty() ? round($resolutionHours->avg(), 1) : 0,
        ];
    }

    private function trend(Collection $tickets, Carbon $from, Carbon $to): array
    {
        $monthly = $from->diffInDays($to) > 62;
        $format = $monthly ? 'Y-m' : 'Y-m-d';
        $cursor = $from->copy()->startOfDay();
        $grouped = $tickets->groupBy(fn (Ticket $ticket) => $ticket->created_at->format($format));
        $rows = [];

        while ($cursor->lte($to)) {
            $key = $cursor->format($format);
            $periodTickets = $grouped->get($key, collect());
            $rows[$key] = [
                'label' => $monthly ? $cursor->translatedFormat('M Y') : $cursor->format('d M'),
                'total' => $periodTickets->count(),
                'completed' => $periodTickets->filter(fn (Ticket $ticket) => in_array(strtolower((string) $ticket->status?->name), self::COMPLETED_STATUSES, true))->count(),
            ];
            $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        return array_values($rows);
    }

    private function distribution(Collection $tickets, callable $resolver): array
    {
        return $tickets
            ->groupBy($resolver)
            ->map(fn (Collection $rows, string $label) => ['label' => $label, 'value' => $rows->count()])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function categorySummary(Collection $tickets): array
    {
        return $tickets
            ->groupBy(fn (Ticket $ticket) => $ticket->category?->name ?: $this->requestTypeLabel($ticket))
            ->map(function (Collection $rows, string $label) {
                $completed = $rows->filter(fn (Ticket $ticket) => in_array(strtolower((string) $ticket->status?->name), self::COMPLETED_STATUSES, true))->count();

                return [
                    'label' => $label,
                    'total' => $rows->count(),
                    'completed' => $completed,
                    'rate' => $rows->count() ? round(($completed / $rows->count()) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('total')
            ->take(6)
            ->values()
            ->all();
    }

    private function requestTypeLabel(Ticket $ticket): string
    {
        return match (data_get($ticket->payload, 'request_type')) {
            'consumption' => 'Permintaan Konsumsi',
            'atk_rtk' => 'Permintaan ATK / RTK',
            'ga_request_finding' => 'GA Permintaan & Temuan',
            default => data_get($ticket->payload, 'request_label', 'Lainnya'),
        };
    }

    private function recommendations(array $summary): array
    {
        $items = [];

        if ($summary['pending_approval'] > 0) {
            $items[] = [
                'title' => 'Percepat antrean approval',
                'description' => $summary['pending_approval'].' tiket masih menunggu keputusan. Prioritaskan tiket paling lama agar waktu tunggu tidak bertambah.',
                'level' => 'Perlu perhatian',
                'tone' => 'warning',
                'icon' => 'bi-person-check',
            ];
        }

        if ($summary['completion_rate'] < 80 && $summary['total'] > 0) {
            $items[] = [
                'title' => 'Naikkan penyelesaian tiket',
                'description' => 'Completion rate saat ini '.$summary['completion_rate'].'%. Tinjau tiket open dan in progress untuk menentukan PIC serta target penyelesaian.',
                'level' => 'Prioritas',
                'tone' => 'danger',
                'icon' => 'bi-speedometer2',
            ];
        }

        if ($summary['open'] + $summary['in_progress'] > $summary['completed']) {
            $items[] = [
                'title' => 'Kendalikan backlog aktif',
                'description' => ($summary['open'] + $summary['in_progress']).' tiket masih aktif, lebih tinggi dari tiket selesai pada periode ini. Lakukan triage berdasarkan prioritas.',
                'level' => 'Tindak lanjut',
                'tone' => 'info',
                'icon' => 'bi-inboxes',
            ];
        }

        if ($items === []) {
            $items[] = [
                'title' => 'Pertahankan ritme operasional',
                'description' => 'Tidak ada indikator kritis pada periode ini. Pertahankan kecepatan approval dan penyelesaian tiket.',
                'level' => 'Terkendali',
                'tone' => 'success',
                'icon' => 'bi-check2-circle',
            ];
        }

        return array_slice($items, 0, 3);
    }

    private function narrative(array $current, array $previous): array
    {
        $ticketDelta = $current['total'] - $previous['total'];
        $rateDelta = round($current['completion_rate'] - $previous['completion_rate'], 1);
        $direction = $rateDelta > 0 ? 'membaik' : ($rateDelta < 0 ? 'menurun' : 'stabil');

        return [
            'headline' => 'Performa penyelesaian tiket '.$direction.' dibanding periode sebelumnya.',
            'detail' => 'Volume tiket '.($ticketDelta >= 0 ? 'bertambah ' : 'berkurang ').number_format(abs($ticketDelta)).' tiket, sementara completion rate berubah '.($rateDelta >= 0 ? '+' : '').number_format($rateDelta, 1).'%.',
            'tone' => $rateDelta > 0 ? 'success' : ($rateDelta < 0 ? 'danger' : 'secondary'),
        ];
    }
}
