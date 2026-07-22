<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\ConsumableItem;
use App\Models\Master\ProcurementReceiving;
use App\Models\Master\ProcurementReceivingItem;
use App\Models\Master\StockMovement;
use App\Models\Master\StockOpname;
use App\Models\Master\Ticket;
use App\Services\ConsumableStockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class BumInventoryController extends Controller
{
    public function dashboard(Request $request)
    {
        $gaType = $request->get('ga_type', 'all');
        if (!in_array($gaType, ['all', 'Permintaan', 'Temuan'], true)) {
            $gaType = 'all';
        }

        $atkPending = Ticket::where('payload->request_type', 'atk_rtk')
            ->where(function ($query) {
                $query->whereNull('payload->workflow_status')
                    ->orWhereNotIn('payload->workflow_status', ['HANDED_OVER', 'CLOSED', 'CANCELLED', 'REJECTED_BY_MANAGER']);
            })
            ->count();
        $consumptionPending = Ticket::where('payload->request_type', 'consumption')
            ->where(function ($query) {
                $query->whereNull('payload->workflow_status')
                    ->orWhereNotIn('payload->workflow_status', ['CLOSED', 'CANCELLED', 'REJECTED_BY_MANAGER']);
            })
            ->count();
        $inventoryTablesReady = collect([
            'consumable_items',
            'procurement_receivings',
            'stock_opnames',
        ])->every(fn ($table) => Schema::hasTable($table));

        if ($inventoryTablesReady) {
            $lowStockCount = ConsumableItem::whereColumn('current_stock', '<=', 'minimum_stock')->where('is_active', true)->count();
            $pendingReceiving = ProcurementReceiving::whereIn('status', ['DRAFT', 'SUBMITTED', 'PO_CREATED', 'PO_SENT_TO_VENDOR', 'DELIVERY_SCHEDULED'])->count();
            $currentOpname = StockOpname::where('period', now()->format('Y-m'))->latest()->first();
            $lowStockItems = ConsumableItem::whereColumn('current_stock', '<=', 'minimum_stock')->orderBy('name')->take(8)->get();
        } else {
            $lowStockCount = 0;
            $pendingReceiving = 0;
            $currentOpname = null;
            $lowStockItems = collect();
        }

        $gaBaseQuery = Ticket::with(['requester', 'status'])
            ->where('payload->request_type', 'ga_request_finding')
            ->when($gaType !== 'all', fn ($query) => $query->where('payload->report_type', $gaType));

        $gaTickets = (clone $gaBaseQuery)->latest()->get();
        $gaTotalAll = Ticket::where('payload->request_type', 'ga_request_finding')->count();
        $gaPermintaanAll = Ticket::where('payload->request_type', 'ga_request_finding')->where('payload->report_type', 'Permintaan')->count();
        $gaTemuanAll = Ticket::where('payload->request_type', 'ga_request_finding')->where('payload->report_type', 'Temuan')->count();

        $gaStats = [
            'total' => $gaTickets->count(),
            'open' => $gaTickets->filter(fn ($ticket) => ($ticket->status->name ?? null) === 'Open')->count(),
            'in_progress' => $gaTickets->filter(fn ($ticket) => in_array($ticket->status->name ?? null, ['In Progress', 'Pending'], true))->count(),
            'completed' => $gaTickets->filter(fn ($ticket) => in_array($ticket->status->name ?? null, ['Resolved', 'Closed'], true))->count(),
            'all_total' => $gaTotalAll,
            'all_permintaan' => $gaPermintaanAll,
            'all_temuan' => $gaTemuanAll,
        ];

        $gaStatusBreakdown = $gaTickets
            ->groupBy(fn ($ticket) => $ticket->status->name ?? 'Tanpa Status')
            ->map(fn ($items) => $items->count())
            ->sortDesc();

        $gaLocationBreakdown = $gaTickets
            ->groupBy(fn ($ticket) => data_get($ticket->payload, 'location') ?: 'Tanpa Lokasi')
            ->map(fn ($items) => $items->count())
            ->sortDesc()
            ->take(6);

        $gaRecentTickets = $gaTickets->take(8);

        return view('bum.dashboard', compact(
            'atkPending',
            'consumptionPending',
            'lowStockCount',
            'pendingReceiving',
            'currentOpname',
            'lowStockItems',
            'inventoryTablesReady',
            'gaType',
            'gaStats',
            'gaStatusBreakdown',
            'gaLocationBreakdown',
            'gaRecentTickets'
        ));
    }

    public function items(Request $request)
    {
        $sort = $request->get('sort', 'name');
        $direction = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $sortable = [
            'code' => 'code',
            'name' => 'name',
            'category' => 'category',
            'location' => 'location',
            'current_stock' => 'current_stock',
            'minimum_stock' => 'minimum_stock',
            'is_active' => 'is_active',
        ];
        $sortColumn = $sortable[$sort] ?? 'name';

        $items = ConsumableItem::query()
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy($sortColumn, $direction)
            ->orderBy('name')
            ->paginate(15);

        return view('bum.items', compact('items'));
    }

    public function storeItem(Request $request, ConsumableStockService $stockService)
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:consumable_items,code',
            'name' => 'required|string|max:150',
            'category' => 'required|in:ATK,RTK',
            'large_uom' => 'required|string|max:30',
            'small_uom' => 'required|string|max:30',
            'conversion_qty' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'buffer_stock' => 'required|integer|min:0',
            'current_stock' => 'required|integer|min:0',
            'small_stock' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $initialStock = (int) $data['current_stock'];
        $initialSmallStock = (int) ($data['small_stock'] ?? 0);
        $data['unit'] = $data['small_uom'];
        $data['current_stock'] = 0;
        $data['small_stock'] = 0;
        $data['is_active'] = $request->boolean('is_active', true);
        $item = ConsumableItem::create($data);

        if ($initialStock > 0) {
            $stockService->increase($item, $initialStock, 'initial_stock', $item->id, 'Stok awal Gudang Besar', auth()->id());
        }

        if ($initialSmallStock > 0) {
            $stockService->increaseSmall($item, $initialSmallStock, 'initial_stock', $item->id, 'Stok awal Gudang Kecil', auth()->id());
        }

        return redirect()
            ->route('bum.items', ['create' => 1])
            ->with('success', 'Master barang berhasil ditambahkan. Silakan input barang berikutnya jika ada.');
    }

    public function showItem(Request $request, ConsumableItem $item)
    {
        $item->loadCount('movements');
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'created_at' => 'created_at',
            'movement_type' => 'movement_type',
            'qty' => 'qty',
            'balance_after' => 'balance_after',
        ];
        $sortColumn = $sortable[$sort] ?? 'created_at';

        $movements = StockMovement::with('creator')
            ->where('item_id', $item->id)
            ->orderBy($sortColumn, $direction)
            ->paginate(15);

        $receivingLines = ProcurementReceivingItem::with('receiving')
            ->where('item_id', $item->id)
            ->latest()
            ->take(10)
            ->get();

        $monthStart = now()->subMonths(5)->startOfMonth();
        $monthlyRows = StockMovement::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw("SUM(CASE WHEN balance_after > balance_before THEN qty ELSE 0 END) as incoming")
            ->selectRaw("SUM(CASE WHEN balance_after < balance_before THEN qty ELSE 0 END) as outgoing")
            ->where('item_id', $item->id)
            ->where('created_at', '>=', $monthStart)
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $monthlyTrend = collect(range(0, 5))->map(function ($index) use ($monthStart, $monthlyRows) {
            $period = $monthStart->copy()->addMonths($index)->format('Y-m');
            $row = $monthlyRows->get($period);

            return [
                'period' => $period,
                'incoming' => (int) ($row->incoming ?? 0),
                'outgoing' => (int) ($row->outgoing ?? 0),
            ];
        });

        $summary = [
            'incoming_total' => (int) StockMovement::where('item_id', $item->id)->whereColumn('balance_after', '>', 'balance_before')->sum('qty'),
            'outgoing_total' => (int) StockMovement::where('item_id', $item->id)->whereColumn('balance_after', '<', 'balance_before')->sum('qty'),
            'last_movement_at' => optional(StockMovement::where('item_id', $item->id)->latest()->first())->created_at,
            'stock_status' => $item->current_stock <= $item->minimum_stock ? 'LOW_STOCK' : 'AMAN',
        ];

        return view('bum.item-show', compact('item', 'movements', 'receivingLines', 'monthlyTrend', 'summary'));
    }

    public function updateItem(Request $request, ConsumableItem $item)
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:consumable_items,code,' . $item->id,
            'name' => 'required|string|max:150',
            'category' => 'required|in:ATK,RTK',
            'large_uom' => 'required|string|max:30',
            'small_uom' => 'required|string|max:30',
            'conversion_qty' => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'buffer_stock' => 'required|integer|min:0',
            'location' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['unit'] = $data['small_uom'];
        $item->update($data);

        return back()->with('success', 'Master barang berhasil diperbarui.');
    }

    public function adjustItemStock(Request $request, ConsumableItem $item, ConsumableStockService $stockService)
    {
        $data = $request->validate([
            'direction' => 'required|in:in,out',
            'stock_location' => 'required|in:big_warehouse,small_warehouse',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $locationLabel = $data['stock_location'] === 'small_warehouse' ? 'Gudang Kecil' : 'Gudang Besar';
        $notes = $data['notes'] ?: ($data['direction'] === 'in' ? "Penambahan stok manual {$locationLabel}" : "Pengurangan stok manual {$locationLabel}");

        try {
            if ($data['stock_location'] === 'small_warehouse' && $data['direction'] === 'in') {
                $stockService->increaseSmall($item, (int) $data['qty'], 'manual_adjustment', $item->id, $notes, auth()->id());
            } elseif ($data['stock_location'] === 'small_warehouse') {
                $stockService->decreaseSmall($item, (int) $data['qty'], 'manual_adjustment', $item->id, $notes, auth()->id());
            } elseif ($data['direction'] === 'in') {
                $stockService->increase($item, (int) $data['qty'], 'manual_adjustment', $item->id, $notes, auth()->id());
            } else {
                $stockService->decrease($item, (int) $data['qty'], 'manual_adjustment', $item->id, $notes, auth()->id());
            }
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Stok barang berhasil diperbarui.');
    }

    public function stockCard(Request $request)
    {
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'created_at' => 'created_at',
            'item_id' => 'item_id',
            'movement_type' => 'movement_type',
            'qty' => 'qty',
            'balance_before' => 'balance_before',
            'balance_after' => 'balance_after',
            'reference_type' => 'reference_type',
        ];
        $sortColumn = $sortable[$sort] ?? 'created_at';

        $items = ConsumableItem::orderBy('name')->get();
        $movements = StockMovement::with(['item', 'creator'])
            ->when($request->item_id, fn ($query, $itemId) => $query->where('item_id', $itemId))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderBy($sortColumn, $direction)
            ->paginate(20);

        return view('bum.stock-card', compact('items', 'movements'));
    }

    public function receivings()
    {
        $request = request();
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'reference_number' => 'reference_number',
            'vendor_name' => 'vendor_name',
            'status' => 'status',
            'received_date' => 'received_date',
            'created_at' => 'created_at',
        ];
        $sortColumn = $sortable[$sort] ?? 'created_at';

        $baseQuery = ProcurementReceiving::with('items.item')
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('received_date', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('received_date', '<=', $date))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%")
                        ->orWhere('po_number', 'like', "%{$search}%")
                        ->orWhere('do_number', 'like', "%{$search}%")
                        ->orWhere('gr_number', 'like', "%{$search}%")
                        ->orWhereHas('items.item', function ($itemQuery) use ($search) {
                            $itemQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            });

        $filteredIds = (clone $baseQuery)->pluck('id');
        $receivingLines = ProcurementReceivingItem::whereIn('receiving_id', $filteredIds);
        $receivingStats = [
            'documents' => $filteredIds->count(),
            'order_qty' => (clone $receivingLines)->sum('qty_ordered'),
            'received_qty' => (clone $receivingLines)->sum('qty_received'),
            'rejected_qty' => (clone $receivingLines)->sum('qty_rejected'),
        ];

        $receivings = $baseQuery
            ->orderBy($sortColumn, $direction)
            ->paginate(10);
        $items = ConsumableItem::where('is_active', true)->orderBy('name')->get();

        return view('bum.receivings', compact('receivings', 'items', 'receivingStats'));
    }

    public function storeReceiving(Request $request)
    {
        $data = $request->validate([
            'vendor_name' => 'nullable|string|max:150',
            'po_number' => 'nullable|string|max:80',
            'do_number' => 'nullable|string|max:80',
            'gr_number' => 'nullable|string|max:80',
            'scheduled_delivery_date' => 'nullable|date',
            'received_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:consumable_items,id',
            'items.*.qty_ordered' => 'required|integer|min:0',
            'items.*.qty_received' => 'nullable|integer|min:0',
            'items.*.qty_rejected' => 'nullable|integer|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            $receiving = ProcurementReceiving::create([
                'reference_number' => $this->nextReference('RCV'),
                'vendor_name' => $data['vendor_name'] ?? null,
                'po_number' => $data['po_number'] ?? null,
                'do_number' => $data['do_number'] ?? null,
                'gr_number' => $data['gr_number'] ?? null,
                'scheduled_delivery_date' => $data['scheduled_delivery_date'] ?? null,
                'received_date' => $data['received_date'] ?? null,
                'status' => 'SUBMITTED',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                ProcurementReceivingItem::create([
                    'receiving_id' => $receiving->id,
                    'item_id' => $row['item_id'],
                    'qty_ordered' => $row['qty_ordered'],
                    'qty_received' => $row['qty_received'] ?? 0,
                    'qty_rejected' => $row['qty_rejected'] ?? 0,
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('bum.receivings', ['create' => 1])
            ->with('success', 'Dokumen penerimaan berhasil dibuat. Silakan input dokumen berikutnya jika ada.');
    }

    public function receive(Request $request, ProcurementReceiving $receiving, ConsumableStockService $stockService)
    {
        $data = $request->validate([
            'status' => 'required|in:RECEIVED,PARTIALLY_RECEIVED,REJECTED,STORED,CLOSED',
            'received_date' => 'required|date',
            'items' => 'required|array',
            'items.*.qty_received' => 'required|integer|min:0',
            'items.*.qty_rejected' => 'required|integer|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $receiving, $stockService) {
            $receiving->update([
                'status' => $data['status'],
                'received_date' => $data['received_date'],
            ]);

            foreach ($receiving->items()->with('item')->get() as $line) {
                $row = $data['items'][$line->id] ?? null;
                if (!$row) {
                    continue;
                }

                $previousReceived = (int) $line->qty_received;
                $newReceived = (int) $row['qty_received'];
                $deltaReceived = $newReceived - $previousReceived;

                $line->update([
                    'qty_received' => $newReceived,
                    'qty_rejected' => (int) $row['qty_rejected'],
                    'notes' => $row['notes'] ?? $line->notes,
                ]);

                if ($deltaReceived > 0) {
                    $stockService->increase($line->item, $deltaReceived, 'procurement_receiving', $receiving->id, 'Penerimaan ' . $receiving->reference_number, auth()->id());
                }
            }
        });

        return back()->with('success', 'Penerimaan barang dan stock card berhasil diperbarui.');
    }

    public function opnames()
    {
        $sort = request('sort', 'created_at');
        $direction = request('direction') === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'period' => 'period',
            'status' => 'status',
            'created_at' => 'created_at',
        ];
        $sortColumn = $sortable[$sort] ?? 'created_at';

        $opnames = StockOpname::with('items.item')
            ->orderBy($sortColumn, $direction)
            ->paginate(12);
        $items = ConsumableItem::where('is_active', true)->orderBy('name')->get();

        return view('bum.opnames', compact('opnames', 'items'));
    }

    public function storeOpname(Request $request, ConsumableStockService $stockService)
    {
        $data = $request->validate([
            'period' => 'required|date_format:Y-m',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:consumable_items,id',
            'items.*.physical_stock' => 'required|integer|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $stockService) {
            $opname = StockOpname::create([
                'period' => $data['period'],
                'status' => 'CLOSED',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                $item = ConsumableItem::lockForUpdate()->findOrFail($row['item_id']);
                $systemStock = (int) $item->current_stock;
                $physicalStock = (int) $row['physical_stock'];
                $variance = $physicalStock - $systemStock;

                $opname->items()->create([
                    'item_id' => $item->id,
                    'system_stock' => $systemStock,
                    'physical_stock' => $physicalStock,
                    'variance' => $variance,
                    'notes' => $row['notes'] ?? null,
                ]);

                if ($variance !== 0) {
                    $stockService->adjustment($item, $variance, 'stock_opname', $opname->id, 'Adjustment stock opname ' . $data['period'], auth()->id());
                }
            }
        });

        return redirect()
            ->route('bum.opnames', ['create' => 1])
            ->with('success', 'Stock opname ditutup dan adjustment tercatat. Silakan input item berikutnya jika ada.');
    }

    public function reports(Request $request)
    {
        $period = $request->period ?: now()->format('Y-m');
        $date = Carbon::createFromFormat('Y-m', $period);

        $usage = StockMovement::with('item')
            ->where('movement_type', 'OUT')
            ->whereYear('created_at', $date->year)
            ->whereMonth('created_at', $date->month)
            ->get()
            ->groupBy('item_id');

        $receivings = ProcurementReceiving::with('items.item')
            ->whereYear('received_date', $date->year)
            ->whereMonth('received_date', $date->month)
            ->latest('received_date')
            ->get();

        $consumptions = Ticket::where('payload->request_type', 'consumption')
            ->whereYear('created_at', $date->year)
            ->whereMonth('created_at', $date->month)
            ->latest()
            ->get();

        return view('bum.reports', compact('period', 'usage', 'receivings', 'consumptions'));
    }

    private function nextReference(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd-His');
    }
}
