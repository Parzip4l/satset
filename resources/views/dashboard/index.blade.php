@extends('partials.layouts.master')

@section('title', 'Dashboard | SatSet Admin & Dashboards')

@section('content')
<div id="layout-wrapper" class="pb-5">
    <div class="row mb-4 align-items-end">
        <div class="col-md-8">
            <h3 class="fw-bold text-dark mb-1">Dashboard Overview</h3>
            <p class="text-muted mb-0">Selamat datang kembali, <span class="text-primary fw-semibold">{{ auth()->user()->name }}</span>. Berikut ringkasan aktivitas ticket Anda.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="btn-group shadow-sm bg-white rounded-3 p-1">
                <span class="btn btn-link text-muted text-decoration-none fs-13 fw-bold border-0 disabled">TAHUN:</span>
                <select class="form-select form-select-sm border-0 fw-bold text-primary focus-none" style="cursor: pointer;" onchange="updateChartByYear(this.value)">
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" {{ ($selectedYear ?? now()->year) == $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="row mb-2">
        @php
            $stats = [
                ['label' => 'Total Tickets', 'value' => $totalRequests ?? 0, 'icon' => 'ri-ticket-2-fill', 'color' => '#3b71fe', 'bg' => 'primary'],
                ['label' => 'Open', 'value' => $openRequests ?? 0, 'icon' => 'ri-error-warning-fill', 'color' => '#ffc107', 'bg' => 'warning'],
                ['label' => 'In Progress', 'value' => $inProgressRequests ?? 0, 'icon' => 'ri-time-fill', 'color' => '#0dcaf0', 'bg' => 'info'],
                ['label' => 'Resolved', 'value' => $closedRequests ?? 0, 'icon' => 'ri-checkbox-circle-fill', 'color' => '#198754', 'bg' => 'success'],
            ];
        @endphp

        @foreach($stats as $stat)
        <div class="col-xxl-3 col-sm-6 mb-4">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-3 bg-{{ $stat['bg'] }}-subtle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                                <i class="{{ $stat['icon'] }} fs-2 text-{{ $stat['bg'] }}"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-1 fs-14 fw-medium">{{ $stat['label'] }}</p>
                            <h3 class="mb-0 fw-bold" style="letter-spacing: -0.5px;">{{ number_format($stat['value']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="position-absolute end-0 bottom-0 text-{{ $stat['bg'] }} opacity-10 mb-n3 me-n2" style="font-size: 70px; transform: rotate(-15deg);">
                    <i class="{{ $stat['icon'] }}"></i>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if(strtolower(auth()->user()->role) === 'admin')
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex align-items-center">
                    <h5 class="fw-bold text-dark mb-0">Tren Ticket Bulanan</h5>
                    <div class="ms-auto">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3">Real-time Data</span>
                    </div>
                </div>
                <div class="card-body px-3">
                    <div id="chart-laporan-bulanan"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold text-dark mb-0">Distribusi Status</h5>
                </div>
                <div class="card-body pt-0">
                    <div id="chart-status"></div>
                    
                    <div class="mt-2 p-3 bg-light rounded-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fs-13"><i class="ri-history-line me-1"></i> Ticket Terlama:</span>
                            <span class="fw-bold text-dark fs-13">{{ $oldestTicketDate ?? '-' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted fs-13"><i class="ri-flashlight-line me-1"></i> Avg. Response:</span>
                            <span class="fw-bold text-success fs-13">± 2.4 Jam</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Tiket Terbaru</h5>
                        <p class="text-muted fs-13 mb-0">Daftar 10 aktivitas tiket paling update.</p>
                    </div>
                    <div class="ms-auto">
                        <a href="{{ route('ticket.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                            Lihat Semua <i class="ri-arrow-right-s-line ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light-subtle">
                                <tr>
                                    <th class="ps-4 py-3 text-muted fw-bold fs-12 text-uppercase">Informasi Ticket</th>
                                    <th class="py-3 text-muted fw-bold fs-12 text-uppercase">Requester</th>
                                    <th class="py-3 text-muted fw-bold fs-12 text-uppercase text-center">Department</th>
                                    <th class="py-3 text-muted fw-bold fs-12 text-uppercase text-center">Status</th>
                                    <th class="py-3 text-muted fw-bold fs-12 text-uppercase">Dibuat Pada</th>
                                    <th class="pe-4 py-3 text-muted fw-bold fs-12 text-uppercase text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentRequests as $t)
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-3">
                                                <div class="avatar-title rounded bg-light text-primary fw-bold fs-10">
                                                    #{{ substr($t->id, -3) }}
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold fs-14 text-dark">
                                                    {{ \Illuminate\Support\Str::limit($t->title, 35) }}
                                                </h6>
                                                <span class="fs-12 text-muted">Ticket ID: #{{ $t->id }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs-2 rounded-circle bg-soft-primary text-primary border border-primary-subtle d-flex align-items-center justify-content-center fw-bold fs-10 me-2" style="width: 24px; height: 24px;">
                                                {{ substr($t->requester->name ?? '?', 0, 1) }}
                                            </div>
                                            <span class="fs-13 fw-medium text-muted">{{ $t->requester->name ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center fs-13 text-muted">{{ $t->department->name ?? '-' }}</td>
                                    <td class="text-center">
                                        @php
                                            $statusMap = ['Open'=>'warning','In Progress'=>'info','Closed'=>'success'];
                                            $color = $statusMap[$t->status->name ?? ''] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} rounded-pill px-3 py-2 fw-bold fs-11" style="min-width: 90px;">
                                            {{ $t->status->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="fs-13 text-muted">
                                        {{ $t->created_at->translatedFormat('d M Y') }}
                                        <div class="fs-11 text-muted-opacity">{{ $t->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <a href="/ticket/{{ $t->id }}" class="btn btn-light btn-sm rounded-circle shadow-none border-0" data-bs-toggle="tooltip" title="Lihat Detail">
                                            <i class="ri-external-link-line text-primary fs-16"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <img src="assets/images/no-data.png" alt="No data" height="100">
                                        <p class="text-muted mt-3">Tidak ada data ticket terbaru untuk ditampilkan.</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .focus-none:focus { box-shadow: none; }
    .text-muted-opacity { opacity: 0.6; }
    .bg-soft-primary { background-color: rgba(59, 113, 254, 0.1); }
</style>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    @if(strtolower(auth()->user()->role) === 'admin')
    // Area Chart - Monthly Activity
    var areaOptions = {
        chart: { type: 'area', height: 350, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
        series: [{ name: 'Tickets Masuk', data: {!! json_encode($monthlyData ?? []) !!} }],
        xaxis: { 
            categories: {!! json_encode($monthlyLabels ?? []) !!},
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: { labels: { style: { colors: '#adb5bd' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4, padding: { left: 20, right: 20 } },
        colors: ['#3b71fe'],
        dataLabels: { enabled: false },
        tooltip: { theme: 'light', x: { show: true } }
    };
    new ApexCharts(document.querySelector("#chart-laporan-bulanan"), areaOptions).render();

    // Donut Chart - Status Distribution
    var donutOptions = {
        chart: { type: 'donut', height: 280, fontFamily: 'Inter, sans-serif' },
        series: {!! json_encode($statusSeries ?? []) !!},
        labels: {!! json_encode($statusLabels ?? []) !!},
        colors: ['#ffc107', '#0dcaf0', '#198754'],
        legend: { position: 'bottom', fontSize: '12px' },
        plotOptions: { 
            pie: { 
                donut: { 
                    size: '72%',
                    labels: {
                        show: true,
                        total: { show: true, label: 'Total', fontSize: '14px', fontWeight: 600, color: '#adb5bd' }
                    }
                } 
            } 
        },
        stroke: { width: 0 },
        dataLabels: { enabled: false }
    };
    new ApexCharts(document.querySelector("#chart-status"), donutOptions).render();
    @endif

    function updateChartByYear(year){
        window.location.href = "{{ route('dashboard.index') }}?tahun=" + year;
    }
</script>
@endsection