@extends('partials.layouts.master')

@section('title', 'Laporan GA & Inventori')
@section('css')
    @include('bum.partials.mobile-style')
    <style>
        .reports-page {
            --report-ink: #1f2933;
            --report-muted: #7b8794;
            --report-line: #e7ecf2;
            --report-soft: #f8fafc;
            --report-primary: #e21a1a;
        }

        .reports-page .page-title {
            color: var(--report-ink);
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .3rem;
        }

        .reports-page .page-subtitle,
        .reports-page .muted-text {
            color: var(--report-muted);
        }

        .report-card,
        .report-metric {
            background: #fff;
            border: 1px solid var(--report-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
        }

        .report-metric {
            align-items: center;
            display: flex;
            gap: 1rem;
            min-height: 94px;
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
            color: var(--report-muted);
            font-size: .78rem;
            font-weight: 800;
        }

        .metric-value {
            color: var(--report-ink);
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1;
            margin-top: .25rem;
        }

        .report-filter {
            padding: 1rem;
        }

        .report-section-head {
            align-items: center;
            border-bottom: 1px solid var(--report-line);
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: 1rem 1.1rem;
        }

        .report-section-title {
            color: var(--report-ink);
            font-size: .95rem;
            font-weight: 800;
        }

        .report-page-size {
            align-items: center;
            display: flex;
            gap: .5rem;
            white-space: nowrap;
        }

        .report-page-size label {
            color: var(--report-muted);
            font-size: .78rem;
            font-weight: 800;
            margin: 0;
        }

        .report-table-wrap {
            min-height: 360px;
        }

        .report-table thead th {
            background: var(--report-soft);
            border-bottom: 1px solid var(--report-line);
            color: #394150;
            cursor: pointer;
            font-size: .78rem;
            font-weight: 800;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .report-table thead th.no-sort {
            cursor: default;
        }

        .report-table tbody td {
            border-color: var(--report-line);
            color: #364152;
            font-size: .84rem;
            padding: .82rem 1rem;
            vertical-align: middle;
        }

        .report-pagination {
            align-items: center;
            border-top: 1px solid var(--report-line);
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: .85rem 1rem;
        }

        .report-pagination-info {
            color: var(--report-muted);
            font-size: .78rem;
            font-weight: 700;
        }

        .report-pager {
            align-items: center;
            display: flex;
            gap: .35rem;
        }

        .report-pager .btn,
        .report-btn {
            border-radius: 8px;
            font-size: .82rem;
            font-weight: 800;
            min-height: 36px;
            padding: .42rem .72rem;
        }

        .soft-badge {
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 800;
            padding: .32rem .55rem;
        }

        @media (max-width: 767.98px) {
            .report-section-head,
            .report-pagination {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page reports-page">
    @php
        $usageTotal = $usage->sum(fn ($rows) => $rows->sum('qty'));
        $receivedTotal = $receivings->flatMap(fn ($receiving) => $receiving->items)->sum('qty_received');
        $consumptionParticipants = $consumptions->sum(fn ($ticket) => (int) data_get($ticket->payload, 'participant_count', 0));
    @endphp

    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <h3 class="page-title">Laporan GA & Inventori</h3>
            <p class="page-subtitle mb-0">Ringkasan pemakaian ATK/RTK dari Gudang Kecil, penerimaan barang ke Gudang Besar, dan konsumsi rapat per periode.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('bum.dashboard') }}" class="btn btn-light border report-btn">Ringkasan</a>
            <a href="{{ route('bum.analytics') }}" class="btn btn-outline-primary report-btn">Analytics & Forecast</a>
        </div>
    </div>

    <div class="report-card report-filter mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4 col-lg-3">
                <label class="form-label small text-muted fw-bold">Periode Laporan</label>
                <input type="month" name="period" value="{{ $period }}" class="form-control">
            </div>
            <div class="col-md-3 col-lg-2">
                <button class="btn btn-primary report-btn w-100">Terapkan</button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Qty Keluar Gudang Kecil', 'value' => number_format($usageTotal), 'icon' => 'bi-box-arrow-up', 'color' => 'primary'],
            ['label' => 'Qty Masuk Gudang Besar', 'value' => number_format($receivedTotal), 'icon' => 'bi-truck', 'color' => 'success'],
            ['label' => 'Request Konsumsi', 'value' => number_format($consumptions->count()), 'icon' => 'bi-cup-hot', 'color' => 'info'],
            ['label' => 'Total Peserta Rapat', 'value' => number_format($consumptionParticipants), 'icon' => 'bi-people', 'color' => 'warning'],
        ] as $metric)
            <div class="col-xl-3 col-md-6">
                <div class="report-metric">
                    <div class="metric-icon bg-{{ $metric['color'] }} bg-opacity-10 text-{{ $metric['color'] }}">
                        <i class="bi {{ $metric['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="metric-label">{{ $metric['label'] }}</div>
                        <div class="metric-value">{{ $metric['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="report-card h-100" data-report-table="usage">
                <div class="report-section-head">
                    <div>
                        <div class="report-section-title">Pemakaian ATK/RTK Gudang Kecil</div>
                        <div class="muted-text small" data-report-summary>Memuat data...</div>
                    </div>
                    <div class="report-page-size">
                        <label>Tampil</label>
                        <select class="form-select form-select-sm" data-report-page-size>
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="15">15</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive report-table-wrap">
                    <table class="table align-middle mb-0 report-table">
                        <thead>
                            <tr>
                                <th data-sort-key="name" data-sort-type="text">Barang <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-sort-key="qty" data-sort-type="number" class="text-end">Qty Keluar <i class="bi bi-arrow-down-up text-muted"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($usage as $rows)
                                @php($first = $rows->first())
                                <tr data-name="{{ $first->item->name ?? '-' }}" data-qty="{{ $rows->sum('qty') }}">
                                    <td class="wrap fw-semibold">{{ $first->item->code ?? '-' }} - {{ $first->item->name ?? '-' }}</td>
                                    <td class="text-end fw-bold">{{ number_format($rows->sum('qty')) }} {{ $first->item->small_uom ?? '' }}</td>
                                </tr>
                            @empty
                                <tr data-empty-row><td colspan="2" class="text-center text-muted py-4">Belum ada pemakaian.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="report-pagination">
                    <div class="report-pagination-info" data-report-info>-</div>
                    <div class="report-pager" data-report-pager></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="report-card h-100" data-report-table="receivings">
                <div class="report-section-head">
                    <div>
                        <div class="report-section-title">Penerimaan Barang</div>
                        <div class="muted-text small" data-report-summary>Memuat data...</div>
                    </div>
                    <div class="report-page-size">
                        <label>Tampil</label>
                        <select class="form-select form-select-sm" data-report-page-size>
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="15">15</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive report-table-wrap">
                    <table class="table align-middle mb-0 report-table">
                        <thead>
                            <tr>
                                <th data-sort-key="reference" data-sort-type="text">Dokumen <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-sort-key="vendor" data-sort-type="text">Vendor <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-sort-key="date" data-sort-type="text">Tanggal <i class="bi bi-arrow-down-up text-muted"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receivings as $receiving)
                                <tr data-reference="{{ $receiving->reference_number }}" data-vendor="{{ $receiving->vendor_name ?? '-' }}" data-date="{{ optional($receiving->received_date)->format('Y-m-d') ?? '' }}">
                                    <td>
                                        <div class="fw-semibold">{{ $receiving->reference_number }}</div>
                                        <span class="badge bg-light text-dark border soft-badge">{{ $receiving->status }}</span>
                                    </td>
                                    <td class="wrap">{{ $receiving->vendor_name ?? '-' }}</td>
                                    <td>{{ optional($receiving->received_date)->format('d M Y') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr data-empty-row><td colspan="3" class="text-center text-muted py-4">Belum ada penerimaan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="report-pagination">
                    <div class="report-pagination-info" data-report-info>-</div>
                    <div class="report-pager" data-report-pager></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="report-card h-100" data-report-table="consumptions">
                <div class="report-section-head">
                    <div>
                        <div class="report-section-title">Konsumsi Rapat</div>
                        <div class="muted-text small" data-report-summary>Memuat data...</div>
                    </div>
                    <div class="report-page-size">
                        <label>Tampil</label>
                        <select class="form-select form-select-sm" data-report-page-size>
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="15">15</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive report-table-wrap">
                    <table class="table align-middle mb-0 report-table">
                        <thead>
                            <tr>
                                <th data-sort-key="activity" data-sort-type="text">Kegiatan <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-sort-key="participants" data-sort-type="number" class="text-end">Peserta <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-sort-key="status" data-sort-type="text">Status <i class="bi bi-arrow-down-up text-muted"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($consumptions as $ticket)
                                <tr data-activity="{{ data_get($ticket->payload, 'activity_name', $ticket->title) }}" data-participants="{{ (int) data_get($ticket->payload, 'participant_count', 0) }}" data-status="{{ data_get($ticket->payload, 'workflow_status', '-') }}">
                                    <td class="wrap">
                                        <a href="{{ route('ticket.show', $ticket) }}" class="fw-semibold text-decoration-none">{{ data_get($ticket->payload, 'activity_name', $ticket->title) }}</a>
                                    </td>
                                    <td class="text-end fw-bold">{{ number_format((int) data_get($ticket->payload, 'participant_count', 0)) }}</td>
                                    <td><span class="badge bg-light text-dark border soft-badge">{{ data_get($ticket->payload, 'workflow_status', '-') }}</span></td>
                                </tr>
                            @empty
                                <tr data-empty-row><td colspan="3" class="text-center text-muted py-4">Belum ada request konsumsi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="report-pagination">
                    <div class="report-pagination-info" data-report-info>-</div>
                    <div class="report-pager" data-report-pager></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-report-table]').forEach((card) => {
            const tbody = card.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr:not([data-empty-row])'));
            const emptyRow = tbody.querySelector('[data-empty-row]');
            const info = card.querySelector('[data-report-info]');
            const pager = card.querySelector('[data-report-pager]');
            const summary = card.querySelector('[data-report-summary]');
            const pageSizeSelect = card.querySelector('[data-report-page-size]');
            const state = { page: 1, pageSize: Number(pageSizeSelect.value), sortKey: null, direction: 'asc' };

            function sortedRows() {
                if (!state.sortKey) return rows;

                return [...rows].sort((a, b) => {
                    const type = card.querySelector(`[data-sort-key="${state.sortKey}"]`)?.dataset.sortType || 'text';
                    const aValue = a.dataset[state.sortKey] || '';
                    const bValue = b.dataset[state.sortKey] || '';
                    const result = type === 'number'
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
                const visible = currentRows.slice(start, start + state.pageSize);

                tbody.innerHTML = '';
                if (!total && emptyRow) {
                    tbody.appendChild(emptyRow);
                    info.textContent = 'Tidak ada data untuk ditampilkan';
                    summary.textContent = '0 data';
                    pager.innerHTML = '';
                    return;
                }

                visible.forEach(row => tbody.appendChild(row));
                const from = start + 1;
                const to = Math.min(start + state.pageSize, total);
                info.textContent = `Menampilkan ${from} - ${to} dari ${total} data`;
                summary.textContent = `${total} data pada periode ini`;

                const pages = Array.from({ length: totalPages }, (_, index) => index + 1)
                    .filter(page => totalPages <= 5 || page === 1 || page === totalPages || Math.abs(page - state.page) <= 1);
                pager.innerHTML = `
                    <button type="button" class="btn btn-light border" data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>
                    ${pages.map((page, index) => {
                        const previous = pages[index - 1];
                        const gap = previous && page - previous > 1 ? '<span class="px-2 text-muted fw-bold">...</span>' : '';
                        return `${gap}<button type="button" class="btn ${page === state.page ? 'btn-primary' : 'btn-light border'}" data-page="${page}">${page}</button>`;
                    }).join('')}
                    <button type="button" class="btn btn-light border" data-page="${state.page + 1}" ${state.page >= totalPages ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>
                `;
            }

            pageSizeSelect.addEventListener('change', () => {
                state.pageSize = Number(pageSizeSelect.value);
                state.page = 1;
                render();
            });

            pager.addEventListener('click', (event) => {
                const button = event.target.closest('[data-page]');
                if (!button || button.disabled) return;
                state.page = Number(button.dataset.page);
                render();
            });

            card.querySelectorAll('[data-sort-key]').forEach((header) => {
                header.addEventListener('click', () => {
                    const nextDirection = state.sortKey === header.dataset.sortKey && state.direction === 'asc' ? 'desc' : 'asc';
                    state.sortKey = header.dataset.sortKey;
                    state.direction = nextDirection;
                    state.page = 1;
                    card.querySelectorAll('[data-sort-key] i').forEach(icon => icon.className = 'bi bi-arrow-down-up text-muted');
                    header.querySelector('i').className = `bi ${state.direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down'}`;
                    render();
                });
            });

            render();
        });
    });
</script>
@endsection
