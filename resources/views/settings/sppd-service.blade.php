@extends('partials.layouts.master')

@section('title', 'Status SPPD')
@section('pagetitle', 'Status SPPD')
@section('title-sub', 'Pengaturan')

@section('content')
<div class="container-fluid" style="max-width: 900px">
    @include('sppd.messages')

    <section class="card border" aria-labelledby="sppd-service-title">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <h1 class="h4 mb-0" id="sppd-service-title">Pengajuan Perjalanan Dinas</h1>
                <span class="badge {{ $service->is_active ? 'bg-success' : 'bg-secondary' }}">
                    {{ $service->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </div>

            <p class="text-muted">
                {{ $service->is_active ? 'Karyawan bisa mengirim pengajuan SPPD baru.' : 'Pengajuan baru ditutup. Pemohon dan approver bisa menyelesaikan pengajuan yang sudah tercatat.' }}
            </p>

            <form action="{{ route('settings.sppd-service.update') }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="is_active" value="{{ $service->is_active ? '0' : '1' }}">
                <button class="btn {{ $service->is_active ? 'btn-outline-danger' : 'btn-primary' }}" type="submit">
                    {{ $service->is_active ? 'Nonaktifkan Pengajuan Baru' : 'Aktifkan Pengajuan Baru' }}
                </button>
            </form>
        </div>
    </section>
    <section class="card border mt-3">
        <div class="card-body p-4">
            <h2 class="h5">Acuan SK Perjalanan Dinas</h2>
            <form action="{{ route('settings.sppd-service.policy') }}" method="POST">
                @csrf
                @method('PUT')
                <label class="form-label" for="sppd-policy-url">Tautan SK Perjalanan Dinas</label>
                <input class="form-control" id="sppd-policy-url" name="sppd_policy_url" type="url" maxlength="2000"
                    value="{{ old('sppd_policy_url', $service->sppd_policy_url) }}">
                <button class="btn btn-primary mt-3" type="submit">Simpan Tautan</button>
            </form>
        </div>
    </section>
</div>
@endsection
