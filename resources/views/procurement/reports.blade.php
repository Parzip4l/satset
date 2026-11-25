@extends('partials.layouts.master')
@section('title', 'Laporan Pengadaan Barang')
@section('pagetitle', 'Laporan Pengadaan Barang')
@section('title-sub', 'Procurement')
@section('css')
<style>
.pgb-report { --pgb-ink:#243044; --pgb-muted:#68768a; --pgb-line:#e5eaf0; color:var(--pgb-ink); }
.pgb-report .report-hero { background:linear-gradient(115deg,#202b3d,#38485e); color:white; border-radius:16px; padding:26px; }
.pgb-report .report-eyebrow { color:#fca5a5; font-size:.72rem; letter-spacing:.12em; font-weight:700; text-transform:uppercase; }
.pgb-report .report-hero p { color:#d0d9e5; max-width:740px; }
.pgb-report .report-card { background:#fff; border:1px solid var(--pgb-line); border-radius:13px; padding:20px; height:100%; }
.pgb-report .report-label { color:var(--pgb-muted); font-size:.8rem; margin-bottom:9px; }
.pgb-report .report-value { font-size:clamp(1.1rem,1.9vw,1.65rem); font-weight:750; overflow-wrap:anywhere; letter-spacing:-.025em; }
.pgb-report .report-note { color:var(--pgb-muted); font-size:.75rem; line-height:1.55; }
.pgb-report .report-section { font-size:1rem; font-weight:700; margin-bottom:6px; }
.pgb-report .report-insight { border-left:3px solid #e21a1a; background:#fff6f6; padding:16px 18px; border-radius:0 10px 10px 0; }
.pgb-report .report-chart { height:300px; width:100%; }
.pgb-report .report-money { white-space:nowrap; font-variant-numeric:tabular-nums; }
.pgb-report .report-table th { color:var(--pgb-muted); font-size:.73rem; background:#f6f8fb; white-space:nowrap; }
.pgb-report .report-table td { font-size:.82rem; vertical-align:middle; padding-top:14px; padding-bottom:14px; }
.pgb-report .report-status { display:flex; justify-content:space-between; gap:12px; border:1px solid var(--pgb-line); border-radius:8px; padding:10px 12px; color:var(--pgb-ink); text-decoration:none; font-size:.8rem; }
.pgb-report .report-status:hover { background:#f6f8fb; }
.pgb-report .report-share { height:4px; background:#edf0f5; width:90px; border-radius:4px; margin-top:5px; }
.pgb-report .report-share > span { display:block; background:#e21a1a; height:100%; border-radius:4px; }
@media print {
    @page { size:A4 landscape; margin:12mm; }
    .app-header,.pe-app-sidebar,.sidebar-backdrop,.app-footer,#progress-scroll,.report-controls,.pagination,.pe-app-sidebar.horizontal-sidebar { display:none!important; }
    main.app-wrapper { margin:0!important; padding:0!important; }
    body,.pgb-report { background:white!important; font-size:10pt; }
    .pgb-report .report-hero { color:#202b3d!important; background:#f2f4f8!important; padding:16px; }
    .pgb-report .report-hero p,.pgb-report .report-eyebrow { color:#455468!important; }
    .pgb-report .report-hero h1 { color:#202b3d!important; }
    .pgb-report .report-card { break-inside:avoid; padding:12px; }
    .pgb-report .table-responsive { overflow:visible!important; }
    .pgb-report .report-value { font-size:16pt; }
    .pgb-report .report-chart { height:220px; }
    .pgb-report a { color:inherit; text-decoration:none; }
    .pgb-report thead { display:table-header-group; }
}
</style>
@endsection
@section('content')
@php
    $summary = $report['summary'];
    $rp = fn ($cents) => 'Rp'.number_format($cents / 100, 2, ',', '.');
    $exportFilters = array_filter(\Illuminate\Support\Arr::except($filters, ['page']), fn ($value) => $value !== null && $value !== '');
    $rankingLabel = \App\Services\ProcurementReportService::RANKINGS[$filters['ranking']];
    $topDepartment = $report['departmentRows'][0] ?? null;
    $topRequester = $report['requesterRows'][0] ?? null;
    $metric = fn ($row) => $filters['ranking'] === 'count' ? number_format($row['count']).' pengajuan' : $rp($row[$filters['ranking']]);
@endphp
<div class="container-fluid py-4 pgb-report">
    <header class="report-hero d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div><div class="report-eyebrow mb-2">Management report · Pengadaan Barang</div><h1 class="h3 text-white mb-2">Kebutuhan, anggaran, dan pemenuhan</h1><p class="mb-2 small">Pantau kontribusi setiap departemen, pemohon dengan nilai tertinggi, serta kebutuhan yang perlu segera ditindaklanjuti.</p><div class="small">Tanggal pengajuan: {{ \Carbon\Carbon::parse($filters['from'])->format('d M Y') }} — {{ \Carbon\Carbon::parse($filters['to'])->format('d M Y') }}</div></div>
        <div class="report-controls d-flex flex-wrap gap-2 align-items-start"><a href="{{ route('procurement.index') }}" class="btn btn-light btn-sm">Daftar Pengadaan</a><button type="button" class="btn btn-outline-light btn-sm" onclick="window.print()">Cetak / PDF</button></div>
    </header>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form action="{{ route('procurement.reports') }}" method="GET" class="report-card report-controls mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-xl-2"><label for="report-from" class="form-label small">Tanggal pengajuan mulai</label><input id="report-from" name="from" type="date" class="form-control" value="{{ $filters['from'] }}" required></div>
            <div class="col-sm-6 col-xl-2"><label for="report-to" class="form-label small">Sampai tanggal</label><input id="report-to" name="to" type="date" class="form-control" value="{{ $filters['to'] }}" required></div>
            <div class="col-sm-6 col-xl-3"><label for="report-department" class="form-label small">Departemen</label><select id="report-department" name="department" class="form-select"><option value="">Semua departemen</option>@foreach($report['departments'] as $department)<option value="{{ $department }}" @selected(strcasecmp($filters['department'] ?? '', $department) === 0)>{{ $department }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-xl-3"><label for="report-status" class="form-label small">Status saat ini</label><select id="report-status" name="status" class="form-select"><option value="">Semua status</option>@foreach(\App\Services\ProcurementReportService::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-xl-2"><label for="report-ranking" class="form-label small">Urutkan peringkat</label><select id="report-ranking" name="ranking" class="form-select">@foreach(\App\Services\ProcurementReportService::RANKINGS as $value => $label)<option value="{{ $value }}" @selected($filters['ranking'] === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary">Tampilkan Laporan</button><a href="{{ route('procurement.reports') }}" class="btn btn-outline-secondary">Reset</a><a href="{{ route('procurement.reports.export', $exportFilters + ['kind' => 'departments']) }}" class="btn btn-outline-primary ms-sm-auto">Unduh Rekap Departemen</a><a href="{{ route('procurement.reports.export', $exportFilters + ['kind' => 'requests']) }}" class="btn btn-outline-primary">Unduh Rincian CSV</a></div>
        </div>
    </form>
    <div class="report-note mb-4">Periode mengikuti <strong>tanggal pengajuan</strong>. Status, pemenuhan, dan dana bersifat kumulatif hingga {{ $report['generated_at']->format('d M Y H:i') }}. Anggaran diajukan mencakup pengajuan yang ditolak. Dana diberikan mencatat penyerahan dana; penyerahan stok ditampilkan sebagai qty dan belum dinilai sebagai biaya pembelian.</div>
    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Total pengajuan', 'value' => number_format($summary['count']), 'note' => $summary['active'].' aktif · '.$summary['rejected'].' ditolak'],
            ['label' => 'Anggaran diajukan', 'value' => $rp($summary['budget']), 'note' => 'Jumlah estimasi seluruh baris kebutuhan'],
            ['label' => 'Anggaran disetujui atasan', 'value' => $rp($summary['approved_budget']), 'note' => 'Nilai pengajuan yang disetujui kadep/manager'],
            ['label' => 'Dana sudah diberikan', 'value' => $rp($summary['funds']), 'note' => 'Nominal penyerahan dana yang tercatat'],
            ['label' => 'Estimasi kebutuhan terbuka', 'value' => $rp($summary['outstanding_budget']), 'note' => 'Prorata anggaran dari qty aktif yang belum terpenuhi'],
            ['label' => 'Menunggu persetujuan', 'value' => number_format($summary['pending']), 'note' => $rp($summary['pending_budget']).' anggaran menunggu atasan'],
            ['label' => 'Tingkat penyelesaian', 'value' => $summary['completion_rate'].'%', 'note' => $summary['completed'].' selesai · rata-rata '.($summary['average_completion_days'] === null ? 'belum tersedia' : $summary['average_completion_days'].' hari')],
            ['label' => 'Pengajuan aktif ≥ 7 hari', 'value' => number_format($summary['aging_count']), 'note' => $summary['stock_quantity'].' qty stok diserahkan · '.$summary['funded_quantity'].' qty dicakup dana'],
        ] as $card)
            <div class="col-sm-6 col-xl-3"><div class="report-card"><div class="report-label">{{ $card['label'] }}</div><div class="report-value mb-2">{{ $card['value'] }}</div><div class="report-note">{{ $card['note'] }}</div></div></div>
        @endforeach
    </div>
    @if($summary['count'] === 0)
        <div class="report-card text-center py-5 mb-4"><i class="bi bi-bar-chart-line fs-1 text-muted"></i><h2 class="h5 mt-3">Belum ada pengajuan pada filter ini</h2><p class="text-muted mb-0">Ubah periode, departemen, atau status untuk menampilkan laporan Pengadaan Barang.</p></div>
    @else
        <div class="report-insight mb-4"><strong>Fokus manajemen</strong><div class="small mt-2">Berdasarkan {{ strtolower($rankingLabel) }}, departemen tertinggi adalah <strong>{{ $topDepartment['label'] }}</strong> ({{ $metric($topDepartment) }}). Pemohon tertinggi adalah <strong>{{ $topRequester['label'] }}</strong> dari {{ $topRequester['department'] }} ({{ $metric($topRequester) }}). Ada <strong>{{ $summary['aging_count'] }} pengajuan aktif berusia minimal 7 hari</strong> untuk ditinjau.</div></div>
    @endif
    <div class="row g-3 mb-4">
        <section class="col-xl-6"><div class="report-card"><h2 class="report-section">10 departemen tertinggi</h2><p class="report-note">Urutan berdasarkan {{ strtolower($rankingLabel) }} pada filter terpilih.</p><div id="procurement-department-chart" class="report-chart" role="img" aria-label="Grafik peringkat departemen. Nilai lengkap tersedia pada tabel rekap."></div></div></section>
        <section class="col-xl-6"><div class="report-card"><h2 class="report-section">Tren berdasarkan bulan pengajuan</h2><p class="report-note">Anggaran diajukan dan dana kumulatif untuk pengajuan pada setiap bulan, dalam Rupiah.</p><div id="procurement-month-chart" class="report-chart" role="img" aria-label="Grafik tren bulanan. Nilai lengkap tersedia pada tabel tren."></div></div></section>
    </div>
    <section class="report-card mb-4"><h2 class="report-section">Rekap per departemen</h2><p class="report-note">Klik departemen untuk melihat seluruh pengajuannya. Porsi dihitung dari anggaran diajukan pada filter saat ini.</p><div class="table-responsive"><table class="table report-table align-middle"><thead><tr><th>Peringkat / Departemen</th><th class="text-end">Pengajuan</th><th class="text-end">Anggaran diajukan</th><th class="text-end">Disetujui atasan</th><th class="text-end">Dana diberikan</th><th class="text-end">Estimasi terbuka</th><th>Porsi anggaran</th><th>Selesai / Aktif / Ditolak</th></tr></thead><tbody>
    @forelse($report['departmentRows'] as $index => $row)
        @php($share = $summary['budget'] > 0 ? round($row['budget'] / $summary['budget'] * 100, 1) : 0)
        <tr><td><span class="text-muted me-2">{{ $index + 1 }}</span><a href="{{ route('procurement.reports', array_merge($exportFilters, ['department' => $row['label']])) }}">{{ $row['label'] }}</a></td><td class="text-end">{{ $row['count'] }}</td><td class="text-end report-money">{{ $rp($row['budget']) }}</td><td class="text-end report-money">{{ $rp($row['approved_budget']) }}</td><td class="text-end report-money">{{ $rp($row['funds']) }}</td><td class="text-end report-money">{{ $rp($row['outstanding_budget']) }}</td><td>{{ $share }}%<div class="report-share"><span style="width:{{ $share }}%"></span></div></td><td>{{ $row['completed'] }} / {{ $row['active'] }} / {{ $row['rejected'] }}</td></tr>
    @empty<tr><td colspan="8" class="text-center text-muted py-4">Belum ada data departemen.</td></tr>@endforelse
    </tbody><tfoot><tr class="fw-bold"><td>Total</td><td class="text-end">{{ $summary['count'] }}</td><td class="text-end report-money">{{ $rp($summary['budget']) }}</td><td class="text-end report-money">{{ $rp($summary['approved_budget']) }}</td><td class="text-end report-money">{{ $rp($summary['funds']) }}</td><td class="text-end report-money">{{ $rp($summary['outstanding_budget']) }}</td><td></td><td>{{ $summary['completed'] }} / {{ $summary['active'] }} / {{ $summary['rejected'] }}</td></tr></tfoot></table></div></section>
    <div class="row g-3 mb-4">
        <section class="col-xl-8"><div class="report-card"><h2 class="report-section">10 pemohon tertinggi</h2><p class="report-note">Peringkat berdasarkan {{ strtolower($rankingLabel) }}; pemohon dipisahkan menurut departemen saat pengajuan.</p><div class="table-responsive"><table class="table report-table"><thead><tr><th>Pemohon / Departemen</th><th class="text-end">Pengajuan</th><th class="text-end">Anggaran</th><th class="text-end">Dana diberikan</th></tr></thead><tbody>@forelse(array_slice($report['requesterRows'], 0, 10) as $index => $row)<tr><td>{{ $index + 1 }}. {{ $row['label'] }}<div class="report-note">{{ $row['department'] }}</div></td><td class="text-end">{{ $row['count'] }}</td><td class="text-end report-money">{{ $rp($row['budget']) }}</td><td class="text-end report-money">{{ $rp($row['funds']) }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-4">Belum ada data pemohon.</td></tr>@endforelse</tbody></table></div></div></section>
        <section class="col-xl-4"><div class="report-card"><h2 class="report-section">Distribusi status</h2><p class="report-note">Klik status untuk memfilter laporan.</p><div class="d-grid gap-2">@foreach($report['distribution'] as $row)<a class="report-status" href="{{ route('procurement.reports', array_merge($exportFilters, ['status' => $row['status']])) }}"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></a>@endforeach</div></div></section>
    </div>
    <section class="report-card mb-4"><h2 class="report-section">Pengajuan aktif terlama</h2><p class="report-note">Maksimal 10 pengajuan, diurutkan berdasarkan umur sejak diajukan. Usia minimal 7 hari diberi penanda.</p><div class="table-responsive"><table class="table report-table"><thead><tr><th>Pengajuan</th><th>Pemohon / Departemen</th><th>Status</th><th class="text-end">Usia</th><th class="text-end">Estimasi kebutuhan terbuka</th></tr></thead><tbody>@forelse($report['backlog']->take(10) as $row)<tr><td><a href="{{ route('ticket.show', $row['ticket']) }}">{{ $row['ticket_no'] }}</a><div class="report-note">{{ $row['submitted_at']->format('d M Y') }}</div></td><td>{{ $row['requester_name'] }}<div class="report-note">{{ $row['department'] }}</div></td><td>{{ $row['status_label'] }}</td><td class="text-end"><span class="badge {{ $row['age_days'] >= 7 ? 'bg-warning text-dark' : 'bg-light text-dark' }}">{{ $row['age_days'] }} hari</span></td><td class="text-end report-money">{{ $rp($row['outstanding_budget']) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada pengajuan aktif pada filter ini.</td></tr>@endforelse</tbody></table></div></section>
    <section class="report-card mb-4"><h2 class="report-section">Rekap bulanan</h2><p class="report-note">Dana pada tabel ini dikelompokkan berdasarkan bulan pengajuan, kumulatif hingga laporan dibuat.</p><div class="table-responsive"><table class="table report-table"><thead><tr><th>Bulan pengajuan</th><th class="text-end">Pengajuan</th><th class="text-end">Anggaran diajukan</th><th class="text-end">Dana diberikan</th><th class="text-end">Selesai</th></tr></thead><tbody>@foreach($report['months'] as $row)<tr><td>{{ $row['label'] }}</td><td class="text-end">{{ $row['count'] }}</td><td class="text-end report-money">{{ $rp($row['budget']) }}</td><td class="text-end report-money">{{ $rp($row['funds']) }}</td><td class="text-end">{{ $row['completed'] }}</td></tr>@endforeach</tbody></table></div></section>
    <section class="report-card mb-4"><h2 class="report-section">Rincian pengajuan</h2><p class="report-note">{{ $details->firstItem() ?? 0 }}–{{ $details->lastItem() ?? 0 }} dari {{ $details->total() }} pengajuan. Unduhan CSV mencakup seluruh hasil filter.</p><div class="table-responsive"><table class="table report-table"><thead><tr><th>Pengajuan / Tanggal</th><th>Pemohon / Departemen</th><th>Status</th><th class="text-end">Anggaran diajukan</th><th class="text-end">Dana diberikan</th><th class="text-end">Qty stok</th><th class="text-end">Estimasi terbuka</th></tr></thead><tbody>@forelse($details as $row)<tr><td><a href="{{ route('ticket.show', $row['ticket']) }}">{{ $row['ticket_no'] }}</a><div class="report-note">{{ $row['submitted_at']->format('d M Y') }} · {{ $row['title'] }}</div></td><td>{{ $row['requester_name'] }}<div class="report-note">{{ $row['department'] }}</div></td><td>{{ $row['status_label'] }}</td><td class="text-end report-money">{{ $rp($row['budget']) }}</td><td class="text-end report-money">{{ $rp($row['funds']) }}</td><td class="text-end">{{ $row['stock_quantity'] }}</td><td class="text-end report-money">{{ $rp($row['outstanding_budget']) }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan.</td></tr>@endforelse</tbody></table></div><div class="report-controls">{{ $details->links() }}</div></section>
    <div class="report-note text-end">Laporan khusus Pengadaan Barang · Dibuat {{ $report['generated_at']->format('d M Y H:i') }}</div>
</div>
@endsection
@section('js')
<script src="{{ asset('assets/libs/echarts/echarts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const departments = @json(array_slice($report['departmentRows'], 0, 10));
    const months = @json($report['months']);
    const ranking = @json($filters['ranking']);
    const rankingLabel = @json($rankingLabel);
    const money = value => new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:2}).format(value);
    const compact = value => new Intl.NumberFormat('id-ID', {notation:'compact',maximumFractionDigits:1}).format(value);
    const deptChart = echarts.init(document.getElementById('procurement-department-chart'));
    const monthChart = echarts.init(document.getElementById('procurement-month-chart'));
    deptChart.setOption({aria:{enabled:true},grid:{left:12,right:45,top:15,bottom:25,containLabel:true},tooltip:{trigger:'axis',renderMode:'richText',valueFormatter:value => ranking === 'count' ? value + ' pengajuan' : money(value)},xAxis:{type:'value',axisLabel:{formatter:compact}},yAxis:{type:'category',inverse:true,data:departments.map(row=>row.label),axisLabel:{width:135,overflow:'truncate'}},series:[{name:rankingLabel,type:'bar',barMaxWidth:22,itemStyle:{color:'#d92635',borderRadius:[0,4,4,0]},data:departments.map(row=>ranking === 'count' ? row.count : row[ranking]/100)}],graphic:departments.length ? [] : [{type:'text',left:'center',top:'middle',style:{text:'Belum ada data',fill:'#68768a'}}]});
    monthChart.setOption({aria:{enabled:true},color:['#d92635','#31828a'],legend:{bottom:0},grid:{left:12,right:15,top:20,bottom:60,containLabel:true},tooltip:{trigger:'axis',renderMode:'richText',valueFormatter:money},xAxis:{type:'category',data:months.map(row=>row.label),axisLabel:{hideOverlap:true}},yAxis:{type:'value',axisLabel:{formatter:compact}},series:[{name:'Anggaran diajukan',type:'bar',barMaxWidth:25,data:months.map(row=>row.budget/100)},{name:'Dana diberikan',type:'line',smooth:false,data:months.map(row=>row.funds/100)}]});
    const resize = () => {deptChart.resize();monthChart.resize();};
    window.addEventListener('resize', resize);
    window.addEventListener('beforeprint', resize);
    window.addEventListener('afterprint', resize);
});
</script>
@endsection
