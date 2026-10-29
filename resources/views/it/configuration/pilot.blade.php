@extends('partials.layouts.master')
@section('title','Kesiapan Pilot IT')
@section('pagetitle','Kesiapan Pilot IT')
@section('title-sub','Layanan IT')
@section('css') @include('it.configuration.style') @endsection
@section('content')
<div class="container-fluid it-config">
    <header class="config-header config-page-header"><div class="config-page-heading"><div class="config-eyebrow">Pengelolaan layanan IT</div><h1>Kesiapan pilot IT</h1><p class="mb-0">Periksa konfigurasi dan persyaratan sebelum pengajuan IT dibuka.</p></div><nav class="config-page-actions" aria-label="Navigasi konfigurasi">@if(\App\Support\ItAccess::can(auth()->user(),'manage_it_configuration') || \App\Support\ItAccess::can(auth()->user(),'manage_it_operations'))<a href="{{ route('it.operations.health') }}" class="config-page-action"><i class="bi bi-activity" aria-hidden="true"></i>Status worker</a>@endif<a href="{{ route('it.configuration.index') }}" class="config-page-action config-page-action-back"><i class="bi bi-arrow-left" aria-hidden="true"></i>Konfigurasi IT</a></nav></header>
    <div class="alert alert-{{ $result['ready']?'success':'warning' }}"><strong>{{ $result['ready']?'Pemeriksaan teknis siap':'Pilot belum siap dibuka' }}</strong><div class="mt-1">Sakelar pengajuan: {{ $enabled?'aktif':'tertutup' }}. Pengajuan memerlukan sakelar aktif dan semua pemeriksaan siap.</div></div>
    <div class="config-panel"><h2>Ringkasan pilot</h2><p class="config-small mb-0">Informasi berikut berasal dari pengaturan yang sedang digunakan.</p><div class="pilot-grid"><div class="pilot-stat"><span>Versi aktif</span><strong>{{ $result['version_id'] ?? 'Belum ada' }}</strong></div><div class="pilot-stat"><span>Peserta</span><strong>{{ $result['participant_count'] ?? 0 }} orang</strong></div><div class="pilot-stat"><span>Katalog yang dibuka</span><strong>{{ implode(', ', $result['catalog_keys'] ?? []) ?: 'Belum ditetapkan' }}</strong></div></div></div>
    <div class="config-panel"><h2>{{ $result['issues']?'Hal yang perlu diselesaikan':'Pemeriksaan kesiapan' }}</h2>@if($result['issues'])<p class="config-small mb-0">Tinjau setiap poin berikut sebelum membuka pengajuan.</p><ul class="pilot-issues">@foreach($result['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@else<p class="config-small mb-0">Tidak ada kendala pada pemeriksaan saat ini.</p>@endif</div>
    <div class="config-note mb-0"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Persetujuan bisnis dan UAT dicatat melalui proses pilot. Perubahan versi aktif atau gangguan worker dapat menutup pengajuan baru secara otomatis.</div>
</div>
@endsection
