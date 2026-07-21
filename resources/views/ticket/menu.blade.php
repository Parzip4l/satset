@extends('partials.layouts.master')

@section('title', 'Requests')
@section('pagetitle', 'Requests')
@section('title-sub', 'Request Menu')

@section('css')
<style>
    .request-hub {
        --hub-ink: #102542;
        --hub-muted: #5f6c7b;
        --hub-surface: #ffffff;
        --hub-line: #d8e1eb;
        --hub-warm: #f3ede2;
        --hub-blue: #0f5cc0;
        --hub-teal: #0e7490;
        --hub-gold: #c9831f;
        --hub-shadow: 0 24px 60px rgba(16, 37, 66, 0.08);
    }

    .request-hero {
        background:
            radial-gradient(circle at top right, rgba(15, 92, 192, 0.14), transparent 26%),
            linear-gradient(135deg, #fff8ef 0%, #ffffff 48%, #eef6ff 100%);
        border: 1px solid rgba(16, 37, 66, 0.08);
        border-radius: 28px;
        box-shadow: var(--hub-shadow);
        overflow: hidden;
        position: relative;
    }

    .request-hero::after {
        content: "";
        position: absolute;
        inset: auto -60px -80px auto;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(14, 116, 144, 0.16), transparent 70%);
    }

    .request-stat {
        background: rgba(255, 255, 255, 0.72);
        border: 1px solid rgba(16, 37, 66, 0.08);
        border-radius: 20px;
        padding: 1rem 1.1rem;
        backdrop-filter: blur(8px);
    }

    .request-card {
        background: var(--hub-surface);
        border: 1px solid var(--hub-line);
        border-radius: 24px;
        box-shadow: 0 16px 45px rgba(16, 37, 66, 0.07);
        height: 100%;
        position: relative;
        overflow: hidden;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }

    .request-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 24px 54px rgba(16, 37, 66, 0.11);
        border-color: rgba(15, 92, 192, 0.22);
    }

    .request-card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 6px;
    }

    .request-card.general::before { background: linear-gradient(180deg, #0f5cc0, #55a3ff); }
    .request-card.konsumsi::before { background: linear-gradient(180deg, #0e7490, #39b6c8); }
    .request-card.atk::before { background: linear-gradient(180deg, #c9831f, #f5b748); }
    .request-card.ga::before { background: linear-gradient(180deg, #6d28d9, #a78bfa); }

    .request-icon {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
    }

    .request-flow {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }

    .request-flow span {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        color: #475569;
        font-size: .76rem;
        font-weight: 600;
        padding: .45rem .75rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid request-hub mt-8">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="request-hero p-4 p-lg-5 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h3 class="fw-bold text-dark mb-2">Requests Center</h3>
                <p class="text-muted">Request Center adalah pusat pengelolaan seluruh permintaan operasional yang masuk ke dalam sistem. Halaman ini memberikan visibilitas menyeluruh terhadap status request mulai dari permintaan baru, yang sedang diproses, hingga yang telah selesai.</p>
            </div>
            <div class="col-lg-5">
                <div class="d-flex justify-content-lg-end mb-3">
                    <form action="{{ route('mail.test.send') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn rounded-pill px-4 text-white" style="background:#102542;">
                            <i class="bi bi-envelope-check me-1"></i> Test Email
                        </button>
                    </form>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="request-stat">
                            <div class="text-muted small mb-1">Total Request</div>
                            <div class="fs-3 fw-bold text-dark">{{ $stats['total'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="request-stat">
                            <div class="text-muted small mb-1">Open</div>
                            <div class="fs-3 fw-bold text-primary">{{ $stats['open'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="request-stat">
                            <div class="text-muted small mb-1">In Progress</div>
                            <div class="fs-3 fw-bold" style="color:#0e7490;">{{ $stats['in_progress'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="request-stat">
                            <div class="text-muted small mb-1">Selesai</div>
                            <div class="fs-3 fw-bold" style="color:#c9831f;">{{ $stats['completed'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4 col-md-6">
            <div class="request-card general">
                <div class="card-body p-4 p-xl-5">
                    <div class="request-icon text-primary bg-primary-subtle mb-4">
                        <i class="bi bi-kanban"></i>
                    </div>
                    <h4 class="fw-bold mb-2">General</h4>
                    <p class="text-muted mb-4">
                        Masuk ke modul request existing untuk melihat daftar tiket, filter status, dan membuat ticket general baru.
                    </p>
                    <a href="{{ route('ticket.general') }}" class="btn btn-primary px-4 rounded-pill">
                        Buka General
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="request-card konsumsi">
                <div class="card-body p-4 p-xl-5">
                    <div class="request-icon mb-4" style="background: rgba(14,116,144,.12); color:#0e7490;">
                        <i class="bi bi-cup-hot"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Permintaan Konsumsi</h4>
                    <p class="text-muted mb-4">
                        Form khusus pengajuan konsumsi dengan alur karyawan, approval atasan, verifikasi Bagian Umum, vendor, sampai laporan pertanggungjawaban.
                    </p>
                    <a href="{{ route('ticket.konsumsi.create') }}" class="btn rounded-pill px-4 text-white" style="background:#0e7490;">
                        Buka Form Konsumsi
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="request-card atk">
                <div class="card-body p-4 p-xl-5">
                    <div class="request-icon mb-4" style="background: rgba(201,131,31,.14); color:#c9831f;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h4 class="fw-bold mb-2">ATK / RTK</h4>
                    <p class="text-muted mb-4">
                        Pengajuan kebutuhan alat tulis atau rumah tangga kantor dengan form khusus supaya request operasional tidak tercampur dengan tiket general.
                    </p>
                    <a href="{{ route('ticket.atk-rtk.create') }}" class="btn rounded-pill px-4 text-white" style="background:#c9831f;">
                        Buka Form ATK/RTK
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="request-card ga">
                <div class="card-body p-4 p-xl-5">
                    <div class="request-icon mb-4" style="background: rgba(109,40,217,.12); color:#6d28d9;">
                        <i class="bi bi-qr-code-scan"></i>
                    </div>
                    <h4 class="fw-bold mb-2">GA Permintaan & Temuan</h4>
                    <p class="text-muted mb-4">
                        Form QR Code Bagian Umum untuk mencatat permintaan dukungan dan temuan kerusakan agar tindak lanjutnya termonitor.
                    </p>
                    <a href="{{ route('ticket.ga-permintaan-temuan.create') }}" class="btn rounded-pill px-4 text-white" style="background:#6d28d9;">
                        Buka Form GA
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
