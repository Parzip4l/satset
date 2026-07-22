@extends('partials.layouts.master')

@section('title', 'Analytics BUM')
@section('css')
    @include('bum.partials.mobile-style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .analytics-card {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
            overflow: hidden;
        }

        .analytics-table-head {
            align-items: center;
            background: #fff;
            border-bottom: 1px solid #e7ecf2;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: 1rem 1.1rem;
        }

        .analytics-table-title {
            color: #1f2933;
            font-size: .95rem;
            font-weight: 800;
        }

        .analytics-table-subtitle {
            color: #7b8794;
            font-size: .78rem;
            margin-top: .15rem;
        }

        .analytics-page-size {
            align-items: center;
            display: flex;
            gap: .5rem;
            white-space: nowrap;
        }

        .analytics-page-size label {
            color: #7b8794;
            font-size: .78rem;
            font-weight: 800;
            margin: 0;
        }

        .analytics-page-size .form-select {
            min-width: 84px;
        }

        .analytics-table-wrap {
            min-height: 420px;
        }

        .analytics-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e7ecf2;
            color: #394150;
            cursor: pointer;
            font-size: .76rem;
            font-weight: 800;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .analytics-table tbody td {
            border-color: #e7ecf2;
            color: #364152;
            font-size: .83rem;
            padding: .78rem 1rem;
            vertical-align: middle;
        }

        .analytics-pagination {
            align-items: center;
            background: #fff;
            border-top: 1px solid #e7ecf2;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: .85rem 1rem;
        }

        .analytics-pagination-info {
            color: #7b8794;
            font-size: .78rem;
            font-weight: 700;
        }

        .analytics-pager {
            align-items: center;
            display: flex;
            gap: .35rem;
        }

        .analytics-pager .btn {
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 800;
            min-width: 36px;
            padding: .38rem .62rem;
        }

        .select2-container--bootstrap-5 .select2-selection {
            border-color: #e7ecf2;
            border-radius: 8px;
            min-height: 38px;
        }

        @media (max-width: 767.98px) {
            .analytics-table-head,
            .analytics-pagination {
                align-items: stretch;
                flex-direction: column;
            }

            .analytics-page-size,
            .analytics-pager {
                justify-content: space-between;
            }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4 bum-page-header">
        <div>
            <h3 class="fw-bold text-dark mb-1">Analytics BUM</h3>
            <p class="text-muted mb-0">Tren pemakaian, forecast stok, konsumsi rapat, penerimaan, dan rekomendasi pengadaan.</p>
        </div>
        <div class="bum-action-row">
            <a href="{{ route('bum.dashboard') }}" class="btn btn-light border">Dashboard</a>
            <a href="{{ route('bum.reports') }}" class="btn btn-outline-primary">Laporan</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form id="analyticsFilter" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Periode</label>
                    <select name="period" class="form-select">
                        <option value="7d">7 hari</option>
                        <option value="30d" selected>30 hari</option>
                        <option value="3m">3 bulan</option>
                        <option value="6m">6 bulan</option>
                        <option value="12m">12 bulan</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Kategori</label>
                    <select name="category" class="form-select">
                        <option value="">Semua</option>
                        <option value="ATK">ATK</option>
                        <option value="RTK">RTK</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted fw-bold">Barang</label>
                    <select name="item_id" class="form-select analytics-select" data-placeholder="Cari kode atau nama barang">
                        <option value="">Semua Barang</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->code }} - {{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Forecast</label>
                    <select name="forecast_days" class="form-select">
                        <option value="30" selected>30 hari</option>
                        <option value="60">60 hari</option>
                        <option value="90">90 hari</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Gudang</label>
                    <select name="stock_location" class="form-select" id="stockLocationFilter">
                        <option value="small_warehouse" selected>Gudang Kecil</option>
                        <option value="big_warehouse">Gudang Besar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Departemen</label>
                    <select name="department_id" class="form-select analytics-select" data-placeholder="Cari departemen">
                        <option value="">Semua</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit">Terapkan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="analyticsError" class="alert alert-danger d-none"></div>

    <div class="row g-3 mb-4" id="summaryCards">
        @foreach([
            ['key' => 'total_usage_this_month', 'label' => 'Pemakaian Bulan Ini', 'icon' => 'bi-box-arrow-up', 'color' => 'primary'],
            ['key' => 'small_warehouse_usage_this_month', 'label' => 'Keluar Gudang Kecil', 'icon' => 'bi-box-arrow-up-right', 'color' => 'info'],
            ['key' => 'big_warehouse_usage_this_month', 'label' => 'Keluar Gudang Besar', 'icon' => 'bi-box-arrow-up-right', 'color' => 'dark'],
            ['key' => 'atk_rtk_requests_this_month', 'label' => 'Request ATK/RTK', 'icon' => 'bi-clipboard-check', 'color' => 'warning'],
            ['key' => 'meeting_consumption_this_month', 'label' => 'Konsumsi Rapat', 'icon' => 'bi-cup-hot', 'color' => 'info'],
            ['key' => 'receiving_this_month', 'label' => 'Barang Diterima', 'icon' => 'bi-truck', 'color' => 'success'],
            ['key' => 'low_stock_items', 'label' => 'Low Stock', 'icon' => 'bi-exclamation-triangle', 'color' => 'danger'],
            ['key' => 'low_stock_small_items', 'label' => 'Low Stock GK', 'icon' => 'bi-exclamation-triangle', 'color' => 'danger'],
            ['key' => 'low_stock_big_items', 'label' => 'Low Stock GB', 'icon' => 'bi-exclamation-triangle', 'color' => 'warning'],
            ['key' => 'predicted_stock_out_30_days', 'label' => 'Habis <= 30 Hari', 'icon' => 'bi-hourglass-split', 'color' => 'danger'],
            ['key' => 'next_month_recommended_purchase_qty', 'label' => 'Rekomendasi Beli', 'icon' => 'bi-cart-plus', 'color' => 'dark'],
            ['key' => 'filtered_usage_total', 'label' => 'Pemakaian Filter', 'icon' => 'bi-funnel', 'color' => 'secondary'],
        ] as $card)
            <div class="col-xxl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded bg-{{ $card['color'] }} bg-opacity-10 text-{{ $card['color'] }} d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                            <i class="bi {{ $card['icon'] }} fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">{{ $card['label'] }}</div>
                            <div class="h4 fw-bold mb-0" data-summary="{{ $card['key'] }}">...</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Tren Keluar Barang <span class="text-muted fw-normal" data-stock-location-label></span></div>
                <div class="card-body"><div id="usageTrendChart" style="min-height:320px;"></div></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Actual vs Forecast Keluar Barang <span class="text-muted fw-normal" data-stock-location-label></span></div>
                <div class="card-body"><div id="usageForecastChart" style="min-height:320px;"></div></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Request ATK/RTK</div>
                <div class="card-body"><div id="requestTrendChart" style="min-height:300px;"></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Konsumsi Rapat</div>
                <div class="card-body"><div id="meetingTrendChart" style="min-height:300px;"></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Penerimaan Barang</div>
                <div class="card-body"><div id="receivingTrendChart" style="min-height:300px;"></div></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card analytics-card h-100">
                <div class="analytics-table-head">
                    <div>
                        <div class="analytics-table-title">Forecast Stock-Out <span class="text-muted fw-normal" data-stock-location-label></span></div>
                        <div class="analytics-table-subtitle" id="stockForecastSummary">Memuat data...</div>
                    </div>
                    <div class="analytics-page-size">
                        <label for="stockForecastPageSize">Tampil</label>
                        <select class="form-select form-select-sm" id="stockForecastPageSize" data-table-page-size="stockForecast">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive analytics-table-wrap">
                    <table class="table table-hover align-middle mb-0 analytics-table">
                        <thead>
                            <tr>
                                <th data-table-sort="stockForecast" data-sort-key="name" data-sort-type="text">Barang <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-table-sort="stockForecast" data-sort-key="current_stock" data-sort-type="number" class="text-end">Stok <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-table-sort="stockForecast" data-sort-key="average_daily_usage" data-sort-type="number" class="text-end">Avg/Hari <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-table-sort="stockForecast" data-sort-key="estimated_stock_out_date" data-sort-type="text">Estimasi Habis <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-table-sort="stockForecast" data-sort-key="recommended_purchase_qty" data-sort-type="number" class="text-end">Rekomendasi <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-table-sort="stockForecast" data-sort-key="risk_level" data-sort-type="text">Risk <i class="bi bi-arrow-down-up text-muted"></i></th>
                            </tr>
                        </thead>
                        <tbody id="stockForecastRows">
                            <tr><td colspan="6" class="text-center text-muted py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="analytics-pagination">
                    <div class="analytics-pagination-info" id="stockForecastPageInfo">-</div>
                    <div class="analytics-pager" id="stockForecastPager"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card analytics-card h-100">
                <div class="analytics-table-head">
                    <div>
                        <div class="analytics-table-title">Rekomendasi Pengadaan <span class="text-muted fw-normal" data-stock-location-label></span></div>
                        <div class="analytics-table-subtitle" id="recommendationSummary">Memuat data...</div>
                    </div>
                    <div class="analytics-page-size">
                        <label for="recommendationPageSize">Tampil</label>
                        <select class="form-select form-select-sm" id="recommendationPageSize" data-table-page-size="recommendations">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive analytics-table-wrap">
                    <table class="table table-hover align-middle mb-0 analytics-table">
                        <thead>
                            <tr>
                                <th data-table-sort="recommendations" data-sort-key="name" data-sort-type="text">Item <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-table-sort="recommendations" data-sort-key="current_stock" data-sort-type="number" class="text-end">Stok <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-table-sort="recommendations" data-sort-key="recommended_purchase_qty" data-sort-type="number" class="text-end">Qty Beli <i class="bi bi-arrow-down-up text-muted"></i></th>
                                <th data-table-sort="recommendations" data-sort-key="risk_level" data-sort-type="text">Risk <i class="bi bi-arrow-down-up text-muted"></i></th>
                            </tr>
                        </thead>
                        <tbody id="recommendationRows">
                            <tr><td colspan="4" class="text-center text-muted py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="analytics-pagination">
                    <div class="analytics-pagination-info" id="recommendationPageInfo">-</div>
                    <div class="analytics-pager" id="recommendationPager"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
const endpoints = {
    summary: @json(route('bum.analytics.data.summary')),
    usageTrend: @json(route('bum.analytics.data.usage-trend')),
    usageForecast: @json(route('bum.analytics.data.usage-forecast')),
    stockForecast: @json(route('bum.analytics.data.stock-forecast')),
    requestTrend: @json(route('bum.analytics.data.request-trend')),
    meetingTrend: @json(route('bum.analytics.data.meeting-consumption-trend')),
    receivingTrend: @json(route('bum.analytics.data.receiving-trend')),
    recommendations: @json(route('bum.analytics.data.procurement-recommendation')),
};

const charts = {};
const riskClass = { CRITICAL: 'danger', HIGH: 'warning', MEDIUM: 'info', LOW: 'success', NO_DATA: 'secondary' };
const tableState = {
    stockForecast: { rows: [], page: 1, pageSize: 10, sortKey: null, sortType: 'text', direction: 'asc' },
    recommendations: { rows: [], page: 1, pageSize: 10, sortKey: null, sortType: 'text', direction: 'asc' },
};

function qs() {
    return new URLSearchParams(new FormData(document.getElementById('analyticsFilter'))).toString();
}

function selectedStockLocationLabel() {
    const select = document.getElementById('stockLocationFilter');
    return select?.selectedOptions?.[0]?.textContent || 'Gudang Kecil';
}

function refreshStockLocationLabels() {
    const label = `(${selectedStockLocationLabel()})`;
    document.querySelectorAll('[data-stock-location-label]').forEach(el => {
        el.textContent = label;
    });
}

function numberId(value) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value || 0);
}

function dateShort(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' }).format(new Date(`${value}T00:00:00`));
}

function dateLong(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`));
}

function monthShort(value) {
    if (!value) return '';
    const [year, month] = value.split('-');
    return new Intl.DateTimeFormat('id-ID', { month: 'short', year: '2-digit' }).format(new Date(Number(year), Number(month) - 1, 1));
}

function labelInterval(total) {
    if (total <= 10) return 1;
    if (total <= 35) return 5;
    if (total <= 100) return 14;
    return 30;
}

function sparseDateLabel(value, index, total) {
    index = Number.isInteger(index) ? index : 0;
    const interval = labelInterval(total);
    if (index === 0 || index === total - 1 || index % interval === 0) {
        return dateShort(value);
    }
    return '';
}

function sparseCategoryFormatter(categories) {
    return function(value, timestamp, opts) {
        const index = opts && Number.isInteger(opts.i) ? opts.i : categories.indexOf(value);
        return sparseDateLabel(value, index, categories.length);
    };
}

function tooltipHtml(title, rows) {
    const validRows = rows.filter(row => row.value !== null && row.value !== undefined);
    if (!validRows.length) return '';

    return `
        <div style="padding:10px 12px; min-width:170px;">
            <div style="font-weight:700; color:#111827; margin-bottom:8px;">${title}</div>
            ${validRows.map(row => `
                <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; margin-top:4px;">
                    <span style="display:flex; align-items:center; gap:6px; color:#4b5563;">
                        <span style="width:8px; height:8px; border-radius:999px; background:${row.color}; display:inline-block;"></span>
                        ${row.label}
                    </span>
                    <strong style="color:#111827;">${numberId(row.value)}</strong>
                </div>
            `).join('')}
        </div>
    `;
}

function usageTooltip(categories) {
    return function({ series, dataPointIndex }) {
        const date = categories[dataPointIndex];
        return tooltipHtml(dateLong(date), [
            { label: 'Qty keluar', value: series[0]?.[dataPointIndex], color: '#e21a1a' },
        ]);
    };
}

function forecastTooltip(categories) {
    return function({ series, dataPointIndex }) {
        const date = categories[dataPointIndex];
        return tooltipHtml(dateLong(date), [
            { label: 'Actual Usage', value: series[0]?.[dataPointIndex], color: '#2563eb' },
            { label: 'Forecast Usage', value: series[1]?.[dataPointIndex], color: '#dc2626' },
        ]);
    };
}

function showError(message) {
    const box = document.getElementById('analyticsError');
    box.textContent = message;
    box.classList.remove('d-none');
}

function clearError() {
    document.getElementById('analyticsError').classList.add('d-none');
}

async function getJson(url) {
    const response = await fetch(`${url}?${qs()}`, { headers: { 'Accept': 'application/json' } });
    if (!response.ok) throw new Error('Gagal memuat data analytics.');
    return (await response.json()).data || [];
}

function renderChart(id, options) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new ApexCharts(document.querySelector(`#${id}`), options);
    charts[id].render();
}

function emptyChart(id, text = 'Belum ada data') {
    renderChart(id, {
        chart: { type: 'bar', height: 280, toolbar: { show: false } },
        series: [{ name: text, data: [] }],
        noData: { text },
    });
}

async function loadSummary() {
    const data = await getJson(endpoints.summary);
    refreshStockLocationLabels();
    document.querySelectorAll('[data-summary]').forEach(el => {
        el.textContent = numberId(data[el.dataset.summary] || 0);
    });
}

async function loadUsageTrend() {
    const data = await getJson(endpoints.usageTrend);
    if (!data.length) return emptyChart('usageTrendChart');
    const categories = data.map(row => row.date);
    renderChart('usageTrendChart', {
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        series: [{ name: 'Qty keluar', data: data.map(row => row.usage) }],
        xaxis: {
            categories,
            tickPlacement: 'on',
            labels: {
                rotate: 0,
                trim: false,
                formatter: sparseCategoryFormatter(categories)
            },
            tooltip: { formatter: dateShort },
        },
        yaxis: { labels: { formatter: numberId } },
        colors: ['#e21a1a'],
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 4 },
        tooltip: {
            custom: usageTooltip(categories),
            followCursor: true,
        },
    });
}

async function loadUsageForecast() {
    const data = await getJson(endpoints.usageForecast);
    const actual = data.actual || [];
    const forecast = data.forecast || [];
    const categories = actual.map(row => row.date).concat(forecast.map(row => row.date));
    if (!categories.length) return emptyChart('usageForecastChart');
    renderChart('usageForecastChart', {
        chart: { type: 'line', height: 320, toolbar: { show: false } },
        series: [
            { name: 'Actual Usage', data: actual.map(row => row.usage).concat(forecast.map(() => null)) },
            { name: 'Forecast Usage', data: actual.map(() => null).concat(forecast.map(row => row.forecast)) },
        ],
        stroke: { curve: 'smooth', width: 3, dashArray: [0, 5] },
        markers: { size: 3, hover: { size: 7 } },
        xaxis: {
            categories,
            tickPlacement: 'on',
            labels: {
                rotate: 0,
                trim: false,
                formatter: sparseCategoryFormatter(categories)
            },
            tooltip: { formatter: dateShort },
        },
        yaxis: { labels: { formatter: numberId } },
        annotations: { xaxis: [{ x: data.today, borderColor: '#dc2626', label: { text: 'Hari ini', orientation: 'horizontal', offsetY: -8 } }] },
        colors: ['#2563eb', '#dc2626'],
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 4 },
        tooltip: {
            shared: true,
            intersect: false,
            custom: forecastTooltip(categories),
        },
    });
}

async function loadRequestTrend() {
    const data = await getJson(endpoints.requestTrend);
    const monthly = data.monthly || [];
    if (!monthly.length) return emptyChart('requestTrendChart');
    renderChart('requestTrendChart', {
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        series: [{ name: 'Request', data: monthly.map(row => row.total) }],
        xaxis: { categories: monthly.map(row => monthShort(row.period)) },
        colors: ['#e21a1a'],
        dataLabels: { enabled: false },
    });
}

async function loadMeetingTrend() {
    const data = await getJson(endpoints.meetingTrend);
    const monthly = data.monthly || [];
    if (!monthly.length) return emptyChart('meetingTrendChart');
    renderChart('meetingTrendChart', {
        chart: { type: 'line', height: 300, toolbar: { show: false } },
        series: [
            { name: 'Request', data: monthly.map(row => row.requests) },
            { name: 'Peserta', data: monthly.map(row => row.participants) },
        ],
        xaxis: { categories: monthly.map(row => monthShort(row.period)) },
        colors: ['#0e7490', '#16a34a'],
        dataLabels: { enabled: false },
    });
}

async function loadReceivingTrend() {
    const data = await getJson(endpoints.receivingTrend);
    const monthly = data.monthly || [];
    if (!monthly.length) return emptyChart('receivingTrendChart');
    renderChart('receivingTrendChart', {
        chart: { type: 'bar', height: 300, stacked: true, toolbar: { show: false } },
        series: [
            { name: 'Diterima', data: monthly.map(row => row.received_qty) },
            { name: 'Ditolak', data: monthly.map(row => row.rejected_qty) },
        ],
        xaxis: { categories: monthly.map(row => monthShort(row.period)) },
        colors: ['#16a34a', '#dc2626'],
        dataLabels: { enabled: false },
    });
}

function riskBadge(level) {
    return `<span class="badge bg-${riskClass[level] || 'secondary'}-subtle text-${riskClass[level] || 'secondary'}">${level || '-'}</span>`;
}

function tableRange(state) {
    const rows = sortedTableRows(state);
    const total = rows.length;
    if (!total) return { total, from: 0, to: 0, totalPages: 1, rows: [] };

    const totalPages = Math.max(1, Math.ceil(total / state.pageSize));
    state.page = Math.min(Math.max(1, state.page), totalPages);
    const from = (state.page - 1) * state.pageSize;
    const to = Math.min(from + state.pageSize, total);

    return { total, from: from + 1, to, totalPages, rows: rows.slice(from, to) };
}

function sortedTableRows(state) {
    if (!state.sortKey) return state.rows;

    return [...state.rows].sort((a, b) => {
        const aValue = a[state.sortKey] ?? '';
        const bValue = b[state.sortKey] ?? '';
        const result = state.sortType === 'number'
            ? Number(aValue) - Number(bValue)
            : String(aValue).localeCompare(String(bValue), 'id', { numeric: true, sensitivity: 'base' });

        return state.direction === 'asc' ? result : -result;
    });
}

function renderPager(key, totalPages) {
    const state = tableState[key];
    const pagerId = key === 'stockForecast' ? 'stockForecastPager' : 'recommendationPager';
    const pager = document.getElementById(pagerId);
    const pages = [];

    if (totalPages <= 5) {
        for (let page = 1; page <= totalPages; page++) pages.push(page);
    } else {
        pages.push(1);
        if (state.page > 3) pages.push('...');
        const start = Math.max(2, state.page - 1);
        const end = Math.min(totalPages - 1, state.page + 1);
        for (let page = start; page <= end; page++) pages.push(page);
        if (state.page < totalPages - 2) pages.push('...');
        pages.push(totalPages);
    }

    pager.innerHTML = `
        <button type="button" class="btn btn-light border" data-table-page="${key}" data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}>
            <i class="bi bi-chevron-left"></i>
        </button>
        ${pages.map(page => page === '...'
            ? '<span class="px-2 text-muted fw-bold">...</span>'
            : `<button type="button" class="btn ${page === state.page ? 'btn-primary' : 'btn-light border'}" data-table-page="${key}" data-page="${page}">${page}</button>`
        ).join('')}
        <button type="button" class="btn btn-light border" data-table-page="${key}" data-page="${state.page + 1}" ${state.page >= totalPages ? 'disabled' : ''}>
            <i class="bi bi-chevron-right"></i>
        </button>
    `;
}

function renderPaginatedTable(key) {
    const state = tableState[key];
    const range = tableRange(state);

    if (key === 'stockForecast') {
        document.getElementById('stockForecastSummary').textContent = range.total
            ? `${numberId(range.total)} item aktif berdasarkan filter ${selectedStockLocationLabel()}`
            : 'Belum ada item aktif';
        document.getElementById('stockForecastPageInfo').textContent = range.total
            ? `Menampilkan ${numberId(range.from)} - ${numberId(range.to)} dari ${numberId(range.total)} item`
            : 'Tidak ada data untuk ditampilkan';
        document.getElementById('stockForecastRows').innerHTML = range.total ? range.rows.map(row => `
            <tr>
                <td><strong>${row.code}</strong><div class="text-muted small">${row.name}</div><div class="text-muted small">1 ${row.large_uom || '-'} = ${numberId(row.conversion_qty || 1)} ${row.small_uom || '-'}</div></td>
                <td class="text-end fw-bold">${numberId(row.current_stock)} ${row.stock_uom || ''}</td>
                <td class="text-end">${numberId(row.average_daily_usage)} ${row.stock_uom || ''}</td>
                <td>${row.estimated_stock_out_date || 'Belum ada tren pemakaian'}</td>
                <td class="text-end">${numberId(row.recommended_purchase_qty)} ${row.stock_uom || ''}</td>
                <td>${riskBadge(row.risk_level)}</td>
            </tr>
        `).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada item aktif.</td></tr>';
    } else {
        document.getElementById('recommendationSummary').textContent = range.total
            ? `${numberId(range.total)} item perlu dipertimbangkan`
            : 'Tidak ada rekomendasi saat ini';
        document.getElementById('recommendationPageInfo').textContent = range.total
            ? `Menampilkan ${numberId(range.from)} - ${numberId(range.to)} dari ${numberId(range.total)} rekomendasi`
            : 'Tidak ada data untuk ditampilkan';
        document.getElementById('recommendationRows').innerHTML = range.total ? range.rows.map(row => `
            <tr>
                <td><strong>${row.code}</strong><div class="text-muted small">${row.name}</div></td>
                <td class="text-end">${numberId(row.current_stock)} ${row.stock_uom || ''}</td>
                <td class="text-end fw-bold">${numberId(row.recommended_purchase_qty)} ${row.stock_uom || ''}</td>
                <td>${riskBadge(row.risk_level)}</td>
            </tr>
        `).join('') : '<tr><td colspan="4" class="text-center text-muted py-4">Belum ada rekomendasi pengadaan.</td></tr>';
    }

    renderPager(key, range.totalPages);
}

async function loadStockTables() {
    const [stockRows, recommendationRows] = await Promise.all([
        getJson(endpoints.stockForecast),
        getJson(endpoints.recommendations),
    ]);

    tableState.stockForecast.rows = stockRows;
    tableState.stockForecast.page = 1;
    tableState.recommendations.rows = recommendationRows;
    tableState.recommendations.page = 1;
    renderPaginatedTable('stockForecast');
    renderPaginatedTable('recommendations');
}

async function loadAnalytics() {
    clearError();
    try {
        await Promise.all([
            loadSummary(),
            loadUsageTrend(),
            loadUsageForecast(),
            loadRequestTrend(),
            loadMeetingTrend(),
            loadReceivingTrend(),
            loadStockTables(),
        ]);
    } catch (error) {
        showError(error.message || 'Gagal memuat data analytics.');
    }
}

document.getElementById('analyticsFilter').addEventListener('submit', event => {
    event.preventDefault();
    loadAnalytics();
});

document.getElementById('stockLocationFilter')?.addEventListener('change', () => {
    refreshStockLocationLabels();
    loadAnalytics();
});

$('.analytics-select').select2({
    theme: 'bootstrap-5',
    width: '100%',
    allowClear: true,
    placeholder: function () {
        return $(this).data('placeholder') || 'Pilih data';
    }
});

document.addEventListener('click', event => {
    const button = event.target.closest('[data-table-page]');
    if (!button || button.disabled) return;

    const key = button.dataset.tablePage;
    tableState[key].page = Number(button.dataset.page);
    renderPaginatedTable(key);
});

document.querySelectorAll('[data-table-page-size]').forEach(select => {
    select.addEventListener('change', event => {
        const key = event.target.dataset.tablePageSize;
        tableState[key].pageSize = Number(event.target.value);
        tableState[key].page = 1;
        renderPaginatedTable(key);
    });
});

document.addEventListener('click', event => {
    const header = event.target.closest('[data-table-sort]');
    if (!header) return;

    const key = header.dataset.tableSort;
    const state = tableState[key];
    state.direction = state.sortKey === header.dataset.sortKey && state.direction === 'asc' ? 'desc' : 'asc';
    state.sortKey = header.dataset.sortKey;
    state.sortType = header.dataset.sortType || 'text';
    state.page = 1;

    document.querySelectorAll(`[data-table-sort="${key}"] i`).forEach(icon => icon.className = 'bi bi-arrow-down-up text-muted');
    header.querySelector('i').className = `bi ${state.direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down'}`;
    renderPaginatedTable(key);
});

loadAnalytics();
</script>
@endsection
