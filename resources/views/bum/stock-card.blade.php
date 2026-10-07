@extends('partials.layouts.master')

@section('title', 'Stock Card')
@section('css')
    @include('bum.partials.mobile-style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .stock-card-page {
            --stock-ink: #1f2933;
            --stock-muted: #7b8794;
            --stock-line: #e7ecf2;
            --stock-soft: #f8fafc;
            --stock-primary: #e21a1a;
        }

        .stock-card-page .page-title {
            color: var(--stock-ink);
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .3rem;
        }

        .stock-card-page .page-subtitle,
        .stock-card-page .muted-text {
            color: var(--stock-muted);
        }

        .stock-card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
        }

        .stock-card-btn {
            border-radius: 8px;
            font-size: .76rem;
            font-weight: 600;
            min-height: 36px;
            padding: .45rem .85rem;
        }

        .stock-card-panel {
            background: #fff;
            border: 1px solid var(--stock-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
            overflow: hidden;
        }

        .filter-panel {
            padding: 1rem;
        }

        .filter-panel .form-label {
            font-size: .7rem !important;
            font-weight: 600 !important;
        }

        .filter-panel .form-control,
        .filter-panel .form-select,
        .filter-panel .select2-selection__rendered {
            font-size: .76rem !important;
            font-weight: 400 !important;
        }

        .stock-table thead th {
            background: var(--stock-soft);
            border-bottom: 1px solid var(--stock-line);
            color: #394150;
            font-size: .7rem;
            font-weight: 600;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .stock-table tbody td {
            border-color: var(--stock-line);
            color: #364152;
            font-size: .72rem;
            font-weight: 400;
            padding: .82rem 1rem;
            vertical-align: middle;
        }

        .stock-table thead a {
            font-weight: 600 !important;
        }

        .stock-table tbody .fw-semibold,
        .stock-table tbody .fw-bold,
        .stock-table tbody a.fw-semibold {
            font-weight: 500 !important;
        }

        .stock-movement-row {
            cursor: pointer;
            transition: background-color .16s ease, box-shadow .16s ease;
        }

        .stock-movement-row:hover,
        .stock-movement-row:focus-visible {
            background: #fff8f8;
            box-shadow: inset 3px 0 0 var(--stock-primary);
            outline: 0;
        }

        .stock-movement-row:focus-visible {
            box-shadow: inset 3px 0 0 var(--stock-primary), 0 0 0 2px rgba(226, 26, 26, .12);
        }

        .stock-pagination {
            align-items: center;
            border-top: 1px solid var(--stock-line);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1rem;
        }

        .stock-pagination .pagination {
            margin-bottom: 0;
        }

        .stock-pagination .page-link {
            border-radius: 6px;
            color: #596575;
            font-size: .78rem;
            font-weight: 800;
            margin: 0 .1rem;
            min-width: 32px;
            text-align: center;
        }

        .stock-pagination .active > .page-link {
            background: var(--stock-primary);
            border-color: var(--stock-primary);
            color: #fff;
        }

        .soft-badge {
            border-radius: 6px;
            font-size: .66rem;
            font-weight: 600;
            padding: .32rem .55rem;
        }

        .select2-container--bootstrap-5 .select2-selection {
            border-color: var(--stock-line);
            border-radius: 8px;
            min-height: 42px;
        }

        .stock-detail-modal .modal-dialog {
            filter: drop-shadow(0 28px 40px rgba(24, 31, 41, .24));
            max-width: min(980px, calc(100vw - 2rem));
        }

        .stock-detail-modal .modal-content {
            border: 0;
            border-radius: 16px;
            box-shadow: none;
            overflow: hidden;
            -webkit-mask:
                radial-gradient(circle at left center, transparent 0 16px, #000 17px),
                radial-gradient(circle at right center, transparent 0 16px, #000 17px);
            -webkit-mask-composite: source-in;
            mask:
                radial-gradient(circle at left center, transparent 0 16px, #000 17px),
                radial-gradient(circle at right center, transparent 0 16px, #000 17px);
            mask-composite: intersect;
        }

        .stock-detail-modal .modal-header {
            align-items: flex-start;
            background: linear-gradient(135deg, #fff7f7, #fff 65%);
            border-bottom: 1px solid var(--stock-line);
            padding: 1.35rem 1.5rem;
        }

        .movement-modal-kicker {
            color: var(--stock-primary);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .08em;
            margin-bottom: .3rem;
            text-transform: uppercase;
        }

        .movement-modal-title {
            color: var(--stock-ink);
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.3;
            margin: 0;
        }

        .movement-modal-subtitle {
            color: var(--stock-muted);
            font-size: .76rem;
            margin-top: .35rem;
        }

        .stock-detail-modal .modal-body {
            padding: 1.5rem;
        }

        .movement-highlight {
            align-items: center;
            background: var(--stock-soft);
            border: 1px solid var(--stock-line);
            border-radius: 12px;
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1fr) auto;
            margin-bottom: 1rem;
            padding: 1rem;
        }

        .movement-type-icon {
            align-items: center;
            background: rgba(226, 26, 26, .08);
            border-radius: 10px;
            color: var(--stock-primary);
            display: inline-flex;
            flex: 0 0 42px;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .movement-qty {
            color: var(--stock-ink);
            font-size: 1.45rem;
            font-weight: 850;
            letter-spacing: -.03em;
            white-space: nowrap;
        }

        .movement-balance {
            align-items: center;
            display: grid;
            gap: .75rem;
            grid-template-columns: 1fr auto 1fr;
            margin-bottom: 1rem;
        }

        .movement-balance-box {
            border: 1px solid var(--stock-line);
            border-radius: 10px;
            padding: .85rem 1rem;
        }

        .movement-balance-box span,
        .movement-info-item span,
        .movement-stock-item span {
            color: var(--stock-muted);
            display: block;
            font-size: .68rem;
            font-weight: 700;
            margin-bottom: .25rem;
        }

        .movement-balance-box strong {
            color: var(--stock-ink);
            font-size: 1rem;
            font-weight: 800;
        }

        .movement-balance-arrow {
            align-items: center;
            background: rgba(226, 26, 26, .08);
            border-radius: 50%;
            color: var(--stock-primary);
            display: flex;
            height: 34px;
            justify-content: center;
            width: 34px;
        }

        .movement-info-grid,
        .movement-stock-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .movement-info-item,
        .movement-stock-item {
            border: 1px solid var(--stock-line);
            border-radius: 10px;
            min-width: 0;
            padding: .8rem;
        }

        .movement-info-item strong,
        .movement-stock-item strong {
            color: var(--stock-ink);
            display: block;
            font-size: .78rem;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .movement-section-label {
            color: #56616e;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .05em;
            margin: 1rem 0 .65rem;
            text-transform: uppercase;
        }

        .movement-note {
            background: #fffdf6;
            border: 1px solid #f0e9cf;
            border-radius: 10px;
            color: #5e5a4b;
            font-size: .78rem;
            line-height: 1.55;
            padding: .85rem 1rem;
        }

        .stock-detail-modal .modal-footer {
            align-items: center;
            border-top: 1px solid var(--stock-line);
            justify-content: space-between;
            padding: 1rem 1.5rem;
        }

        .movement-counter {
            color: var(--stock-muted);
            font-size: .72rem;
            font-weight: 700;
        }

        .movement-nav-actions {
            display: flex;
            gap: .5rem;
        }

        .movement-nav-actions .btn {
            border-radius: 8px;
            font-size: .78rem;
            font-weight: 800;
            min-width: 112px;
        }

        .movement-ticket-shell {
            background: #fff;
            display: grid;
            grid-template-columns: 270px minmax(0, 1fr);
            min-height: 500px;
        }

        .movement-ticket-stub {
            background:
                radial-gradient(circle at 10% 5%, rgba(255, 255, 255, .16), transparent 28%),
                linear-gradient(150deg, #c9141d, #ed2525 62%, #bb1019);
            color: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 1.6rem;
            position: relative;
            transition: background .2s ease;
        }

        .movement-ticket-stub.is-incoming {
            background:
                radial-gradient(circle at 10% 5%, rgba(255, 255, 255, .16), transparent 28%),
                linear-gradient(150deg, #087b4b, #16a36a 62%, #07683f);
        }

        .movement-ticket-stub.is-outgoing {
            background:
                radial-gradient(circle at 10% 5%, rgba(255, 255, 255, .16), transparent 28%),
                linear-gradient(150deg, #a80f18, #d71924 62%, #8f0b13);
        }

        .movement-ticket-stub::after {
            border: 28px solid rgba(255, 255, 255, .06);
            border-radius: 50%;
            content: '';
            height: 170px;
            position: absolute;
            right: -75px;
            top: -55px;
            width: 170px;
        }

        .ticket-stub-label {
            font-size: .66rem;
            font-weight: 850;
            letter-spacing: .12em;
            opacity: .82;
            text-transform: uppercase;
        }

        .ticket-stub-number {
            font-size: .74rem;
            font-weight: 800;
            margin-top: .25rem;
            opacity: .9;
        }

        .ticket-stub-center {
            align-items: center;
            display: flex;
            flex: 1;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 1;
        }

        .movement-ticket-stub .movement-type-icon {
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .18);
            color: #fff;
            font-size: 1.5rem;
            height: 64px;
            width: 64px;
        }

        .ticket-stub-qty {
            color: #fff !important;
            font-size: 2rem;
            margin-top: 1rem;
            text-shadow: 0 2px 10px rgba(75, 0, 5, .18);
        }

        .movement-ticket-stub .movement-qty.ticket-stub-qty,
        .movement-ticket-stub.is-incoming .movement-qty.ticket-stub-qty,
        .movement-ticket-stub.is-outgoing .movement-qty.ticket-stub-qty {
            color: #fff !important;
            opacity: 1 !important;
        }

        .ticket-stub-type {
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .06em;
            margin-top: .45rem;
            text-align: center;
            text-transform: uppercase;
        }

        .ticket-stub-meta {
            border-top: 1px dashed rgba(255, 255, 255, .52);
            display: grid;
            gap: .85rem;
            padding-top: 1rem;
            position: relative;
            z-index: 1;
        }

        .ticket-stub-meta span {
            display: block;
            font-size: .62rem;
            font-weight: 700;
            margin-bottom: .2rem;
            opacity: .7;
            text-transform: uppercase;
        }

        .ticket-stub-meta strong {
            display: block;
            font-size: .76rem;
            font-weight: 800;
        }

        .movement-ticket-main {
            border-left: 1px dashed #d8dde3;
            display: flex;
            flex-direction: column;
            min-width: 0;
            position: relative;
        }

        .movement-ticket-main::before,
        .movement-ticket-main::after {
            display: none;
        }

        .movement-ticket-head {
            align-items: flex-start;
            background: linear-gradient(135deg, #fff, #fffafa);
            border-bottom: 1px solid var(--stock-line);
            display: flex;
            justify-content: space-between;
            padding: 1.35rem 1.5rem;
        }

        .movement-ticket-content {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem 1.5rem;
        }

        .movement-ticket-content .movement-balance {
            margin-bottom: .85rem;
        }

        .movement-ticket-footer {
            align-items: center;
            border-top: 1px solid var(--stock-line);
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: .9rem 1.5rem;
        }

        @media (max-width: 767.98px) {
            .stock-card-actions,
            .stock-pagination {
                align-items: stretch;
                flex-direction: column;
            }

            .movement-info-grid,
            .movement-stock-grid {
                grid-template-columns: 1fr;
            }

            .stock-detail-modal .modal-footer {
                align-items: stretch;
                flex-direction: column;
            }

            .movement-nav-actions,
            .movement-nav-actions .btn {
                width: 100%;
            }

            .movement-ticket-shell {
                grid-template-columns: 1fr;
            }

            .movement-ticket-stub {
                min-height: 245px;
            }

            .movement-ticket-main {
                border-left: 0;
                border-top: 1px dashed #d8dde3;
            }

            .movement-ticket-main::before,
            .movement-ticket-main::after {
                display: none;
            }

            .movement-ticket-footer {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page stock-card-page">
    @php
        $sortLink = function (string $key) {
            $currentSort = request('sort', 'created_at');
            $currentDirection = request('direction', 'desc');
            $nextDirection = $currentSort === $key && $currentDirection === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDirection, 'page' => null]);
        };
        $sortIcon = function (string $key) {
            if (request('sort', 'created_at') !== $key) {
                return 'bi-arrow-down-up text-muted';
            }

            return request('direction', 'desc') === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
        };
        $movementDetails = $movements->getCollection()->values()->map(function ($movement) {
            $referenceLabel = $movement->reference_type && $movement->reference_id
                ? str_replace('_', ' ', $movement->reference_type).' #'.$movement->reference_id
                : '-';
            $referenceUrl = match ($movement->reference_type) {
                'atk_rtk_request', 'atk_rtk_replenishment' => $movement->reference_id ? route('ticket.show', $movement->reference_id) : null,
                'procurement_receiving' => route('bum.receivings').'#receiving-'.$movement->reference_id,
                'stock_opname' => route('bum.opnames').'#opname-'.$movement->reference_id,
                'initial_stock' => route('bum.items'),
                default => null,
            };
            $item = $movement->item;
            $isOutgoing = (int) $movement->balance_after < (int) $movement->balance_before;
            $unit = $movement->balance_uom ?: ($movement->stock_location === 'small_warehouse' ? $item?->small_uom : $item?->large_uom);
            $bigMinimum = $item ? (int) ceil(((int) $item->minimum_stock) / max(1, (int) $item->conversion_qty)) : 0;
            $needsAttention = $item && ((int) $item->small_stock <= (int) $item->minimum_stock || (int) $item->current_stock <= $bigMinimum);

            return [
                'id' => $movement->id,
                'item_code' => $item?->code ?: '-',
                'item_name' => $item?->name ?: 'Barang tidak ditemukan',
                'category' => $item?->category ?: '-',
                'item_location' => $item?->location ?: '-',
                'movement_type' => str($movement->movement_type)->replace('_', ' ')->title()->toString(),
                'warehouse' => $movement->stock_location === 'small_warehouse' ? 'Gudang Kecil' : 'Gudang Besar',
                'is_outgoing' => $isOutgoing,
                'qty' => (int) $movement->qty,
                'unit' => $unit ?: '',
                'balance_before' => (int) $movement->balance_before,
                'balance_after' => (int) $movement->balance_after,
                'big_stock' => (int) ($item?->current_stock ?? 0),
                'big_uom' => $item?->large_uom ?: '-',
                'small_stock' => (int) ($item?->small_stock ?? 0),
                'small_uom' => $item?->small_uom ?: '-',
                'minimum_stock' => (int) ($item?->minimum_stock ?? 0),
                'needs_attention' => $needsAttention,
                'reference_label' => $referenceLabel,
                'reference_url' => $referenceUrl,
                'creator' => $movement->creator?->name ?: 'Sistem',
                'created_at' => $movement->created_at->translatedFormat('d M Y, H:i'),
                'notes' => $movement->notes ?: 'Tidak ada catatan tambahan.',
                'item_url' => $item ? route('bum.items.show', $item) : null,
            ];
        })->all();
    @endphp

    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <h3 class="page-title">Stock Card</h3>
            <p class="page-subtitle mb-0">Histori mutasi masuk, keluar, adjustment, dan referensi dokumen.</p>
        </div>
        <div class="stock-card-actions">
            <a href="{{ route('bum.dashboard') }}" class="btn btn-light border stock-card-btn">Ringkasan</a>
            <a href="{{ route('bum.items') }}" class="btn btn-outline-primary stock-card-btn">Master Barang</a>
        </div>
    </div>

    <div class="stock-card-panel filter-panel mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="sort" value="{{ request('sort', 'created_at') }}">
            <input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">
            <div class="col-lg-4">
                <label class="form-label small text-muted fw-bold">Barang</label>
                <select name="item_id" class="form-select stock-item-filter" data-placeholder="Cari kode atau nama barang">
                    <option value="">Semua Barang</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" @selected(request('item_id') == $item->id)>{{ $item->code }} - {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label small text-muted fw-bold">Dari Tanggal</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="col-lg-3">
                <label class="form-label small text-muted fw-bold">Sampai Tanggal</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="col-lg-2">
                <button class="btn btn-primary stock-card-btn w-100">Filter</button>
            </div>
        </form>
    </div>

    <div class="stock-card-panel">
        <div class="table-responsive stock-table">
            <table class="table table-hover align-middle mb-0 bum-stock-card-table">
                <thead>
                    <tr>
                        <th><a href="{{ $sortLink('created_at') }}" class="text-dark text-decoration-none">Tanggal <i class="bi {{ $sortIcon('created_at') }}"></i></a></th>
                        <th><a href="{{ $sortLink('item_id') }}" class="text-dark text-decoration-none">Barang <i class="bi {{ $sortIcon('item_id') }}"></i></a></th>
                        <th><a href="{{ $sortLink('movement_type') }}" class="text-dark text-decoration-none">Tipe <i class="bi {{ $sortIcon('movement_type') }}"></i></a></th>
                        <th>Gudang</th>
                        <th class="text-end"><a href="{{ $sortLink('qty') }}" class="text-dark text-decoration-none">Qty <i class="bi {{ $sortIcon('qty') }}"></i></a></th>
                        <th class="text-end"><a href="{{ $sortLink('balance_before') }}" class="text-dark text-decoration-none">Stok Sebelum <i class="bi {{ $sortIcon('balance_before') }}"></i></a></th>
                        <th class="text-end"><a href="{{ $sortLink('balance_after') }}" class="text-dark text-decoration-none">Stok Sesudah <i class="bi {{ $sortIcon('balance_after') }}"></i></a></th>
                        <th><a href="{{ $sortLink('reference_type') }}" class="text-dark text-decoration-none">Referensi <i class="bi {{ $sortIcon('reference_type') }}"></i></a></th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $movement)
                        @php($detail = $movementDetails[$loop->index])
                        <tr class="stock-movement-row" data-stock-detail-index="{{ $loop->index }}" tabindex="0" role="button" aria-label="Lihat detail mutasi {{ $detail['item_name'] }}">
                            <td data-label="Tanggal">{{ $movement->created_at->format('d M Y H:i') }}</td>
                            <td data-label="Barang" class="fw-semibold wrap">{{ $movement->item->code ?? '-' }} - {{ $movement->item->name ?? '-' }}</td>
                            <td data-label="Tipe"><span class="badge bg-light text-dark border soft-badge">{{ $movement->movement_type }}</span></td>
                            <td data-label="Gudang">{{ $movement->stock_location === 'small_warehouse' ? 'Gudang Kecil' : 'Gudang Besar' }}</td>
                            <td data-label="Qty" class="text-end">{{ $movement->qty }} {{ $movement->balance_uom }}</td>
                            <td data-label="Stok Sebelum" class="text-end">{{ $movement->balance_before }} {{ $movement->balance_uom }}</td>
                            <td data-label="Stok Sesudah" class="text-end fw-bold">{{ $movement->balance_after }} {{ $movement->balance_uom }}</td>
                            <td data-label="Referensi">
                                @if($detail['reference_url'])
                                    <a href="{{ $detail['reference_url'] }}" class="fw-semibold text-primary text-decoration-none">{{ $detail['reference_label'] }}</a>
                                @else
                                    {{ $detail['reference_label'] }}
                                @endif
                            </td>
                            <td data-label="Catatan" class="wrap">{{ $movement->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">Belum ada mutasi stok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="stock-pagination">
            <div class="muted-text small">
                Menampilkan {{ $movements->firstItem() ?? 0 }} - {{ $movements->lastItem() ?? 0 }} dari {{ $movements->total() }} mutasi
            </div>
            {{ $movements->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<div class="modal fade stock-detail-modal" id="stockMovementDetailModal" tabindex="-1" aria-labelledby="stockMovementDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="movement-ticket-shell">
                <aside class="movement-ticket-stub">
                    <div>
                        <div class="ticket-stub-label">Stock Movement</div>
                        <div class="ticket-stub-number" id="movementTicketNumber">#-</div>
                    </div>
                    <div class="ticket-stub-center">
                        <span class="movement-type-icon" id="movementTypeIcon"><i class="bi bi-arrow-left-right"></i></span>
                        <div class="movement-qty ticket-stub-qty" id="movementQty">-</div>
                        <div class="ticket-stub-type" id="movementTypeLabel">Detail Mutasi</div>
                    </div>
                    <div class="ticket-stub-meta">
                        <div><span>Gudang</span><strong id="movementWarehouse">-</strong></div>
                        <div><span>Waktu Transaksi</span><strong id="movementDate">-</strong></div>
                    </div>
                </aside>

                <section class="movement-ticket-main">
                    <header class="movement-ticket-head">
                        <div class="pe-3">
                            <div class="movement-modal-kicker">Detail Kartu Stok</div>
                            <h2 class="movement-modal-title" id="stockMovementDetailTitle">-</h2>
                            <div class="movement-modal-subtitle" id="movementItemMeta">-</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </header>

                    <div class="movement-ticket-content">
                        <div class="movement-balance">
                            <div class="movement-balance-box">
                                <span>Stok Sebelum</span>
                                <strong id="movementBalanceBefore">-</strong>
                            </div>
                            <span class="movement-balance-arrow"><i class="bi bi-arrow-right"></i></span>
                            <div class="movement-balance-box">
                                <span>Stok Sesudah</span>
                                <strong id="movementBalanceAfter">-</strong>
                            </div>
                        </div>

                        <div class="movement-section-label">Informasi Transaksi</div>
                        <div class="movement-info-grid">
                            <div class="movement-info-item">
                                <span>Referensi</span>
                                <strong id="movementReferenceText">-</strong>
                                <a href="#" id="movementReferenceLink" class="small text-primary text-decoration-none d-none">Buka referensi <i class="bi bi-arrow-up-right"></i></a>
                            </div>
                            <div class="movement-info-item"><span>Diproses Oleh</span><strong id="movementCreator">-</strong></div>
                            <div class="movement-info-item"><span>Bin Location</span><strong id="movementLocation">-</strong></div>
                        </div>

                        <div class="movement-section-label">Kondisi Barang Saat Ini</div>
                        <div class="movement-stock-grid">
                            <div class="movement-stock-item"><span>Gudang Besar</span><strong id="movementBigStock">-</strong></div>
                            <div class="movement-stock-item"><span>Gudang Kecil</span><strong id="movementSmallStock">-</strong></div>
                            <div class="movement-stock-item"><span>Status Stok</span><strong id="movementStockStatus">-</strong></div>
                        </div>

                        <div class="movement-section-label">Catatan</div>
                        <div class="movement-note" id="movementNotes">-</div>
                    </div>

                    <footer class="movement-ticket-footer">
                        <div class="d-flex align-items-center gap-3">
                            <span class="movement-counter" id="movementCounter">-</span>
                            <a href="#" class="small fw-semibold text-primary text-decoration-none" id="movementItemLink">Detail barang <i class="bi bi-arrow-up-right"></i></a>
                        </div>
                        <div class="movement-nav-actions">
                            <button type="button" class="btn btn-light border" id="movementPrevious">
                                <i class="bi bi-arrow-left me-1"></i> Sebelumnya
                            </button>
                            <button type="button" class="btn btn-primary" id="movementNext">
                                Berikutnya <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(function () {
        $('.stock-item-filter').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: $('.stock-item-filter').data('placeholder')
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        const movementDetails = @json($movementDetails);
        const modalElement = document.getElementById('stockMovementDetailModal');
        if (!modalElement || movementDetails.length === 0) return;

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const previousButton = document.getElementById('movementPrevious');
        const nextButton = document.getElementById('movementNext');
        let currentIndex = 0;

        const text = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value ?? '-';
        };

        const renderMovement = index => {
            currentIndex = (index + movementDetails.length) % movementDetails.length;
            const movement = movementDetails[currentIndex];
            const directionSign = movement.is_outgoing ? '-' : '+';

            text('movementTicketNumber', `#SC-${String(movement.id).padStart(6, '0')}`);
            text('movementTypeLabel', movement.movement_type);
            text('stockMovementDetailTitle', movement.item_name);
            text('movementItemMeta', `${movement.item_code} · ${movement.category}`);
            text('movementWarehouse', movement.warehouse);
            text('movementDate', movement.created_at);
            text('movementQty', `${directionSign}${movement.qty} ${movement.unit}`);
            text('movementBalanceBefore', `${movement.balance_before} ${movement.unit}`);
            text('movementBalanceAfter', `${movement.balance_after} ${movement.unit}`);
            text('movementReferenceText', movement.reference_label);
            text('movementCreator', movement.creator);
            text('movementLocation', movement.item_location);
            text('movementBigStock', `${movement.big_stock} ${movement.big_uom}`);
            text('movementSmallStock', `${movement.small_stock} ${movement.small_uom}`);
            text('movementStockStatus', movement.needs_attention ? 'Perlu perhatian' : 'Stok aman');
            text('movementNotes', movement.notes);
            text('movementCounter', `${currentIndex + 1} dari ${movementDetails.length} mutasi di halaman ini`);

            const stub = document.querySelector('.movement-ticket-stub');
            stub.classList.toggle('is-outgoing', movement.is_outgoing);
            stub.classList.toggle('is-incoming', !movement.is_outgoing);

            const qty = document.getElementById('movementQty');
            qty.classList.remove('text-danger', 'text-success', 'text-warning', 'text-primary');
            qty.style.color = '#fff';

            const icon = document.querySelector('#movementTypeIcon i');
            icon.className = `bi ${movement.is_outgoing ? 'bi-box-arrow-up-right' : 'bi-box-arrow-in-down-left'}`;

            const referenceLink = document.getElementById('movementReferenceLink');
            referenceLink.classList.toggle('d-none', !movement.reference_url);
            referenceLink.href = movement.reference_url || '#';

            const itemLink = document.getElementById('movementItemLink');
            itemLink.classList.toggle('d-none', !movement.item_url);
            itemLink.href = movement.item_url || '#';
        };

        const openMovement = index => {
            renderMovement(index);
            modal.show();
        };

        document.querySelectorAll('[data-stock-detail-index]').forEach(row => {
            row.addEventListener('click', event => {
                if (event.target.closest('a, button, input, select')) return;
                openMovement(Number(row.dataset.stockDetailIndex));
            });
            row.addEventListener('keydown', event => {
                if (event.target.closest('a, button, input, select')) return;
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openMovement(Number(row.dataset.stockDetailIndex));
                }
            });
        });

        previousButton.addEventListener('click', () => renderMovement(currentIndex - 1));
        nextButton.addEventListener('click', () => renderMovement(currentIndex + 1));
        modalElement.addEventListener('keydown', event => {
            if (event.key === 'ArrowLeft') renderMovement(currentIndex - 1);
            if (event.key === 'ArrowRight') renderMovement(currentIndex + 1);
        });
    });
</script>
@endsection
