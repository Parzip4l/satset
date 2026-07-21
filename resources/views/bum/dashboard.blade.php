@extends('partials.layouts.master')

@section('title', 'Ringkasan GA & Inventori')
@section('css')
    @include('bum.partials.mobile-style')
    <style>
        .bum-dashboard {
            --bum-ink: #1f2933;
            --bum-muted: #7b8794;
            --bum-line: #e7ecf2;
            --bum-soft: #f7f9fc;
            --bum-orange: #e21a1a;
            --bum-blue: #0ea5c6;
            --bum-green: #22c55e;
            --bum-yellow: #f6b51e;
            --bum-red: #ef476f;
        }

        .bum-dashboard .page-title {
            color: var(--bum-ink);
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .3rem;
        }

        .bum-dashboard .page-subtitle,
        .bum-dashboard .section-subtitle,
        .bum-dashboard .muted-text {
            color: var(--bum-muted);
        }

        .bum-dashboard .section-card,
        .bum-dashboard .metric-card {
            background: #fff;
            border: 1px solid var(--bum-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
        }

        .bum-dashboard .metric-card {
            min-height: 92px;
            padding: 1rem 1.1rem;
            display: flex;
            align-items: center;
            gap: .9rem;
        }

        .metric-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 42px;
            font-size: 1.1rem;
        }

        .metric-label {
            color: var(--bum-muted);
            font-size: .78rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .metric-value {
            color: var(--bum-ink);
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.1;
            margin-top: .2rem;
        }

        .action-bar {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .dashboard-btn {
            min-height: 34px;
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 700;
            padding: .42rem .75rem;
        }

        .section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.15rem 1.15rem .85rem;
            border-bottom: 1px solid var(--bum-line);
        }

        .section-title {
            color: var(--bum-ink);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: .18rem;
        }

        .section-subtitle {
            font-size: .86rem;
            margin-bottom: 0;
        }

        .segmented-filter {
            background: var(--bum-soft);
            border: 1px solid var(--bum-line);
            border-radius: 8px;
            display: inline-flex;
            gap: .25rem;
            padding: .25rem;
            flex: 0 0 auto;
        }

        .segmented-filter .btn {
            border: 0;
            border-radius: 6px;
            color: #596575;
            font-size: .78rem;
            font-weight: 800;
            min-height: 30px;
            padding: .35rem .75rem;
        }

        .segmented-filter .btn.active {
            background: var(--bum-orange);
            color: #fff;
            box-shadow: 0 4px 12px rgba(255, 107, 44, .22);
        }

        .ga-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            border-bottom: 1px solid var(--bum-line);
        }

        .ga-kpi {
            padding: 1rem 1.15rem;
            border-right: 1px solid var(--bum-line);
            display: flex;
            align-items: center;
            gap: .8rem;
            min-height: 86px;
        }

        .ga-kpi:last-child {
            border-right: 0;
        }

        .ga-insight-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            padding: 1rem 1.15rem;
        }

        .insight-panel {
            border: 1px solid var(--bum-line);
            border-radius: 8px;
            padding: 1rem;
            min-height: 142px;
        }

        .insight-title {
            color: var(--bum-ink);
            font-size: .86rem;
            font-weight: 800;
            margin-bottom: .9rem;
        }

        .meter-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: center;
            margin-bottom: .45rem;
            color: #6b7280;
            font-size: .82rem;
        }

        .meter {
            height: 6px;
            border-radius: 999px;
            background: #eef2f7;
            overflow: hidden;
            margin-bottom: .8rem;
        }

        .meter span {
            display: block;
            height: 100%;
            border-radius: inherit;
        }

        .empty-state {
            min-height: 86px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bum-muted);
            font-size: .84rem;
            text-align: center;
            background: #fbfcfe;
            border-radius: 8px;
        }

        .table-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0 1.15rem 1rem;
        }

        .table-section-title {
            color: var(--bum-ink);
            font-size: .92rem;
            font-weight: 800;
        }

        .clean-table {
            border-top: 1px solid var(--bum-line);
        }

        .clean-table table {
            margin-bottom: 0;
        }

        .clean-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid var(--bum-line);
            color: #384150;
            font-size: .78rem;
            font-weight: 800;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .clean-table tbody td {
            border-color: var(--bum-line);
            color: #364152;
            font-size: .84rem;
            padding: .85rem 1rem;
            vertical-align: middle;
        }

        .soft-badge {
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 800;
            padding: .3rem .5rem;
        }

        .ops-list .list-group-item {
            border-color: var(--bum-line);
            color: #6b7280;
            font-size: .88rem;
            padding: .85rem 1rem;
        }

        @media (max-width: 1199.98px) {
            .ga-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ga-kpi:nth-child(2) {
                border-right: 0;
            }

            .ga-kpi:nth-child(-n+2) {
                border-bottom: 1px solid var(--bum-line);
            }

            .ga-insight-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .bum-dashboard .page-title {
                font-size: 1.2rem;
            }

            .action-bar,
            .section-head,
            .table-section-head {
                align-items: stretch;
                flex-direction: column;
            }

            .segmented-filter {
                width: 100%;
            }

            .segmented-filter .btn {
                flex: 1;
            }

            .ga-kpi-grid {
                grid-template-columns: 1fr;
            }

            .ga-kpi {
                border-right: 0;
                border-bottom: 1px solid var(--bum-line);
            }

            .ga-kpi:last-child {
                border-bottom: 0;
            }
        }
    </style>
@endsection
@section('content')
<div class="container-fluid pb-5 bum-page bum-dashboard">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <h3 class="page-title">Ringkasan GA & Inventori</h3>
            <p class="page-subtitle mb-0">Pantau request Bagian Umum, stok minimum, penerimaan, dan opname bulan berjalan.</p>
        </div>
        <div class="action-bar">
            <a href="{{ route('bum.analytics') }}" class="btn btn-outline-info dashboard-btn">Analytics & Forecast</a>
            <a href="{{ route('bum.items') }}" class="btn btn-outline-primary dashboard-btn">Master Barang</a>
            <a href="{{ route('bum.receivings') }}" class="btn btn-primary dashboard-btn">Penerimaan Barang</a>
        </div>
    </div>

    @unless($inventoryTablesReady)
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            Modul inventori belum siap karena tabel BUM belum ada di database. Jalankan migration di server agar data stok, penerimaan, dan opname aktif.
        </div>
    @endunless

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'ATK/RTK Pending', 'value' => $atkPending, 'icon' => 'bi-clipboard-check', 'color' => 'warning'],
            ['label' => 'Konsumsi Pending', 'value' => $consumptionPending, 'icon' => 'bi-cup-hot', 'color' => 'info'],
            ['label' => 'Stok Minimum', 'value' => $lowStockCount, 'icon' => 'bi-exclamation-triangle', 'color' => 'danger'],
            ['label' => 'Penerimaan Pending', 'value' => $pendingReceiving, 'icon' => 'bi-truck', 'color' => 'primary'],
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

    <div class="section-card mb-4">
        <div class="section-head">
            <div>
                <div class="section-title">Permintaan & Temuan</div>
                <p class="section-subtitle">Monitoring laporan QR Code Bagian Umum berdasarkan jenis, status, dan lokasi.</p>
            </div>
            <div class="segmented-filter">
                @foreach([
                    'all' => 'Semua',
                    'Permintaan' => 'Permintaan',
                    'Temuan' => 'Temuan',
                ] as $value => $label)
                    <a href="{{ route('bum.dashboard', ['ga_type' => $value]) }}"
                       class="btn {{ $gaType === $value ? 'active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="ga-kpi-grid">
            @foreach([
                ['label' => 'Total Filter', 'value' => $gaStats['total'], 'icon' => 'bi-qr-code-scan', 'color' => 'primary'],
                ['label' => 'Open', 'value' => $gaStats['open'], 'icon' => 'bi-inbox', 'color' => 'success'],
                ['label' => 'In Progress', 'value' => $gaStats['in_progress'], 'icon' => 'bi-hourglass-split', 'color' => 'warning'],
                ['label' => 'Selesai', 'value' => $gaStats['completed'], 'icon' => 'bi-check2-circle', 'color' => 'info'],
            ] as $stat)
                <div class="ga-kpi">
                    <div class="metric-icon bg-{{ $stat['color'] }} bg-opacity-10 text-{{ $stat['color'] }}">
                        <i class="bi {{ $stat['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="metric-label">{{ $stat['label'] }}</div>
                        <div class="metric-value">{{ number_format($stat['value']) }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="ga-insight-grid">
            <div class="insight-panel">
                <div class="insight-title">Komposisi Semua Laporan</div>
                @php
                    $permintaanPercent = $gaStats['all_total'] > 0 ? round(($gaStats['all_permintaan'] / $gaStats['all_total']) * 100) : 0;
                    $temuanPercent = $gaStats['all_total'] > 0 ? round(($gaStats['all_temuan'] / $gaStats['all_total']) * 100) : 0;
                @endphp
                <div class="meter-row">
                    <span>Permintaan</span>
                    <strong>{{ number_format($gaStats['all_permintaan']) }}</strong>
                </div>
                <div class="meter"><span style="width: {{ $permintaanPercent }}%; background: var(--bum-blue);"></span></div>
                <div class="meter-row">
                    <span>Temuan</span>
                    <strong>{{ number_format($gaStats['all_temuan']) }}</strong>
                </div>
                <div class="meter mb-0"><span style="width: {{ $temuanPercent }}%; background: var(--bum-orange);"></span></div>
            </div>

            <div class="insight-panel">
                <div class="insight-title">Status Laporan</div>
                @forelse($gaStatusBreakdown as $statusName => $total)
                    @php $percent = $gaStats['total'] > 0 ? round(($total / $gaStats['total']) * 100) : 0; @endphp
                    <div class="meter-row">
                        <span>{{ $statusName }}</span>
                        <strong>{{ number_format($total) }}</strong>
                    </div>
                    <div class="meter"><span style="width: {{ $percent }}%; background: var(--bum-green);"></span></div>
                @empty
                    <div class="empty-state">Belum ada laporan untuk filter ini.</div>
                @endforelse
            </div>

            <div class="insight-panel">
                <div class="insight-title">Top Lokasi</div>
                @forelse($gaLocationBreakdown as $location => $total)
                    @php $percent = $gaStats['total'] > 0 ? round(($total / $gaStats['total']) * 100) : 0; @endphp
                    <div class="meter-row">
                        <span class="text-truncate">{{ $location }}</span>
                        <strong>{{ number_format($total) }}</strong>
                    </div>
                    <div class="meter"><span style="width: {{ $percent }}%; background: var(--bum-red);"></span></div>
                @empty
                    <div class="empty-state">Belum ada lokasi untuk filter ini.</div>
                @endforelse
            </div>
        </div>

        <div class="table-section-head">
            <div class="table-section-title">Laporan Terbaru</div>
            <a href="{{ route('ticket.ga-permintaan-temuan.create') }}" class="btn btn-outline-primary dashboard-btn">Input Laporan</a>
        </div>
        <div class="table-responsive clean-table">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>No Ticket</th>
                        <th>Jenis</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Pelapor</th>
                        <th class="text-end">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gaRecentTickets as $ticket)
                        <tr>
                            <td>
                                <a href="{{ route('ticket.show', $ticket) }}" class="fw-semibold text-decoration-none">
                                    {{ $ticket->ticket_no }}
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark border soft-badge">{{ data_get($ticket->payload, 'report_type', '-') }}</span></td>
                            <td class="wrap">{{ data_get($ticket->payload, 'location', '-') }}</td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary soft-badge">{{ $ticket->status->name ?? '-' }}</span></td>
                            <td>{{ $ticket->requester->name ?? '-' }}</td>
                            <td class="text-end muted-text">{{ $ticket->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center muted-text py-4">Belum ada laporan untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="section-card h-100">
                <div class="section-head">
                    <div>
                        <div class="section-title">Item Stok Minimum</div>
                        <p class="section-subtitle">Barang aktif yang sudah menyentuh batas minimum.</p>
                    </div>
                </div>
                <div class="table-responsive clean-table">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Kategori</th>
                                <th>Lokasi</th>
                                <th class="text-end">Stok</th>
                                <th class="text-end">Min</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lowStockItems as $item)
                                <tr>
                                    <td class="fw-semibold wrap">{{ $item->code }} - {{ $item->name }}</td>
                                    <td><span class="badge bg-light text-dark border soft-badge">{{ $item->category }}</span></td>
                                    <td>{{ $item->location ?? '-' }}</td>
                                    <td class="text-end text-danger fw-bold">{{ $item->current_stock }}</td>
                                    <td class="text-end">{{ $item->minimum_stock }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center muted-text py-4">Tidak ada item di bawah minimum stok.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="section-card mb-3">
                <div class="section-head">
                    <div>
                        <div class="section-title">Stock Opname Bulan Ini</div>
                        <p class="section-subtitle">Status opname periode berjalan.</p>
                    </div>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="muted-text">Periode</span>
                        <span class="fw-bold text-dark">{{ now()->format('Y-m') }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="muted-text">Status</span>
                        <span class="badge bg-{{ $currentOpname ? 'success' : 'warning' }} bg-opacity-10 text-{{ $currentOpname ? 'success' : 'warning' }} soft-badge">
                            {{ $currentOpname->status ?? 'BELUM ADA' }}
                        </span>
                    </div>
                    <a href="{{ route('bum.opnames') }}" class="btn btn-outline-primary dashboard-btn w-100">Kelola Opname</a>
                </div>
            </div>
            <div class="list-group ops-list shadow-sm">
                <a class="list-group-item list-group-item-action" href="{{ route('bum.stock-card') }}">Stock Card</a>
                <a class="list-group-item list-group-item-action" href="{{ route('bum.analytics') }}">Analytics & Forecast</a>
                <a class="list-group-item list-group-item-action" href="{{ route('bum.receivings') }}">Penerimaan Barang</a>
                <a class="list-group-item list-group-item-action" href="{{ route('bum.reports') }}">Laporan</a>
            </div>
        </div>
    </div>
</div>
@endsection
