@extends('partials.layouts.master')
@section('title', 'Status Layanan IT')
@section('pagetitle', 'Status Layanan IT')
@section('title-sub', 'Pengaturan')
@section('content')
<div class="container-fluid" style="max-width: 900px">
    <div class="mb-4"><h1 class="h3 mb-2">Status Layanan IT</h1><p class="text-muted mb-0">Atur ketersediaan Layanan IT untuk pengguna Satset.</p></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card border"><div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
            <div><h2 class="h5 mb-2">Layanan IT</h2><p class="text-muted mb-0">Saat nonaktif, halaman pengajuan menampilkan informasi layanan segera hadir.</p></div>
            <span class="badge {{ $service->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $service->is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <div class="alert alert-light border mb-4">Tiket yang sudah ada tetap dapat dipantau sesuai hak akses. Pengajuan baru hanya tersedia jika konfigurasi dan persyaratan pilot juga sudah terpenuhi.</div>
        <form action="{{ route('settings.it-service.update') }}" method="POST">
            @csrf @method('PUT')
            <input type="hidden" name="is_active" value="{{ $service->is_active ? '0' : '1' }}">
            <button class="btn {{ $service->is_active ? 'btn-outline-danger' : 'btn-primary' }}" type="submit">{{ $service->is_active ? 'Nonaktifkan layanan' : 'Aktifkan layanan' }}</button>
        </form>
    </div></div>
</div>
@endsection
