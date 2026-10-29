@extends('partials.layouts.master')
@section('title', 'Layanan IT')
@section('pagetitle', 'Layanan IT')
@section('title-sub', 'Layanan IT')

@section('css')
<style>
    .it-ticket-page .it-header-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
    .it-ticket-page .it-header-actions .btn { white-space:nowrap; }
    .it-ticket-page .it-filter-search { position:relative; }
    .it-ticket-page .it-filter-search i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#7c8794; pointer-events:none; }
    .it-ticket-page .it-filter-search input { padding-left:40px; }
    .it-ticket-page .it-ticket-info { min-width:240px; }
    .it-ticket-page .it-workflow-cell { min-width:210px; }
    .it-ticket-page .it-workflow-icon { width:34px; height:34px; flex:0 0 34px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; }
    .it-ticket-page .it-workflow-label { font-size:.82rem; line-height:1.25; }
    .it-ticket-page .it-workflow-detail { font-size:.7rem; line-height:1.35; }
    .it-ticket-page .it-ticket-number { font-size:.76rem; }
    .it-ticket-page .it-table-heading { font-size:.69rem; letter-spacing:.04em; }
    .it-ticket-page .it-empty-icon { width:48px; height:48px; display:inline-flex; align-items:center; justify-content:center; border-radius:14px; background:#f2f5f8; color:#7a8998; }
    @media (max-width:767px) { .it-ticket-page .it-header-actions { justify-content:flex-start; } }
</style>
@endsection

@section('content')
@php
    $statusLabels = [
        'submitted' => ['Diajukan', 'Tiket telah tercatat.', 'primary', 'bi-inbox'],
        'triage' => ['Perlu triage', 'Menunggu penilaian dan penentuan tim.', 'warning', 'bi-clipboard-check'],
        'queued' => ['Dalam antrean', 'Menunggu atau siap ditangani petugas.', 'primary', 'bi-inbox'],
        'awaiting_approval' => ['Menunggu persetujuan', 'Permintaan sedang menunggu keputusan.', 'warning', 'bi-hourglass-split'],
        'approval_exception' => ['Perlu tindak lanjut', 'Penentuan persetujuan perlu diperiksa.', 'danger', 'bi-exclamation-circle'],
        'needs_revision' => ['Perlu revisi', 'Pemohon diminta memperbarui permintaan.', 'warning', 'bi-pencil-square'],
        'in_progress' => ['Dalam penanganan', 'Petugas sedang menindaklanjuti tiket.', 'info', 'bi-arrow-repeat'],
        'pending' => ['Menunggu', 'Penanganan tertunda sementara.', 'warning', 'bi-pause-circle'],
        'resolved' => ['Menunggu konfirmasi', 'Penyelesaian telah dikirim ke pemohon.', 'success', 'bi-check2-circle'],
        'closed' => ['Selesai', 'Tiket telah ditutup.', 'success', 'bi-check2-circle'],
        'cancelled' => ['Dibatalkan', 'Permintaan telah dibatalkan.', 'secondary', 'bi-x-circle'],
        'rejected' => ['Ditolak', 'Permintaan tidak disetujui.', 'danger', 'bi-x-circle'],
    ];
@endphp
<div class="container-fluid it-ticket-page">
    <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div><h4 class="fw-bold mb-1">Layanan IT</h4><p class="text-muted mb-0">Pantau gangguan dan permintaan layanan teknologi informasi.</p></div>
                <div class="it-header-actions">
                    <a href="{{ route('ticket.index') }}" class="btn btn-light border shadow-sm"><i class="bi bi-grid me-1" aria-hidden="true"></i> Menu Tiket</a>
                    @if(\App\Support\ItAccess::operational(auth()->user()))<a href="{{ route('it.operations') }}" class="btn btn-light border shadow-sm">Operasional IT</a>@endif
                    @if(\Illuminate\Support\Facades\Schema::hasTable('it_sla_clocks') && (\App\Support\ItAccess::operational(auth()->user()) || \App\Support\ItAccess::can(auth()->user(), 'view_it_queue') || \App\Support\ItAccess::can(auth()->user(), 'manage_it_configuration')))<a href="{{ route('it.dashboard') }}" class="btn btn-light border shadow-sm">Dashboard IT</a>@endif
                    <a href="{{ route('it.notifications') }}" class="btn btn-light border shadow-sm">Notifikasi IT</a>
                    <a href="{{ route('it.approvals') }}" class="btn btn-light border shadow-sm">Persetujuan Saya</a>
                    @if($enabled)<a href="{{ route('it.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Buat Tiket IT</a>@endif
                </div>
            </div>
        </div>
    </div>

    @if(!$enabled)<div class="alert alert-info mb-4">Pengajuan IT belum dibuka untuk akun ini. Tiket yang sudah tercatat tetap dapat dipantau sesuai hak akses Anda.</div>@endif
    @if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif

    <div class="card border-0 mb-4 shadow-sm" style="border-radius:12px">
        <div class="card-body p-3">
            <form method="get" action="{{ route('it.index') }}" class="row g-3 align-items-center">
                <div class="col-md-4 col-xl-3"><div class="it-filter-search"><i class="bi bi-search" aria-hidden="true"></i><input name="q" class="form-control" placeholder="Cari nomor atau judul tiket..." value="{{ request('q') }}" aria-label="Cari nomor atau judul tiket"></div></div>
                <div class="col-md-4 col-xl-3"><select class="form-select" name="scope" aria-label="Lingkup tiket"><option value="">Semua yang dapat saya akses</option>@foreach(['mine'=>'Tiket Saya','assigned'=>'Ditugaskan kepada Saya','queue'=>'Antrean tim / exception'] as $key=>$label)<option value="{{ $key }}" @selected(request('scope')===$key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4 col-xl-2"><select class="form-select" name="priority" aria-label="Prioritas"><option value="">Semua Prioritas</option>@foreach(['P1','P2','P3','P4'] as $priority)<option value="{{ $priority }}" @selected(request('priority')===$priority)>{{ $priority }}</option>@endforeach</select></div>
                <div class="col-md-4 col-xl-2"><select class="form-select" name="status" aria-label="Status tiket"><option value="">Semua Status</option>@foreach($statusLabels as $key=>$status)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $status[0] }}</option>@endforeach</select></div>
                <div class="col-6 col-md-4 col-xl-1"><button class="btn btn-primary w-100">Cari</button></div>
                <div class="col-6 col-md-4 col-xl-1"><a href="{{ route('it.index') }}" class="btn btn-outline-danger w-100">Reset</a></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:12px;overflow:hidden">
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted text-uppercase fw-bold it-table-heading"><tr><th class="ps-4 py-3">Informasi Tiket</th><th class="py-3">Pemohon</th><th class="py-3">Prioritas</th><th class="py-3">Proses Saat Ini</th><th class="py-3">Tgl Dibuat</th><th class="text-end pe-4 py-3">Aksi</th></tr></thead>
            <tbody class="border-top-0">
            @forelse($tickets as $ticket)
                @php
                    $detail = $ticket->itDetail;
                    $state = strtolower(str_replace([' ', '-'], '_', $detail?->status ?? ''));
                    $workflow = $statusLabels[$state] ?? ['Status tidak tersedia', 'Lihat detail untuk informasi tiket.', 'secondary', 'bi-info-circle'];
                    $priority = $detail?->priority;
                    $priorityColor = match($priority) { 'P1' => 'danger', 'P2' => 'warning', 'P3' => 'primary', 'P4' => 'info', default => 'secondary' };
                    $type = match($detail?->record_type) { 'incident' => 'Incident', 'service_request' => 'Service Request', default => 'Tiket IT' };
                @endphp
                <tr>
                    <td class="ps-4 py-3 it-ticket-info"><div class="d-flex align-items-center gap-3"><span class="avatar-sm bg-soft-{{ $workflow[2] }} text-{{ $workflow[2] }} rounded d-flex align-items-center justify-content-center" style="min-width:40px;height:40px"><i class="bi bi-ticket-perforated fs-5" aria-hidden="true"></i></span><div><h6 class="fw-bold mb-0 text-dark">{{ $ticket->title }}</h6><div class="d-flex flex-wrap align-items-center gap-2 mt-1"><small class="text-muted fw-medium it-ticket-number">{{ $ticket->ticket_no }}</small><span class="badge bg-light text-dark border">{{ $type }}</span>@if(!$detail?->catalog_key)<span class="badge bg-light text-muted border">Mode baca</span>@endif</div></div></div></td>
                    <td><span class="fw-semibold text-dark">{{ $ticket->requester?->name ?? 'Tidak tersedia' }}</span></td>
                    <td>@if($priority)<div class="d-flex align-items-center gap-1"><span class="badge badge-dot bg-{{ $priorityColor }}"></span><span class="text-dark fw-medium">{{ $priority }}</span></div>@else<span class="text-muted">—</span>@endif</td>
                    <td class="it-workflow-cell"><div class="d-flex align-items-start gap-2"><span class="it-workflow-icon bg-{{ $workflow[2] }}-subtle text-{{ $workflow[2] }}"><i class="bi {{ $workflow[3] }}" aria-hidden="true"></i></span><div><div class="it-workflow-label fw-bold text-dark">{{ $workflow[0] }}</div><div class="it-workflow-detail text-muted mt-1">{{ $workflow[1] }}</div>@if($ticket->assignedUser)<div class="it-workflow-detail text-muted mt-1">PIC: {{ $ticket->assignedUser->name }}</div>@endif</div></div></td>
                    <td><span class="text-dark">{{ $ticket->created_at?->format('d/m/Y') ?? '—' }}</span>@if($ticket->created_at)<small class="text-muted d-block">{{ $ticket->created_at->format('H:i') }}</small>@endif</td>
                    <td class="text-end pe-4"><a href="{{ route('it.show',$ticket) }}" class="btn btn-sm btn-light border" aria-label="Lihat detail {{ $ticket->ticket_no }}"><i class="bi bi-eye me-1" aria-hidden="true"></i> Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5"><span class="it-empty-icon mb-3"><i class="bi bi-inbox fs-4" aria-hidden="true"></i></span><p class="fw-semibold text-dark mb-1">Belum ada tiket IT</p><p class="text-muted mb-0">Tidak ada tiket yang sesuai filter dan hak akses Anda.</p></td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if($tickets->hasPages())<div class="px-4 py-3 border-top">{{ $tickets->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
