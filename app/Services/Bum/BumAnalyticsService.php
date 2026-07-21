<?php

namespace App\Services\Bum;

use App\Models\Master\ConsumableItem;
use App\Models\Master\ProcurementReceiving;
use App\Models\Master\StockMovement;
use App\Models\Master\Ticket;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BumAnalyticsService
{
    public function __construct(private ForecastHelper $forecastHelper)
    {
    }

    public function filters(array $input): array
    {
        $period = $input['period'] ?? '30d';
        $days = match ($period) {
            '7d' => 7,
            '3m' => 90,
            '6m' => 180,
            '12m' => 365,
            default => 30,
        };

        $endDate = !empty($input['end_date']) ? Carbon::parse($input['end_date'])->endOfDay() : now()->endOfDay();
        $startDate = !empty($input['start_date']) ? Carbon::parse($input['start_date'])->startOfDay() : $endDate->copy()->subDays($days - 1)->startOfDay();
        $forecastDays = max(1, min(90, (int) ($input['forecast_days'] ?? 30)));

        return [
            'period' => $period,
            'period_days' => max(1, $startDate->diffInDays($endDate) + 1),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'category' => $input['category'] ?? null,
            'item_id' => $input['item_id'] ?? null,
            'department_id' => $input['department_id'] ?? null,
            'forecast_days' => $forecastDays,
        ];
    }

    public function summary(array $filters): array
    {
        $movementQuery = $this->movementQuery($filters)->where('movement_type', 'OUT');
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $stockForecast = collect($this->stockForecast($filters));
        $recommendations = collect($this->procurementRecommendation($filters));

        return [
            'total_usage_this_month' => (int) StockMovement::where('movement_type', 'OUT')->whereBetween('created_at', [$monthStart, $monthEnd])->sum('qty'),
            'atk_rtk_requests_this_month' => Ticket::where('payload->request_type', 'atk_rtk')->whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'meeting_consumption_this_month' => Ticket::where('payload->request_type', 'consumption')->whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'receiving_this_month' => (int) DB::table('procurement_receiving_items')
                ->join('procurement_receivings', 'procurement_receiving_items.receiving_id', '=', 'procurement_receivings.id')
                ->whereBetween('procurement_receivings.received_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('procurement_receiving_items.qty_received'),
            'low_stock_items' => ConsumableItem::whereColumn('current_stock', '<=', 'minimum_stock')->where('is_active', true)->count(),
            'predicted_stock_out_30_days' => $stockForecast->filter(fn ($row) => in_array($row['risk_level'], ['CRITICAL', 'HIGH', 'MEDIUM'], true))->count(),
            'next_month_recommended_purchase_qty' => $recommendations->sum('recommended_purchase_qty'),
            'filtered_usage_total' => (int) $movementQuery->sum('qty'),
        ];
    }

    public function usageTrend(array $filters): array
    {
        $rows = $this->movementQuery($filters)
            ->where('movement_type', 'OUT')
            ->selectRaw('DATE(stock_movements.created_at) as usage_date, SUM(stock_movements.qty) as total_qty')
            ->groupBy('usage_date')
            ->orderBy('usage_date')
            ->pluck('total_qty', 'usage_date');

        return $this->dailySeries($filters, $rows, 'usage');
    }

    public function usageForecast(array $filters): array
    {
        $actual = $this->usageTrend($filters);
        $actualValues = array_map(fn ($row) => (float) $row['usage'], $actual);
        $forecastValues = $this->forecastHelper->forecastSeries($actualValues, $filters['forecast_days'], min(30, max(7, (int) floor($filters['period_days'] / 4))));
        $start = now()->addDay();

        $forecast = [];
        foreach ($forecastValues as $index => $value) {
            $forecast[] = [
                'date' => $start->copy()->addDays($index)->toDateString(),
                'forecast' => $value,
            ];
        }

        return [
            'today' => now()->toDateString(),
            'actual' => $actual,
            'forecast' => $forecast,
        ];
    }

    public function stockForecast(array $filters): array
    {
        $items = ConsumableItem::query()
            ->when($filters['category'], fn ($query, $category) => $query->where('category', $category))
            ->when($filters['item_id'], fn ($query, $itemId) => $query->whereKey($itemId))
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $usageByItem = $this->movementQuery($filters)
            ->where('movement_type', 'OUT')
            ->selectRaw('stock_movements.item_id, SUM(stock_movements.qty) as total_qty')
            ->groupBy('stock_movements.item_id')
            ->pluck('total_qty', 'item_id');

        return $items->map(function (ConsumableItem $item) use ($filters, $usageByItem) {
            $totalUsage = (int) ($usageByItem[$item->id] ?? 0);
            $averageDailyUsage = $this->forecastHelper->calculateAverageUsage([$totalUsage], $filters['period_days']);
            $daysUntilStockOut = $averageDailyUsage > 0 ? round($item->current_stock / $averageDailyUsage, 1) : null;
            $stockOutDate = $this->forecastHelper->calculateStockOutDate((int) $item->current_stock, $averageDailyUsage);
            $recommendedQty = $this->forecastHelper->calculateRecommendedPurchaseQty((int) $item->current_stock, (int) $item->minimum_stock, (int) $item->buffer_stock, $averageDailyUsage, $filters['forecast_days']);
            $riskLevel = $this->forecastHelper->calculateRiskLevel((int) $item->current_stock, (int) $item->minimum_stock, $daysUntilStockOut);

            return [
                'item_id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'category' => $item->category,
                'current_stock' => (int) $item->current_stock,
                'minimum_stock' => (int) $item->minimum_stock,
                'buffer_stock' => (int) $item->buffer_stock,
                'average_daily_usage' => $averageDailyUsage,
                'estimated_days_until_stock_out' => $daysUntilStockOut,
                'estimated_stock_out_date' => $stockOutDate?->toDateString(),
                'recommended_purchase_qty' => $recommendedQty,
                'risk_level' => $riskLevel,
            ];
        })->values()->all();
    }

    public function requestTrend(array $filters): array
    {
        $query = Ticket::query()
            ->where('payload->request_type', 'atk_rtk')
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->when($filters['department_id'], fn ($query, $departmentId) => $query->where('department_id', $departmentId));

        $monthly = (clone $query)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('total', 'period');

        $statuses = (clone $query)
            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.workflow_status')), 'SUBMITTED') as status_name, COUNT(*) as total")
            ->groupBy('status_name')
            ->pluck('total', 'status_name');

        $topItems = (clone $query)
            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.item_type')), 'Tidak ditentukan') as item_name, COUNT(*) as total")
            ->groupBy('item_name')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'item_name');

        return [
            'monthly' => $this->monthSeries($filters, $monthly, 'total'),
            'statuses' => $this->assocSeries($statuses),
            'top_items' => $this->assocSeries($topItems),
        ];
    }

    public function meetingConsumptionTrend(array $filters): array
    {
        $query = Ticket::query()
            ->where('payload->request_type', 'consumption')
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->when($filters['department_id'], fn ($query, $departmentId) => $query->where('department_id', $departmentId));

        $monthly = (clone $query)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total, SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.participant_count')) AS UNSIGNED)) as participants, SUM(CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.actual_cost')), JSON_UNQUOTE(JSON_EXTRACT(payload, '$.estimated_cost')), 0) AS DECIMAL(15,2))) as cost")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $statuses = (clone $query)
            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.workflow_status')), 'SUBMITTED') as status_name, COUNT(*) as total")
            ->groupBy('status_name')
            ->pluck('total', 'status_name');

        return [
            'monthly' => $monthly->map(fn ($row) => [
                'period' => $row->period,
                'requests' => (int) $row->total,
                'participants' => (int) $row->participants,
                'cost' => (float) $row->cost,
            ])->values()->all(),
            'statuses' => $this->assocSeries($statuses),
        ];
    }

    public function receivingTrend(array $filters): array
    {
        $query = DB::table('procurement_receivings')
            ->leftJoin('procurement_receiving_items', 'procurement_receivings.id', '=', 'procurement_receiving_items.receiving_id')
            ->whereBetween('procurement_receivings.created_at', [$filters['start_date'], $filters['end_date']]);

        $monthly = (clone $query)
            ->selectRaw("DATE_FORMAT(procurement_receivings.created_at, '%Y-%m') as period, SUM(procurement_receiving_items.qty_received) as received_qty, SUM(procurement_receiving_items.qty_rejected) as rejected_qty")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $statuses = ProcurementReceiving::whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $vendors = ProcurementReceiving::whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->whereNotNull('vendor_name')
            ->selectRaw('vendor_name, COUNT(*) as total')
            ->groupBy('vendor_name')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'vendor_name');

        return [
            'monthly' => $monthly->map(fn ($row) => [
                'period' => $row->period,
                'received_qty' => (int) $row->received_qty,
                'rejected_qty' => (int) $row->rejected_qty,
            ])->values()->all(),
            'statuses' => $this->assocSeries($statuses),
            'vendors' => $this->assocSeries($vendors),
        ];
    }

    public function procurementRecommendation(array $filters): array
    {
        return collect($this->stockForecast($filters))
            ->filter(fn ($row) => $row['recommended_purchase_qty'] > 0 || in_array($row['risk_level'], ['CRITICAL', 'HIGH', 'MEDIUM'], true))
            ->sortBy(fn ($row) => array_search($row['risk_level'], ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'NO_DATA'], true))
            ->values()
            ->all();
    }

    private function movementQuery(array $filters)
    {
        return StockMovement::query()
            ->join('consumable_items', 'stock_movements.item_id', '=', 'consumable_items.id')
            ->whereBetween('stock_movements.created_at', [$filters['start_date'], $filters['end_date']])
            ->when($filters['category'], fn ($query, $category) => $query->where('consumable_items.category', $category))
            ->when($filters['item_id'], fn ($query, $itemId) => $query->where('stock_movements.item_id', $itemId));
    }

    private function dailySeries(array $filters, Collection $rows, string $valueKey): array
    {
        $series = [];
        foreach (CarbonPeriod::create($filters['start_date']->copy()->startOfDay(), $filters['end_date']->copy()->startOfDay()) as $date) {
            $key = $date->toDateString();
            $series[] = [
                'date' => $key,
                $valueKey => (int) ($rows[$key] ?? 0),
            ];
        }

        return $series;
    }

    private function monthSeries(array $filters, Collection $rows, string $valueKey): array
    {
        $start = $filters['start_date']->copy()->startOfMonth();
        $end = $filters['end_date']->copy()->startOfMonth();
        $series = [];

        while ($start <= $end) {
            $key = $start->format('Y-m');
            $series[] = [
                'period' => $key,
                $valueKey => (int) ($rows[$key] ?? 0),
            ];
            $start->addMonth();
        }

        return $series;
    }

    private function assocSeries(Collection $rows): array
    {
        return $rows->map(fn ($total, $label) => [
            'label' => $label ?: 'Tidak ditentukan',
            'total' => (int) $total,
        ])->values()->all();
    }
}
