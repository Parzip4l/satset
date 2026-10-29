@extends('partials.layouts.master')
@section('title', 'Editor Konfigurasi IT')
@section('pagetitle', 'Konfigurasi IT')
@section('title-sub', 'Layanan IT')
@section('css') @include('it.configuration.style') @endsection
@section('content')
@php($editable = $version->state==='draft' && \App\Support\ItAccess::can(auth()->user(),'manage_it_configuration'))
<div class="container-fluid it-config" id="it-configuration-editor" data-editable="{{ $editable?'1':'0' }}" data-save-url="{{ route('it.configuration.update',$version) }}" data-preview-url="{{ route('it.configuration.preview',$version) }}" data-sla-preview-url="{{ route('it.configuration.sla-preview',$version) }}" data-lock="{{ $version->lock_version }}">
    <header class="config-header config-page-header"><div class="config-page-heading"><div class="config-eyebrow">Pengelolaan layanan IT</div><h1>Konfigurasi IT <span class="text-muted">/ v{{ $version->number }}</span></h1><p class="mb-0">{{ $editable?'Atur layanan dan proses penanganan dalam draft ini.':'Tinjau pengaturan pada versi yang tersimpan.' }}</p></div><nav class="config-page-actions" aria-label="Navigasi konfigurasi">@can('manage_it_configuration')<a href="{{ route('it.configuration.teams') }}" class="config-page-action"><i class="bi bi-people" aria-hidden="true"></i>Tim IT</a>@endcan<a href="{{ route('it.configuration.index') }}" class="config-page-action config-page-action-back"><i class="bi bi-arrow-left" aria-hidden="true"></i>Semua versi</a></nav></header>
    <div class="config-note"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Simpan perubahan sebelum mencoba preview. Publikasi tidak mengubah tiket lama atau otomatis membuka pengajuan IT. Pilihan petugas saat ini berasal dari akun yang sudah tercatat di Satset.</div>
    <div id="config-feedback" role="status" aria-live="polite">@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif</div>
    <div class="config-layout"><div>
        <div class="config-panel">
            <div class="config-tabs" role="tablist" aria-label="Bagian konfigurasi">@foreach(['services'=>'Layanan','categories'=>'Kategori','catalog'=>'Katalog','forms'=>'Formulir','locations'=>'Lokasi','operations'=>'Alur kerja','sla'=>'SLA & kalender','preview'=>'Preview & uji'] as $key=>$label)<button type="button" role="tab" id="config-tab-{{ $key }}" aria-controls="config-builder" tabindex="{{ $key==='services'?'0':'-1' }}" data-config-tab="{{ $key }}" aria-selected="{{ $key==='services'?'true':'false' }}">{{ $label }}</button>@endforeach</div>
            <div id="config-tab-guide" class="config-tab-guide" aria-live="polite"></div>
            <div id="config-builder" role="tabpanel" aria-labelledby="config-tab-services"></div>
        </div>
    </div><aside class="config-aside">
        <div class="config-panel"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="mb-0">Versi {{ $version->number }}</h2><span class="config-badge {{ $version->state==='draft'?'draft':'active' }}">{{ $version->state==='draft'?'Draft':'Terpublikasi' }}</span></div>
            <p class="config-small">Kode versi <code id="config-checksum">{{ substr($version->checksum,0,12) }}</code></p>
            @if($editable)<label for="config-save-reason">Alasan perubahan</label><textarea id="config-save-reason" class="form-control mb-2" maxlength="1000" placeholder="Contoh: menambahkan kategori jaringan"></textarea><p class="builder-help mb-3">Minimal 5 karakter. Alasan dicatat dalam riwayat konfigurasi.</p><button type="button" id="config-save" class="btn btn-primary w-100">Simpan draft</button><div id="config-save-status" role="status" aria-live="polite" class="builder-help mt-2">Belum ada perubahan lokal.</div>@endif
        </div>
        @can('publish_it_configuration')
        @if($version->state==='draft' || $set->active_version_id!==$version->id)
        <div class="config-panel"><h2>{{ $version->state==='draft'?'Publikasikan konfigurasi':'Aktifkan kembali versi ini' }}</h2><p class="config-small">{{ $version->state==='draft'?'Simpan dan tinjau draft terlebih dahulu. Publikasi memeriksa pengaturan lalu menjadikan versi ini aktif.':'Versi ini akan menjadi aktif kembali. Data tiket yang sudah ada tetap tersimpan.' }}</p>
            <form method="POST" action="{{ route($version->state==='draft'?'it.configuration.publish':'it.configuration.rollback',$version) }}" id="config-activate">@csrf<input type="hidden" name="set_revision" value="{{ $set->revision }}"><input type="hidden" name="lock_version" value="{{ $version->lock_version }}"><label for="config-publish-reason">Alasan {{ $version->state==='draft'?'publikasi':'rollback' }}</label><textarea id="config-publish-reason" name="reason" required minlength="5" maxlength="1000" class="form-control mb-3"></textarea><button class="btn btn-outline-primary w-100">{{ $version->state==='draft'?'Publikasikan versi':'Aktifkan versi ini' }}</button></form>
        </div>@endif @endcan
        <div class="config-panel config-aside-help"><h2>Urutan pengaturan</h2><ol><li>Susun layanan, kategori, katalog dan formulir.</li><li>Atur tim, alur kerja dan SLA.</li><li>Simpan draft, lalu coba preview sebelum publikasi.</li></ol><a href="{{ route('it.configuration.pilot') }}" class="config-text-link">Lihat kesiapan pilot <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
    </aside></div>
</div>
<script type="application/json" id="it-configuration-data">{!! json_encode(['configuration'=>$version->configuration,'users'=>$users,'teams'=>$teams], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
@section('js')<script src="{{ asset('assets/js/it/configuration-builder.js') }}?v={{ filemtime(public_path('assets/js/it/configuration-builder.js')) }}" defer></script>@endsection
