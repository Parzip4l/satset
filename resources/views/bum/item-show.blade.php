@extends('partials.layouts.master')

@section('title', 'Detail Barang')
@section('css')
    @include('bum.partials.mobile-style')
    <style>
        .item-detail {
            --detail-ink: #1f2933;
            --detail-muted: #7b8794;
            --detail-line: #e7ecf2;
            --detail-soft: #f8fafc;
            --detail-primary: #e21a1a;
        }

        .detail-title {
            color: var(--detail-ink);
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: .3rem;
        }

        .detail-card,
        .metric-card {
            background: #fff;
            border: 1px solid var(--detail-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
        }

        .metric-card {
            min-height: 92px;
            padding: 1rem;
        }

        .metric-label {
            color: var(--detail-muted);
            font-size: .78rem;
            font-weight: 800;
        }

        .metric-value {
            color: var(--detail-ink);
            font-size: 1.35rem;
            font-weight: 800;
            margin-top: .25rem;
        }

        .detail-section-head {
            border-bottom: 1px solid var(--detail-line);
            padding: 1rem 1.1rem;
        }

        .detail-section-title {
            color: var(--detail-ink);
            font-size: .95rem;
            font-weight: 800;
        }

        .detail-table thead th {
            background: var(--detail-soft);
            border-bottom: 1px solid var(--detail-line);
            color: #394150;
            font-size: .78rem;
            font-weight: 800;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .detail-table tbody td {
            border-color: var(--detail-line);
            color: #364152;
            font-size: .84rem;
            padding: .82rem 1rem;
        }

        .trend-row {
            display: grid;
            grid-template-columns: 80px minmax(0, 1fr) 72px 72px;
            gap: .75rem;
            align-items: center;
            color: #52606d;
            font-size: .82rem;
            margin-bottom: .75rem;
        }

        .trend-meter {
            background: #eef2f7;
            border-radius: 999px;
            height: 7px;
            overflow: hidden;
        }

        .trend-meter span {
            display: block;
            height: 100%;
            border-radius: inherit;
        }

        .soft-badge {
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 800;
            padding: .32rem .55rem;
        }

        .adjust-modal .modal-dialog {
            max-width: min(760px, calc(100vw - 2rem));
        }

        .adjust-modal .modal-content {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
        }

        .adjust-modal .modal-header,
        .adjust-modal .modal-footer {
            border-color: var(--detail-line);
            padding: 1.1rem 1.35rem;
        }

        .stock-note {
            background: var(--detail-soft);
            border: 1px solid var(--detail-line);
            border-radius: 8px;
            padding: .9rem 1rem;
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page item-detail">
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

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-3">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <div class="text-muted small fw-bold mb-1">{{ $item->code }} | {{ $item->category }}</div>
            <h3 class="detail-title">{{ $item->name }}</h3>
            <p class="text-muted mb-0">{{ $item->unit }} | Rp{{ number_format($item->unit_price, 0, ',', '.') }} | Binloc {{ $item->location ?? '-' }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('bum.items') }}" class="btn btn-light border">Kembali</a>
            <a href="{{ route('bum.stock-card', ['item_id' => $item->id]) }}" class="btn btn-outline-primary">Stock Card</a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                <i class="bi bi-arrow-left-right me-1"></i> Tambah/Kurangi Stok
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Stok Saat Ini', 'value' => number_format($item->current_stock), 'hint' => $summary['stock_status']],
            ['label' => 'Minimum / Buffer', 'value' => number_format($item->minimum_stock) . ' / ' . number_format($item->buffer_stock), 'hint' => 'Batas kontrol'],
            ['label' => 'Total Masuk', 'value' => number_format($summary['incoming_total']), 'hint' => 'Semua pergerakan'],
            ['label' => 'Total Keluar', 'value' => number_format($summary['outgoing_total']), 'hint' => 'Semua pergerakan'],
        ] as $metric)
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-label">{{ $metric['label'] }}</div>
                    <div class="metric-value">{{ $metric['value'] }}</div>
                    <div class="text-muted small mt-1">{{ $metric['hint'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="detail-card h-100">
                <div class="detail-section-head">
                    <div class="detail-section-title">Analytics 6 Bulan</div>
                </div>
                <div class="p-3">
                    @php
                        $maxTrend = max(1, $monthlyTrend->max(fn ($row) => max($row['incoming'], $row['outgoing'])));
                    @endphp
                    @foreach($monthlyTrend as $row)
                        <div class="trend-row">
                            <strong>{{ $row['period'] }}</strong>
                            <div>
                                <div class="trend-meter mb-1"><span style="width: {{ round(($row['incoming'] / $maxTrend) * 100) }}%; background:#22c55e;"></span></div>
                                <div class="trend-meter"><span style="width: {{ round(($row['outgoing'] / $maxTrend) * 100) }}%; background:#ef476f;"></span></div>
                            </div>
                            <span class="text-end text-success fw-bold">+{{ number_format($row['incoming']) }}</span>
                            <span class="text-end text-danger fw-bold">-{{ number_format($row['outgoing']) }}</span>
                        </div>
                    @endforeach
                    <div class="d-flex gap-3 text-muted small mt-3">
                        <span><span class="badge bg-success me-1">&nbsp;</span>Masuk</span>
                        <span><span class="badge bg-danger me-1">&nbsp;</span>Keluar</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="detail-card h-100">
                <div class="detail-section-head d-flex justify-content-between align-items-center">
                    <div class="detail-section-title">Riwayat Stock Card</div>
                    <span class="badge bg-light text-dark border soft-badge">{{ $item->movements_count }} movement</span>
                </div>
                <div class="table-responsive detail-table">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th><a href="{{ $sortLink('created_at') }}" class="text-dark text-decoration-none">Tanggal <i class="bi {{ $sortIcon('created_at') }}"></i></a></th>
                                <th><a href="{{ $sortLink('movement_type') }}" class="text-dark text-decoration-none">Tipe <i class="bi {{ $sortIcon('movement_type') }}"></i></a></th>
                                <th class="text-end"><a href="{{ $sortLink('qty') }}" class="text-dark text-decoration-none">Qty <i class="bi {{ $sortIcon('qty') }}"></i></a></th>
                                <th class="text-end"><a href="{{ $sortLink('balance_after') }}" class="text-dark text-decoration-none">Saldo <i class="bi {{ $sortIcon('balance_after') }}"></i></a></th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                @php($isOutgoing = $movement->balance_after < $movement->balance_before)
                                <tr>
                                    <td>{{ $movement->created_at->format('d M Y H:i') }}</td>
                                    <td><span class="badge bg-light text-dark border soft-badge">{{ $movement->movement_type }}</span></td>
                                    <td class="text-end fw-bold {{ $isOutgoing ? 'text-danger' : 'text-success' }}">
                                        {{ $isOutgoing ? '-' : '+' }}{{ $movement->qty }}
                                    </td>
                                    <td class="text-end">{{ $movement->balance_after }}</td>
                                    <td class="wrap">{{ $movement->notes ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada stock movement.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top">
                    {{ $movements->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>

    <div class="detail-card mt-4">
        <div class="detail-section-head">
            <div class="detail-section-title">Penerimaan Terakhir</div>
        </div>
        <div class="table-responsive detail-table">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>PO</th>
                        <th>Status</th>
                        <th class="text-end">Order</th>
                        <th class="text-end">Terima</th>
                        <th class="text-end">Tolak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receivingLines as $line)
                        <tr>
                            <td>{{ $line->receiving->reference_number ?? '-' }}</td>
                            <td>{{ $line->receiving->po_number ?? '-' }}</td>
                            <td><span class="badge bg-light text-dark border soft-badge">{{ $line->receiving->status ?? '-' }}</span></td>
                            <td class="text-end">{{ $line->qty_ordered }}</td>
                            <td class="text-end">{{ $line->qty_received }}</td>
                            <td class="text-end">{{ $line->qty_rejected }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada penerimaan untuk barang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade adjust-modal" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('bum.items.stock-adjustment', $item) }}">
                @csrf
                <div class="modal-header align-items-start">
                    <div>
                        <h5 class="modal-title fw-bold" id="adjustStockModalLabel">Tambah/Kurangi Stok</h5>
                        <div class="text-muted small">Gunakan untuk koreksi manual, barang keluar, atau penyesuaian kecil.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="stock-note mb-3">
                        <div class="text-muted small fw-bold mb-1">{{ $item->code }}</div>
                        <div class="fw-bold">{{ $item->name }}</div>
                        <div class="text-muted small mt-1">Stok saat ini: <strong class="text-dark">{{ number_format($item->current_stock) }}</strong> {{ $item->unit }}</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Mutasi</label>
                            <select name="direction" class="form-select" required>
                                <option value="in" @selected(old('direction') === 'in')>Tambah stok</option>
                                <option value="out" @selected(old('direction') === 'out')>Kurangi stok</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Qty</label>
                            <input name="qty" type="number" min="1" class="form-control" value="{{ old('qty', 1) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: koreksi stok fisik / pemakaian urgent / retur barang">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex flex-column flex-md-row gap-2">
                    <button class="btn btn-primary flex-fill">Simpan Mutasi Stok</button>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if($errors->any() || session('error'))
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adjustStockModal')).show();
        @endif
    });
</script>
@endsection
