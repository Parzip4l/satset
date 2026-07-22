@extends('partials.layouts.master_auth')

@section('title', 'Public Request')

@section('css')
<style>
    body {
        background: #f8fafc;
    }

    .public-request-page {
        min-height: 100vh;
        padding: 32px 0;
    }

    .request-hero,
    .request-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .06);
    }

    .request-card {
        display: block;
        height: 100%;
        padding: 24px;
        color: inherit;
        text-decoration: none;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .request-card:hover {
        border-color: #e21a1a;
        box-shadow: 0 18px 40px rgba(226, 26, 26, .12);
        color: inherit;
        transform: translateY(-2px);
    }

    .request-icon {
        width: 58px;
        height: 58px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fee2e2;
        color: #e21a1a;
        font-size: 1.55rem;
    }
</style>
@endsection

@section('content')
<div class="public-request-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-10">
                <div class="request-hero p-4 p-lg-5 mb-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                        <span class="request-icon">
                            <i class="bi bi-qr-code-scan"></i>
                        </span>
                        <div>
                            <h3 class="fw-bold text-dark mb-2">Public Request Bagian Umum</h3>
                            <p class="text-muted mb-0">Pilih jenis permintaan atau laporan yang ingin dikirim. Anda tidak perlu login untuk mengirim request.</p>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <a href="{{ route('public.ticket.konsumsi.create') }}" class="request-card">
                            <span class="request-icon mb-4">
                                <i class="bi bi-cup-hot"></i>
                            </span>
                            <h5 class="fw-bold text-dark mb-2">Konsumsi</h5>
                            <p class="text-muted mb-0">Ajukan kebutuhan konsumsi untuk rapat, kegiatan, pelatihan, atau acara operasional.</p>
                        </a>
                    </div>

                    <div class="col-md-4">
                        <a href="{{ route('public.ticket.atk-rtk.create') }}" class="request-card">
                            <span class="request-icon mb-4">
                                <i class="bi bi-box-seam"></i>
                            </span>
                            <h5 class="fw-bold text-dark mb-2">ATK / RTK</h5>
                            <p class="text-muted mb-0">Ajukan kebutuhan alat tulis kantor atau rumah tangga kantor untuk operasional.</p>
                        </a>
                    </div>

                    <div class="col-md-4">
                        <a href="{{ route('public.ticket.ga-permintaan-temuan.create') }}" class="request-card">
                            <span class="request-icon mb-4">
                                <i class="bi bi-tools"></i>
                            </span>
                            <h5 class="fw-bold text-dark mb-2">Permintaan / Temuan</h5>
                            <p class="text-muted mb-0">Ajukan dukungan fasilitas atau laporkan temuan kerusakan dan kondisi area kerja.</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
