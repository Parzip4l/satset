<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\ConsumableItem;
use App\Models\Master\ProcurementReceiving;
use App\Models\Master\Status;
use App\Models\Master\StockMovement;
use App\Models\Master\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $findings = $this->findingsSummary($tickets);
        $inventory = $this->inventorySummary($dateFrom, $dateTo);
        $recommendations = $this->recommendations($summary, $inventory, $findings);
        $narrative = $this->narrative($summary, $previous, $inventory, $findings);
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
            'findings',
            'inventory',
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

    private function findingsSummary(Collection $tickets): array
    {
        $findings = $tickets->filter(fn (Ticket $ticket) => data_get($ticket->payload, 'request_type') === 'ga_request_finding'
            && strtolower((string) data_get($ticket->payload, 'report_type')) === 'temuan');
        $completed = $findings->filter(fn (Ticket $ticket) => in_array(strtolower((string) $ticket->status?->name), self::COMPLETED_STATUSES, true))->count();

        return [
            'total' => $findings->count(),
            'open' => $findings->filter(fn (Ticket $ticket) => strtolower((string) $ticket->status?->name) === 'open')->count(),
            'in_progress' => $findings->filter(fn (Ticket $ticket) => str_contains(strtolower((string) $ticket->status?->name), 'progress'))->count(),
            'completed' => $completed,
            'completion_rate' => $findings->count() ? round(($completed / $findings->count()) * 100, 1) : 0,
            'locations' => $findings
                ->groupBy(fn (Ticket $ticket) => data_get($ticket->payload, 'location') ?: 'Tanpa lokasi')
                ->map(fn (Collection $rows, string $label) => ['label' => $label, 'value' => $rows->count()])
                ->sortByDesc('value')
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    private function inventorySummary(Carbon $from, Carbon $to): array
    {
        $empty = [
            'available' => false,
            'active_items' => 0,
            'low_stock' => 0,
            'outgoing_qty' => 0,
            'received_qty' => 0,
            'pending_receivings' => 0,
            'opname_variance' => 0,
            'trend' => [],
            'low_stock_items' => [],
            'categories' => [],
        ];

        if (! collect(['consumable_items', 'stock_movements', 'procurement_receivings', 'procurement_receiving_items', 'stock_opnames', 'stock_opname_items'])
            ->every(fn (string $table) => Schema::hasTable($table))) {
            return $empty;
        }

        $items = ConsumableItem::query()->where('is_active', true)->orderBy('name')->get();
        $lowStockItems = $items->filter(function (ConsumableItem $item) {
            $bigMinimum = (int) ceil(((int) $item->minimum_stock) / max(1, (int) $item->conversion_qty));

            return (int) $item->small_stock <= (int) $item->minimum_stock
                || (int) $item->current_stock <= $bigMinimum;
        });
        $movements = StockMovement::query()
            ->whereBetween('created_at', [$from, $to])
            ->get(['movement_type', 'qty', 'balance_before', 'balance_after', 'created_at']);
        $monthly = $from->diffInDays($to) > 62;
        $dateFormat = $monthly ? 'Y-m' : 'Y-m-d';
        $cursor = $from->copy()->startOfDay();
        $movementGroups = $movements->groupBy(fn (StockMovement $movement) => $movement->created_at->format($dateFormat));
        $trend = [];

        while ($cursor->lte($to)) {
            $key = $cursor->format($dateFormat);
            $periodMovements = $movementGroups->get($key, collect());
            $trend[$key] = [
                'label' => $monthly ? $cursor->translatedFormat('M Y') : $cursor->format('d M'),
                'incoming' => (int) $periodMovements->filter(fn (StockMovement $movement) => (int) $movement->balance_after > (int) $movement->balance_before)->sum('qty'),
                'outgoing' => (int) $periodMovements->filter(fn (StockMovement $movement) => (int) $movement->balance_after < (int) $movement->balance_before)->sum('qty'),
            ];
            $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        $receivedQty = (int) DB::table('procurement_receiving_items')
            ->join('procurement_receivings', 'procurement_receiving_items.receiving_id', '=', 'procurement_receivings.id')
            ->whereBetween('procurement_receivings.created_at', [$from, $to])
            ->sum('procurement_receiving_items.qty_received');
        $opnameVariance = (int) DB::table('stock_opname_items')
            ->join('stock_opnames', 'stock_opname_items.stock_opname_id', '=', 'stock_opnames.id')
            ->whereBetween('stock_opnames.created_at', [$from, $to])
            ->get(['stock_opname_items.variance'])
            ->sum(fn ($row) => abs((int) $row->variance));

        return [
            'available' => true,
            'active_items' => $items->count(),
            'low_stock' => $lowStockItems->count(),
            'outgoing_qty' => (int) $movements->filter(fn (StockMovement $movement) => (int) $movement->balance_after < (int) $movement->balance_before)->sum('qty'),
            'received_qty' => $receivedQty,
            'pending_receivings' => ProcurementReceiving::whereIn('status', ['DRAFT', 'SUBMITTED', 'PO_CREATED', 'PO_SENT_TO_VENDOR', 'DELIVERY_SCHEDULED'])->count(),
            'opname_variance' => $opnameVariance,
            'trend' => array_values($trend),
            'low_stock_items' => $lowStockItems->take(6)->map(fn (ConsumableItem $item) => [
                'code' => $item->code,
                'name' => $item->name,
                'big_stock' => (int) $item->current_stock,
                'small_stock' => (int) $item->small_stock,
                'minimum_stock' => (int) $item->minimum_stock,
            ])->values()->all(),
            'categories' => $items->groupBy(fn (ConsumableItem $item) => $item->category ?: 'Tanpa kategori')
                ->map(fn (Collection $rows, string $label) => [
                    'label' => $label,
                    'items' => $rows->count(),
                    'low_stock' => $rows->filter(fn (ConsumableItem $item) => $lowStockItems->contains('id', $item->id))->count(),
                ])->values()->all(),
        ];
    }

    private function recommendations(array $summary, array $inventory, array $findings): array
    {
        $items = [];

        if ($inventory['low_stock'] > 0) {
            $items[] = [
                'title' => 'Tindak lanjuti stok menipis',
                'description' => $inventory['low_stock'].' barang berada pada atau di bawah batas minimum. Verifikasi kebutuhan dan siapkan replenishment.',
                'level' => 'Inventori',
                'tone' => 'danger',
                'icon' => 'bi-box-seam',
            ];
        }

        if ($findings['open'] + $findings['in_progress'] > 0) {
            $items[] = [
                'title' => 'Selesaikan temuan fasilitas',
                'description' => ($findings['open'] + $findings['in_progress']).' temuan masih aktif. Fokuskan tindak lanjut pada lokasi dengan laporan terbanyak.',
                'level' => 'Temuan',
                'tone' => 'warning',
                'icon' => 'bi-building-exclamation',
            ];
        }

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

        return array_slice($items, 0, 4);
    }

    private function narrative(array $current, array $previous, array $inventory, array $findings): array
    {
        $ticketDelta = $current['total'] - $previous['total'];
        $rateDelta = round($current['completion_rate'] - $previous['completion_rate'], 1);
        $direction = $rateDelta > 0 ? 'membaik' : ($rateDelta < 0 ? 'menurun' : 'stabil');

        return [
            'headline' => 'Performa penyelesaian tiket '.$direction.' dibanding periode sebelumnya.',
            'detail' => 'Volume tiket '.($ticketDelta >= 0 ? 'bertambah ' : 'berkurang ').number_format(abs($ticketDelta)).' tiket, completion rate berubah '.($rateDelta >= 0 ? '+' : '').number_format($rateDelta, 1).'%. Terdapat '.number_format($findings['open'] + $findings['in_progress']).' temuan aktif dan '.number_format($inventory['low_stock']).' barang dengan stok menipis.',
            'tone' => $rateDelta > 0 ? 'success' : ($rateDelta < 0 ? 'danger' : 'secondary'),
        ];
    }
}
