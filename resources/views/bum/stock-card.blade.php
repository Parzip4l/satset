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
            font-size: .84rem;
            font-weight: 800;
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

        .stock-table thead th {
            background: var(--stock-soft);
            border-bottom: 1px solid var(--stock-line);
            color: #394150;
            font-size: .78rem;
            font-weight: 800;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .stock-table tbody td {
            border-color: var(--stock-line);
            color: #364152;
            font-size: .84rem;
            padding: .82rem 1rem;
            vertical-align: middle;
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
            font-size: .72rem;
            font-weight: 800;
            padding: .32rem .55rem;
        }

        .select2-container--bootstrap-5 .select2-selection {
            border-color: var(--stock-line);
            border-radius: 8px;
            min-height: 42px;
        }

        @media (max-width: 767.98px) {
            .stock-card-actions,
            .stock-pagination {
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
                        @php
                            $referenceLabel = $movement->reference_type && $movement->reference_id
                                ? str_replace('_', ' ', $movement->reference_type) . ' #' . $movement->reference_id
                                : '-';
                            $referenceUrl = match ($movement->reference_type) {
                                'atk_rtk_request' => $movement->reference_id ? route('ticket.show', $movement->reference_id) : null,
                                'atk_rtk_replenishment' => $movement->reference_id ? route('ticket.show', $movement->reference_id) : null,
                                'procurement_receiving' => route('bum.receivings') . '#receiving-' . $movement->reference_id,
                                'stock_opname' => route('bum.opnames') . '#opname-' . $movement->reference_id,
                                'initial_stock' => route('bum.items'),
                                default => null,
                            };
                        @endphp
                        <tr>
                            <td data-label="Tanggal">{{ $movement->created_at->format('d M Y H:i') }}</td>
                            <td data-label="Barang" class="fw-semibold wrap">{{ $movement->item->code ?? '-' }} - {{ $movement->item->name ?? '-' }}</td>
                            <td data-label="Tipe"><span class="badge bg-light text-dark border soft-badge">{{ $movement->movement_type }}</span></td>
                            <td data-label="Gudang">{{ $movement->stock_location === 'small_warehouse' ? 'Gudang Kecil' : 'Gudang Besar' }}</td>
                            <td data-label="Qty" class="text-end">{{ $movement->qty }} {{ $movement->balance_uom }}</td>
                            <td data-label="Stok Sebelum" class="text-end">{{ $movement->balance_before }} {{ $movement->balance_uom }}</td>
                            <td data-label="Stok Sesudah" class="text-end fw-bold">{{ $movement->balance_after }} {{ $movement->balance_uom }}</td>
                            <td data-label="Referensi">
                                @if($referenceUrl)
                                    <a href="{{ $referenceUrl }}" class="fw-semibold text-primary text-decoration-none">{{ $referenceLabel }}</a>
                                @else
                                    {{ $referenceLabel }}
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
</script>
@endsection
