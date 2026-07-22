@extends('partials.layouts.master')

@section('title', 'Gudang ATK/RTK')
@section('pagetitle', 'Gudang ATK/RTK')
@section('title-sub', 'Antrian Permintaan')

@section('css')
<style>
    .warehouse-page {
        --warehouse-ink: #1f2933;
        --warehouse-muted: #7b8794;
        --warehouse-line: #e7ecf2;
        --warehouse-soft: #f8fafc;
        --warehouse-primary: #e21a1a;
    }

    .warehouse-page .page-title {
        color: var(--warehouse-ink);
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: .3rem;
    }

    .warehouse-page .page-subtitle,
    .warehouse-page .muted-text {
        color: var(--warehouse-muted);
    }

    .warehouse-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        justify-content: flex-end;
    }

    .warehouse-btn {
        border-radius: 8px;
        font-size: .84rem;
        font-weight: 800;
        min-height: 36px;
        padding: .45rem .85rem;
    }

    .metric-card,
    .warehouse-card {
        background: #fff;
        border: 1px solid var(--warehouse-line);
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
    }

    .metric-card {
        align-items: center;
        display: flex;
        gap: 1rem;
        min-height: 96px;
        padding: 1rem;
    }

    .metric-icon {
        align-items: center;
        border-radius: 8px;
        display: flex;
        flex: 0 0 48px;
        height: 48px;
        justify-content: center;
        width: 48px;
    }

    .metric-label {
        color: var(--warehouse-muted);
        font-size: .78rem;
        font-weight: 800;
    }

    .metric-value {
        color: var(--warehouse-ink);
        font-size: 1.35rem;
        font-weight: 800;
        line-height: 1;
        margin-top: .25rem;
    }

    .warehouse-section-head {
        align-items: center;
        border-bottom: 1px solid var(--warehouse-line);
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding: 1rem 1.1rem;
    }

    .warehouse-section-title {
        color: var(--warehouse-ink);
        font-size: .95rem;
        font-weight: 800;
    }

    .warehouse-table thead th {
        background: var(--warehouse-soft);
        border-bottom: 1px solid var(--warehouse-line);
        color: #394150;
        font-size: .78rem;
        font-weight: 800;
        padding: .85rem 1rem;
        white-space: nowrap;
    }

    .warehouse-table tbody td {
        border-color: var(--warehouse-line);
        color: #364152;
        font-size: .84rem;
        padding: .82rem 1rem;
        vertical-align: middle;
    }

    .stock-tools {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: .6rem;
        justify-content: flex-end;
    }

    .stock-tools .form-control,
    .stock-tools .form-select {
        border-color: var(--warehouse-line);
        border-radius: 8px;
        min-height: 36px;
    }

    .stock-tools .stock-search {
        min-width: min(320px, 100%);
    }

    .sortable-head {
        cursor: pointer;
        user-select: none;
    }

    .soft-badge {
        border-radius: 6px;
        font-size: .72rem;
        font-weight: 800;
        padding: .32rem .55rem;
    }

    .warehouse-pagination {
        align-items: center;
        border-top: 1px solid var(--warehouse-line);
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: .9rem 1rem;
    }

    .warehouse-pagination .pagination {
        margin-bottom: 0;
    }

    .warehouse-pagination .page-link {
        border-radius: 6px;
        color: #596575;
        font-size: .78rem;
        font-weight: 800;
        margin: 0 .1rem;
        min-width: 32px;
        text-align: center;
    }

    .warehouse-pagination .active > .page-link {
        background: var(--warehouse-primary);
        border-color: var(--warehouse-primary);
        color: #fff;
    }

    @media (max-width: 767.98px) {
        .warehouse-actions,
        .warehouse-pagination {
            align-items: stretch;
            flex-direction: column;
        }

        .stock-tools {
            align-items: stretch;
            flex-direction: column;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid pb-5 warehouse-page">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-3">{{ session('error') }}</div>
    @endif

    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <h3 class="page-title">Gudang ATK/RTK</h3>
            <p class="page-subtitle mb-0">Kelola antrian permintaan ATK/RTK, review stok, pengadaan, dan serah-terima barang.</p>
        </div>
        <div class="warehouse-actions">
            <a href="{{ route('ticket.atk-rtk.create') }}" class="btn btn-light border warehouse-btn">Buat Request</a>
            <a href="{{ route('bum.items') }}" class="btn btn-outline-primary warehouse-btn">Master Barang</a>
            <a href="{{ route('bum.stock-card') }}" class="btn btn-primary warehouse-btn">Stock Card</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Menunggu Review', 'value' => $stats['waiting_review'], 'icon' => 'bi-clipboard-check', 'color' => 'warning'],
            ['label' => 'Perlu Pengadaan', 'value' => $stats['waiting_procurement'], 'icon' => 'bi-cart-plus', 'color' => 'info'],
            ['label' => 'Siap Serah Terima', 'value' => $stats['ready_to_handover'], 'icon' => 'bi-box-arrow-up', 'color' => 'success'],
            ['label' => 'Sudah Diserahkan', 'value' => $stats['handed_over'], 'icon' => 'bi-check2-circle', 'color' => 'primary'],
        ] as $stat)
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-{{ $stat['color'] }} bg-opacity-10 text-{{ $stat['color'] }}">
                        <i class="bi {{ $stat['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="metric-label">{{ $stat['label'] }}</div>
                        <div class="metric-value">{{ number_format($stat['value']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="warehouse-card mb-4">
        <div class="warehouse-section-head">
            <div>
                <div class="warehouse-section-title">Antrian Permintaan ATK/RTK</div>
                <div class="muted-text small">Klik proses untuk review stok, update status, atau handover barang.</div>
            </div>
        </div>
        <div class="table-responsive warehouse-table">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>No Ticket</th>
                        <th>Requester</th>
                        <th>Barang</th>
                        <th class="text-end">Qty</th>
                        <th>Status</th>
                        <th>Butuh</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeTickets as $ticket)
                        @php
                            $payload = $ticket->payload ?? [];
                            $workflow = data_get($payload, 'workflow_status', '-');
                            $itemSummary = collect(data_get($payload, 'items', []))
                                ->map(fn ($item) => data_get($item, 'item_name') . ' x ' . data_get($item, 'quantity'))
                                ->filter()
                                ->implode(', ');
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('ticket.show', $ticket) }}" class="fw-semibold text-decoration-none">{{ $ticket->ticket_no }}</a>
                                <div class="muted-text small">{{ $ticket->created_at->format('d M Y') }}</div>
                            </td>
                            <td>{{ $ticket->requester->name ?? '-' }}</td>
                            <td class="wrap">
                                <div class="fw-semibold">{{ data_get($payload, 'request_subject', data_get($payload, 'item_type', '-')) }}</div>
                                <div class="muted-text small">{{ $itemSummary ?: data_get($payload, 'delivery_location', '-') }}</div>
                            </td>
                            <td class="text-end fw-bold">{{ number_format((int) data_get($payload, 'total_quantity', data_get($payload, 'quantity', 0))) }}</td>
                            <td><span class="badge bg-light text-dark border soft-badge">{{ $workflow }}</span></td>
                            <td>{{ data_get($payload, 'needed_date') ?: '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('ticket.show', $ticket) }}" class="btn btn-sm btn-primary warehouse-btn">Proses</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Tidak ada antrian ATK/RTK aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="warehouse-pagination">
            <div class="muted-text small">
                Menampilkan {{ $activeTickets->firstItem() ?? 0 }} - {{ $activeTickets->lastItem() ?? 0 }} dari {{ $activeTickets->total() }} request
            </div>
            {{ $activeTickets->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <div class="warehouse-card">
        <div class="warehouse-section-head">
            <div>
                <div class="warehouse-section-title">Stok Minimum Gudang Kecil</div>
                <div class="muted-text small">Item aktif di Gudang Kecil yang perlu dicek sebelum handover atau transfer dari Gudang Besar.</div>
            </div>
            <div class="stock-tools">
                <input id="lowStockSearch" class="form-control stock-search" placeholder="Cari kode, nama, kategori, lokasi">
                <select id="lowStockPageSize" class="form-select" style="width: 92px;">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                </select>
                <a href="{{ route('bum.analytics') }}" class="btn btn-outline-primary warehouse-btn">Forecast Pengadaan</a>
            </div>
        </div>
        <div class="table-responsive warehouse-table">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="sortable-head" data-low-stock-sort="name" data-sort-type="text" style="min-width: 360px;">Barang <i class="bi bi-arrow-down-up text-muted"></i></th>
                        <th class="sortable-head" data-low-stock-sort="category" data-sort-type="text">Kategori <i class="bi bi-arrow-down-up text-muted"></i></th>
                        <th class="sortable-head" data-low-stock-sort="location" data-sort-type="text">Lokasi <i class="bi bi-arrow-down-up text-muted"></i></th>
                        <th class="sortable-head text-end" data-low-stock-sort="stock" data-sort-type="number">Gudang Kecil <i class="bi bi-arrow-down-up text-muted"></i></th>
                        <th class="sortable-head text-end" data-low-stock-sort="minimum" data-sort-type="number">Minimum <i class="bi bi-arrow-down-up text-muted"></i></th>
                        <th class="sortable-head text-end" data-low-stock-sort="gap" data-sort-type="number">Selisih <i class="bi bi-arrow-down-up text-muted"></i></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="lowStockTableBody">
                    @forelse($lowStockItems as $item)
                        <tr data-low-stock-row
                            data-code="{{ $item->code }}"
                            data-name="{{ $item->name }}"
                            data-category="{{ $item->category }}"
                            data-location="{{ $item->location ?: '-' }}"
                            data-stock="{{ (int) $item->small_stock }}"
                            data-minimum="{{ (int) $item->minimum_stock }}"
                            data-gap="{{ max(0, (int) $item->minimum_stock - (int) $item->small_stock) }}">
                            <td class="wrap">
                                <div class="fw-semibold">{{ $item->code }} - {{ $item->name }}</div>
                                <div class="muted-text small">GB {{ number_format($item->current_stock) }} {{ $item->large_uom }} | Rp{{ number_format($item->unit_price, 0, ',', '.') }}/{{ $item->small_uom }}</div>
                            </td>
                            <td><span class="badge bg-light text-dark border soft-badge">{{ $item->category }}</span></td>
                            <td>{{ $item->location ?: '-' }}</td>
                            <td class="text-end fw-bold text-danger">{{ number_format($item->small_stock) }} {{ $item->small_uom }}</td>
                            <td class="text-end">{{ number_format($item->minimum_stock) }} {{ $item->small_uom }}</td>
                            <td class="text-end">{{ number_format(max(0, $item->minimum_stock - $item->small_stock)) }} {{ $item->small_uom }}</td>
                            <td class="text-end">
                                <a href="{{ route('bum.items.show', $item) }}" class="btn btn-sm btn-light border warehouse-btn">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr data-low-stock-empty>
                            <td colspan="7" class="text-center text-muted py-4">Tidak ada item di bawah minimum stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="warehouse-pagination">
            <div class="muted-text small" id="lowStockInfo">-</div>
            <div class="d-flex flex-wrap gap-1" id="lowStockPager"></div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tbody = document.getElementById('lowStockTableBody');
        const rows = Array.from(document.querySelectorAll('[data-low-stock-row]'));
        const emptyRow = document.querySelector('[data-low-stock-empty]');
        const searchInput = document.getElementById('lowStockSearch');
        const pageSizeSelect = document.getElementById('lowStockPageSize');
        const info = document.getElementById('lowStockInfo');
        const pager = document.getElementById('lowStockPager');
        const state = { page: 1, pageSize: Number(pageSizeSelect.value), sortKey: 'gap', sortType: 'number', direction: 'desc', search: '' };

        function rowMatches(row) {
            if (!state.search) return true;
            const text = [
                row.dataset.code,
                row.dataset.name,
                row.dataset.category,
                row.dataset.location
            ].join(' ').toLowerCase();

            return text.includes(state.search);
        }

        function sortedRows() {
            return rows
                .filter(rowMatches)
                .sort((a, b) => {
                    const aValue = a.dataset[state.sortKey] || '';
                    const bValue = b.dataset[state.sortKey] || '';
                    const result = state.sortType === 'number'
                        ? Number(aValue) - Number(bValue)
                        : String(aValue).localeCompare(String(bValue), 'id', { numeric: true, sensitivity: 'base' });

                    return state.direction === 'asc' ? result : -result;
                });
        }

        function render() {
            const currentRows = sortedRows();
            const total = currentRows.length;
            const totalPages = Math.max(1, Math.ceil(total / state.pageSize));
            state.page = Math.min(Math.max(1, state.page), totalPages);
            const start = (state.page - 1) * state.pageSize;
            const visibleRows = currentRows.slice(start, start + state.pageSize);

            tbody.innerHTML = '';
            if (!total) {
                if (emptyRow) tbody.appendChild(emptyRow);
                info.textContent = 'Tidak ada stok minimum sesuai pencarian.';
                pager.innerHTML = '';
                return;
            }

            visibleRows.forEach(row => tbody.appendChild(row));
            const from = start + 1;
            const to = Math.min(start + state.pageSize, total);
            info.textContent = `Menampilkan ${from} - ${to} dari ${total} item`;

            const pages = Array.from({ length: totalPages }, (_, index) => index + 1)
                .filter(page => totalPages <= 5 || page === 1 || page === totalPages || Math.abs(page - state.page) <= 1);

            pager.innerHTML = `
                <button type="button" class="btn btn-sm btn-light border warehouse-btn" data-low-stock-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>
                ${pages.map((page, index) => {
                    const previous = pages[index - 1];
                    const gap = previous && page - previous > 1 ? '<span class="px-2 text-muted fw-bold">...</span>' : '';
                    return `${gap}<button type="button" class="btn btn-sm ${page === state.page ? 'btn-primary' : 'btn-light border'} warehouse-btn" data-low-stock-page="${page}">${page}</button>`;
                }).join('')}
                <button type="button" class="btn btn-sm btn-light border warehouse-btn" data-low-stock-page="${state.page + 1}" ${state.page >= totalPages ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>
            `;
        }

        searchInput.addEventListener('input', function () {
            state.search = this.value.trim().toLowerCase();
            state.page = 1;
            render();
        });

        pageSizeSelect.addEventListener('change', function () {
            state.pageSize = Number(this.value);
            state.page = 1;
            render();
        });

        pager.addEventListener('click', function (event) {
            const button = event.target.closest('[data-low-stock-page]');
            if (!button || button.disabled) return;
            state.page = Number(button.dataset.lowStockPage);
            render();
        });

        document.querySelectorAll('[data-low-stock-sort]').forEach(header => {
            header.addEventListener('click', function () {
                state.direction = state.sortKey === this.dataset.lowStockSort && state.direction === 'asc' ? 'desc' : 'asc';
                state.sortKey = this.dataset.lowStockSort;
                state.sortType = this.dataset.sortType || 'text';
                state.page = 1;

                document.querySelectorAll('[data-low-stock-sort] i').forEach(icon => icon.className = 'bi bi-arrow-down-up text-muted');
                this.querySelector('i').className = `bi ${state.direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down'}`;
                render();
            });
        });

        render();
    });
</script>
@endsection
