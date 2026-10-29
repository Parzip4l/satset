@extends('partials.layouts.master')
@section('title','Pengajuan Layanan IT')
@section('pagetitle','Pengajuan Layanan IT')
@section('title-sub','Layanan IT')
@section('content')
<div class="container-fluid" style="max-width:900px">
    <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-body p-4 p-md-5">
            <div class="mb-3 text-danger"><i class="bi bi-laptop fs-3" aria-hidden="true"></i></div>
            <h1 class="h4 fw-bold mb-2">Pengajuan layanan IT belum tersedia</h1>
            <p class="text-muted mb-4">Form pengajuan belum dibuka untuk akun Anda. Anda tetap dapat melihat tiket IT yang sudah ada.</p>
            <div class="d-flex flex-wrap gap-2"><a href="{{ route('it.index') }}" class="btn btn-primary">Lihat tiket IT</a><a href="{{ route('ticket.index') }}" class="btn btn-light border">Kembali ke layanan</a>@if(\App\Support\ItAccess::can(auth()->user(),'manage_it_configuration') || \App\Support\ItAccess::can(auth()->user(),'publish_it_configuration'))<a href="{{ route('it.configuration.pilot') }}" class="btn btn-light border">Lihat kesiapan pilot</a>@endif</div>
        </div>
    </div>
</div>
@endsection
