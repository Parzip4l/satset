@extends('partials.layouts.master')
@section('title', 'Konfigurasi IT')
@section('pagetitle', 'Konfigurasi IT')
@section('title-sub', 'Layanan IT')
@section('css') @include('it.configuration.style') @endsection
@section('content')
<div class="container-fluid it-config">
    <header class="config-header config-page-header">
        <div class="config-page-heading">
            <div class="config-eyebrow">Pengelolaan layanan IT</div>
            <h1>Konfigurasi IT</h1>
            <p class="mb-0">Kelola katalog dan form dalam versi yang dapat ditinjau sebelum dipublikasikan.</p>
        </div>
        <nav class="config-page-actions" aria-label="Navigasi konfigurasi IT">
            @can('manage_it_configuration')<a class="config-page-action" href="{{ route('it.configuration.teams') }}"><i class="bi bi-people" aria-hidden="true"></i>Tim IT</a>@endcan
            <a class="config-page-action" href="{{ route('it.configuration.pilot') }}"><i class="bi bi-clipboard-check" aria-hidden="true"></i>Kesiapan pilot</a>
            @if(\App\Support\ItAccess::can(auth()->user(),'manage_it_configuration') || \App\Support\ItAccess::can(auth()->user(),'manage_it_operations'))<a class="config-page-action" href="{{ route('it.operations.health') }}"><i class="bi bi-activity" aria-hidden="true"></i>Status worker</a>@endif
            <a class="config-page-action config-page-action-back" href="{{ route('it.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Layanan IT</a>
        </nav>
    </header>
    <div class="config-note"><i class="bi bi-info-circle me-2"></i>Publikasi konfigurasi menyiapkan form dan katalog. Pengajuan IT tetap tertutup sampai konfigurasi layanan dan pilot disetujui.</div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @php($active = $versions->firstWhere('id', $set->active_version_id))
    @php($summary = $active?->configuration ?? $versions->first()?->configuration ?? [])
    <div class="config-stats">
        @foreach(['services'=>'Layanan IT','categories'=>'Kategori','catalog'=>'Item katalog','forms'=>'Template form'] as $key=>$label)<div class="config-stat"><strong>{{ count($summary[$key] ?? []) }}</strong><span>{{ $label }}</span></div>@endforeach
    </div>
    <div class="config-panel">
        <div class="config-header"><div><h2 class="mb-1">Versi konfigurasi</h2><span class="config-small">{{ $active ? 'Versi aktif: v'.$active->number : 'Belum ada versi aktif. Draft awal siap ditinjau.' }}</span></div>
        @can('manage_it_configuration')<form method="POST" action="{{ route('it.configuration.draft') }}" class="builder-actions">@csrf<input type="hidden" name="reason" value="Membuat draft baru untuk peninjauan konfigurasi"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Buat draft baru</button></form>@endcan</div>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Versi</th><th>Status</th><th>Diperbarui</th><th>Checksum</th><th></th></tr></thead><tbody>
        @foreach($versions as $version)<tr><td><strong>v{{ $version->number }}</strong></td><td><span class="config-badge {{ $version->state==='draft'?'draft':($version->id===$set->active_version_id?'active':'') }}">{{ $version->state==='draft'?'Draft':($version->id===$set->active_version_id?'Aktif':'Arsip publikasi') }}</span></td><td>{{ $version->updated_at->format('d/m/Y H:i') }}</td><td><code>{{ substr($version->checksum,0,12) }}</code></td><td class="text-end"><a href="{{ route('it.configuration.show',$version) }}" class="btn btn-outline-secondary btn-sm">{{ $version->state==='draft'?'Buka draft':'Lihat & preview' }} <i class="bi bi-arrow-right ms-1"></i></a></td></tr>@endforeach
        </tbody></table></div>
    </div>
    <div class="config-panel"><h2>Riwayat konfigurasi</h2><p class="config-small">Perubahan dan alasan tersimpan. Jawaban pada preview tidak dicatat sebagai tiket.</p><div class="table-responsive"><table class="table config-history"><thead><tr><th>Waktu</th><th>Aksi</th><th>Versi</th><th>Aktor</th><th>Alasan</th></tr></thead><tbody>@forelse($events as $event)@php($eventVersion = $versions->firstWhere('id', $event->version_id))<tr><td>{{ $event->created_at }}</td><td>{{ ['initialized'=>'Konfigurasi awal','draft_created'=>'Draft dibuat','draft_saved'=>'Draft disimpan','published'=>'Dipublikasikan','rollback_activated'=>'Versi diaktifkan kembali'][$event->action] ?? $event->action }}</td><td>{{ $eventVersion ? 'v'.$eventVersion->number : 'Tidak tersedia' }}</td><td>{{ $event->actor_name ?? 'Pengguna tidak tersedia' }}</td><td>{{ $event->reason }}</td></tr>@empty<tr><td colspan="5" class="text-muted">Belum ada riwayat konfigurasi.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
