@extends('partials.layouts.master')

@section('title', 'Executive Summary')
@section('pagetitle', 'Executive Summary')
@section('title-sub', 'Laporan Eksekutif')

@section('css')
<style>
    .executive-page {
        --executive-ink: #20252c;
        --executive-muted: #727c88;
        --executive-line: #e7eaee;
        --executive-soft: #f6f7f9;
        --executive-red: #e21a1a;
        color: var(--executive-ink);
        max-width: 1480px;
    }

    .executive-header {
        align-items: end;
        display: flex;
        gap: 24px;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .executive-title {
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -.03em;
        margin: 0 0 5px;
    }

    .executive-subtitle,
    .executive-muted {
        color: var(--executive-muted);
    }

    .executive-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }

    .executive-actions .btn,
    .executive-filter .btn {
        border-radius: 8px;
        font-size: .8rem;
        font-weight: 750;
        min-height: 37px;
    }

    .executive-card {
        background: #fff;
        border: 1px solid var(--executive-line);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(31, 41, 51, .045);
    }

    .executive-filter {
        margin-bottom: 16px;
        padding: 16px;
    }

    .executive-filter-grid {
        align-items: end;
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(145px, 1fr)) auto auto;
    }

    .executive-filter .form-label {
        color: #5f6975;
        font-size: .72rem;
        font-weight: 750;
        margin-bottom: 5px;
    }

    .executive-filter .form-control,
    .executive-filter .form-select {
        border-color: var(--executive-line);
        border-radius: 8px;
        font-size: .8rem;
        min-height: 38px;
    }

    .executive-summary {
        background: linear-gradient(125deg, #25292f, #353b44);
        border: 0;
        color: #fff;
        overflow: hidden;
        padding: 22px;
        position: relative;
    }

    .executive-summary::after {
        border: 30px solid rgba(255, 255, 255, .035);
        border-radius: 50%;
        content: '';
        height: 170px;
        position: absolute;
        right: -40px;
        top: -82px;
        width: 170px;
    }

    .executive-kicker {
        color: #ff8585;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .09em;
        margin-bottom: 9px;
        text-transform: uppercase;
    }

    .executive-summary h2 {
        font-size: 1.2rem;
        font-weight: 750;
        line-height: 1.35;
        margin: 0 0 9px;
        max-width: 760px;
    }

    .executive-summary p {
        color: #c6cbd2;
        font-size: .79rem;
        line-height: 1.55;
        margin: 0;
        max-width: 850px;
    }

    .executive-snapshot {
        height: 100%;
        padding: 18px;
    }

    .executive-card-title {
        color: var(--executive-ink);
        font-size: .88rem;
        font-weight: 800;
        margin: 0;
    }

    .executive-card-subtitle {
        color: var(--executive-muted);
        font-size: .72rem;
        margin-top: 3px;
    }

    .snapshot-row {
        align-items: center;
        border-bottom: 1px solid #f0f1f3;
        display: flex;
        font-size: .76rem;
        justify-content: space-between;
        padding: 7px 0;
    }

    .snapshot-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .snapshot-row span {
        color: var(--executive-muted);
    }

    .snapshot-row strong {
        color: var(--executive-ink);
        font-weight: 800;
    }

    .executive-metrics {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        margin: 16px 0;
    }

    .executive-metric {
        min-width: 0;
        padding: 14px;
    }

    .metric-head {
        align-items: center;
        display: flex;
        gap: 9px;
        margin-bottom: 12px;
    }

    .metric-icon {
        align-items: center;
        background: rgba(226, 26, 26, .08);
        border-radius: 8px;
        color: var(--executive-red);
        display: inline-flex;
        flex: 0 0 34px;
        height: 34px;
        justify-content: center;
        width: 34px;
    }

    .metric-label {
        color: var(--executive-muted);
        font-size: .7rem;
        font-weight: 750;
        line-height: 1.25;
    }

    .metric-value {
        color: var(--executive-ink);
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1;
    }

    .metric-compare {
        color: var(--executive-muted);
        font-size: .67rem;
        margin-top: 6px;
    }

    .executive-section {
        margin-top: 16px;
    }

    .executive-section-head {
        align-items: center;
        border-bottom: 1px solid var(--executive-line);
        display: flex;
        justify-content: space-between;
        padding: 14px 16px;
    }

    .recommendation-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        padding: 14px;
    }

    .recommendation-item {
        border: 1px solid var(--executive-line);
        border-radius: 8px;
        min-height: 130px;
        padding: 14px;
    }

    .recommendation-top {
        align-items: center;
        display: flex;
        gap: 10px;
        justify-content: space-between;
        margin-bottom: 9px;
    }

    .recommendation-item h3 {
        font-size: .8rem;
        font-weight: 800;
        margin: 0;
    }

    .recommendation-item p {
        color: var(--executive-muted);
        font-size: .72rem;
        line-height: 1.55;
        margin: 0;
    }

    .executive-badge {
        border-radius: 999px;
        font-size: .62rem;
        font-weight: 800;
        padding: 4px 7px;
        white-space: nowrap;
    }

    .chart-body {
        min-height: 290px;
        padding: 8px 12px 4px;
    }

    .distribution-list {
        display: grid;
        gap: 8px;
        padding: 0 16px 16px;
    }

    .distribution-row {
        align-items: center;
        display: grid;
        font-size: .7rem;
        gap: 8px;
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .distribution-row span {
        color: var(--executive-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .comparison-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 14px;
    }

    .comparison-card {
        background: var(--executive-soft);
        border: 1px solid var(--executive-line);
        border-radius: 8px;
        padding: 14px;
    }

    .comparison-card.current {
        background: #fff5f5;
        border-color: rgba(226, 26, 26, .15);
    }

    .comparison-period {
        color: var(--executive-muted);
        font-size: .68rem;
        font-weight: 750;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .comparison-values {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(3, 1fr);
    }

    .comparison-values small {
        color: var(--executive-muted);
        display: block;
        font-size: .65rem;
        margin-bottom: 3px;
    }

    .comparison-values strong {
        font-size: .92rem;
        font-weight: 800;
    }

    .executive-table th {
        background: var(--executive-soft);
        color: #59636f;
        font-size: .68rem;
        font-weight: 800;
        padding: 11px 14px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .executive-table td {
        border-color: var(--executive-line);
        color: #414a55;
        font-size: .76rem;
        padding: 11px 14px;
        vertical-align: middle;
    }

    .progress.executive-progress {
        background: #eceff2;
        height: 5px;
        min-width: 90px;
    }

    .progress.executive-progress .progress-bar {
        background: var(--executive-red);
    }

    @media (max-width: 1199.98px) {
        .executive-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .executive-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 767.98px) {
        .executive-header { align-items: stretch; flex-direction: column; }
        .executive-actions { justify-content: stretch; }
        .executive-actions .btn { flex: 1 1 auto; }
        .executive-filter-grid,
        .executive-metrics,
        .recommendation-grid,
        .comparison-grid { grid-template-columns: 1fr; }
        .executive-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media print {
        .pe-app-header,
        .pe-app-sidebar,
        .footer,
        .executive-actions,
        .executive-filter,
        #scroll-top { display: none !important; }
        .app-wrapper { margin: 0 !important; padding: 0 !important; }
        .executive-page { max-width: none; }
        .executive-card { break-inside: avoid; box-shadow: none; }
        body { background: #fff !important; }
    }
</style>
@endsection

@section('content')
@php
    $metricCards = [
        ['label' => 'Total Tiket', 'key' => 'total', 'icon' => 'bi-ticket-perforated', 'suffix' => ''],
        ['label' => 'Completion Rate', 'key' => 'completion_rate', 'icon' => 'bi-check2-circle', 'suffix' => '%'],
        ['label' => 'Tiket Open', 'key' => 'open', 'icon' => 'bi-inbox', 'suffix' => ''],
        ['label' => 'Dalam Proses', 'key' => 'in_progress', 'icon' => 'bi-arrow-repeat', 'suffix' => ''],
        ['label' => 'Menunggu Approval', 'key' => 'pending_approval', 'icon' => 'bi-person-check', 'suffix' => ''],
        ['label' => 'Rata-rata Resolusi', 'key' => 'avg_resolution_hours', 'icon' => 'bi-stopwatch', 'suffix' => ' jam'],
    ];
@endphp

<div class="container-fluid executive-page pb-4">
    <div class="executive-header">
        <div>
            <h1 class="executive-title">Executive Summary</h1>
            <p class="executive-subtitle mb-0">Ringkasan performa tiket operasional untuk pengambilan keputusan manajemen.</p>
        </div>
        <div class="executive-actions">
            <button type="button" class="btn btn-light border" id="copyExecutiveSummary">
                <i class="bi bi-copy me-1"></i> Salin Ringkasan
            </button>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak / PDF
            </button>
        </div>
    </div>

    <div class="executive-card executive-filter">
        <form method="GET" class="executive-filter-grid">
            <div>
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom->format('Y-m-d') }}">
            </div>
            <div>
                <label class="form-label">Tanggal Selesai</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo->format('Y-m-d') }}">
            </div>
            <div>
                <label class="form-label">Jenis Permintaan</label>
                <select name="request_type" class="form-select">
                    <option value="">Semua jenis</option>
                    @foreach($requestTypes as $value => $label)
                        <option value="{{ $value }}" @selected($requestType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status_id" class="form-select">
                    <option value="">Semua status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->id }}" @selected($statusId === (int) $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('bum.executive-summary') }}" class="btn btn-light border px-3">Reset</a>
            <button class="btn btn-primary px-4">Terapkan</button>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="executive-card executive-summary h-100">
                <div class="executive-kicker">Executive summary otomatis</div>
                <h2>{{ $narrative['headline'] }}</h2>
                <p>{{ $narrative['detail'] }}</p>
                <div class="mt-3">
                    <span class="executive-badge bg-{{ $narrative['tone'] }} bg-opacity-25 text-white">
                        {{ $dateFrom->translatedFormat('d M Y') }} – {{ $dateTo->translatedFormat('d M Y') }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="executive-card executive-snapshot">
                <h2 class="executive-card-title">Snapshot Kinerja</h2>
                <p class="executive-card-subtitle">Kondisi periode terpilih saat ini.</p>
                <div class="snapshot-row"><span>Volume tiket</span><strong>{{ number_format($summary['total']) }}</strong></div>
                <div class="snapshot-row"><span>Tiket selesai</span><strong>{{ number_format($summary['completed']) }}</strong></div>
                <div class="snapshot-row"><span>Completion rate</span><strong>{{ number_format($summary['completion_rate'], 1) }}%</strong></div>
                <div class="snapshot-row"><span>Rata-rata resolusi</span><strong>{{ number_format($summary['avg_resolution_hours'], 1) }} jam</strong></div>
            </div>
        </div>
    </div>

    <div class="executive-metrics">
        @foreach($metricCards as $metric)
            @php($delta = round($summary[$metric['key']] - $previous[$metric['key']], 1))
            <div class="executive-card executive-metric">
                <div class="metric-head">
                    <span class="metric-icon"><i class="bi {{ $metric['icon'] }}"></i></span>
                    <span class="metric-label">{{ $metric['label'] }}</span>
                </div>
                <div class="metric-value">{{ number_format($summary[$metric['key']], str_contains((string) $summary[$metric['key']], '.') ? 1 : 0) }}{{ $metric['suffix'] }}</div>
                <div class="metric-compare {{ $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : '') }}">
                    {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1) }} vs periode lalu
                </div>
            </div>
        @endforeach
    </div>

    <section class="executive-card executive-section">
        <div class="executive-section-head">
            <div>
                <h2 class="executive-card-title">Recommended Action Plan</h2>
                <p class="executive-card-subtitle mb-0">Prioritas tindak lanjut berdasarkan kondisi tiket pada periode terpilih.</p>
            </div>
            <span class="executive-badge bg-light text-dark border">{{ count($recommendations) }} aksi</span>
        </div>
        <div class="recommendation-grid">
            @foreach($recommendations as $item)
                <article class="recommendation-item">
                    <div class="recommendation-top">
                        <span class="metric-icon"><i class="bi {{ $item['icon'] }}"></i></span>
                        <span class="executive-badge bg-{{ $item['tone'] }}-subtle text-{{ $item['tone'] }}">{{ $item['level'] }}</span>
                    </div>
                    <h3>{{ $item['title'] }}</h3>
                    <p class="mt-2">{{ $item['description'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <div class="row g-3 executive-section">
        <div class="col-xl-8">
            <section class="executive-card h-100">
                <div class="executive-section-head">
                    <div>
                        <h2 class="executive-card-title">Tren Tiket</h2>
                        <p class="executive-card-subtitle mb-0">Perbandingan tiket masuk dan tiket selesai.</p>
                    </div>
                </div>
                <div class="chart-body" id="executiveTrendChart"></div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="executive-card h-100">
                <div class="executive-section-head">
                    <div>
                        <h2 class="executive-card-title">Komposisi Status</h2>
                        <p class="executive-card-subtitle mb-0">Distribusi kondisi tiket saat ini.</p>
                    </div>
                </div>
                <div class="chart-body" id="executiveStatusChart"></div>
            </section>
        </div>
    </div>

    <div class="row g-3 executive-section">
        <div class="col-xl-7">
            <section class="executive-card h-100">
                <div class="executive-section-head">
                    <div>
                        <h2 class="executive-card-title">Perbandingan Periode</h2>
                        <p class="executive-card-subtitle mb-0">Kinerja periode aktif terhadap periode dengan durasi yang sama.</p>
                    </div>
                </div>
                <div class="comparison-grid">
                    @foreach([
                        ['class' => 'current', 'title' => 'Periode Aktif', 'from' => $dateFrom, 'to' => $dateTo, 'data' => $summary],
                        ['class' => '', 'title' => 'Periode Sebelumnya', 'from' => $previousFrom, 'to' => $previousTo, 'data' => $previous],
                    ] as $period)
                        <div class="comparison-card {{ $period['class'] }}">
                            <div class="comparison-period">{{ $period['title'] }} · {{ $period['from']->format('d M') }} – {{ $period['to']->format('d M Y') }}</div>
                            <div class="comparison-values">
                                <div><small>Total tiket</small><strong>{{ number_format($period['data']['total']) }}</strong></div>
                                <div><small>Completion</small><strong>{{ number_format($period['data']['completion_rate'], 1) }}%</strong></div>
                                <div><small>Avg. resolusi</small><strong>{{ number_format($period['data']['avg_resolution_hours'], 1) }}j</strong></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="executive-card h-100">
                <div class="executive-section-head">
                    <div>
                        <h2 class="executive-card-title">Komposisi Operasional</h2>
                        <p class="executive-card-subtitle mb-0">Jenis permintaan dan prioritas dominan.</p>
                    </div>
                </div>
                <div class="row g-0">
                    <div class="col-sm-6 border-end">
                        <div class="distribution-list pt-3">
                            @forelse($requestTypeDistribution as $row)
                                <div class="distribution-row"><span>{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong></div>
                            @empty
                                <span class="executive-muted small">Belum ada data.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="distribution-list pt-3">
                            @forelse($priorityDistribution as $row)
                                <div class="distribution-row"><span>{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong></div>
                            @empty
                                <span class="executive-muted small">Belum ada data.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="executive-card executive-section overflow-hidden">
        <div class="executive-section-head">
            <div>
                <h2 class="executive-card-title">Kategori Permintaan Teratas</h2>
                <p class="executive-card-subtitle mb-0">Volume dan tingkat penyelesaian kategori dengan aktivitas tertinggi.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table executive-table mb-0">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Selesai</th>
                        <th style="width: 220px;">Completion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topCategories as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['label'] }}</td>
                            <td class="text-end">{{ number_format($row['total']) }}</td>
                            <td class="text-end">{{ number_format($row['completed']) }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress executive-progress flex-grow-1">
                                        <div class="progress-bar" style="width: {{ min(100, $row['rate']) }}%"></div>
                                    </div>
                                    <strong>{{ number_format($row['rate'], 1) }}%</strong>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center executive-muted py-4">Belum ada data pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const trend = @json($trend);
    const statuses = @json($statusDistribution);
    const chartFont = getComputedStyle(document.body).fontFamily;

    new ApexCharts(document.querySelector('#executiveTrendChart'), {
        chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: chartFont },
        colors: ['#e21a1a', '#69727e'],
        dataLabels: { enabled: false },
        fill: { type: 'gradient', gradient: { opacityFrom: .22, opacityTo: .02 } },
        grid: { borderColor: '#edf0f2', strokeDashArray: 4 },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px' },
        series: [
            { name: 'Tiket Masuk', data: trend.map(row => row.total) },
            { name: 'Tiket Selesai', data: trend.map(row => row.completed) },
        ],
        stroke: { curve: 'smooth', width: 2.5 },
        xaxis: { categories: trend.map(row => row.label), labels: { style: { fontSize: '10px' } } },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value) } },
        noData: { text: 'Belum ada data' },
    }).render();

    new ApexCharts(document.querySelector('#executiveStatusChart'), {
        chart: { type: 'donut', height: 280, fontFamily: chartFont },
        colors: ['#e21a1a', '#f59e0b', '#2786a6', '#22a06b', '#7b8794', '#b65fcf'],
        dataLabels: { enabled: false },
        labels: statuses.map(row => row.label),
        legend: { position: 'bottom', fontSize: '10px' },
        plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => '{{ $summary['total'] }}' } } } } },
        series: statuses.map(row => row.value),
        noData: { text: 'Belum ada data' },
    }).render();

    document.getElementById('copyExecutiveSummary')?.addEventListener('click', async function () {
        const text = @json($narrative['headline'].' '.$narrative['detail']);
        await navigator.clipboard.writeText(text);
        const original = this.innerHTML;
        this.innerHTML = '<i class="bi bi-check2 me-1"></i> Tersalin';
        setTimeout(() => this.innerHTML = original, 1600);
    });
});
</script>
@endsection
