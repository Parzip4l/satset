@extends('partials.layouts.master')

@section('title', 'Requests')
@section('pagetitle', 'Requests')
@section('title-sub', 'Request Menu')

@section('css')
<style>
    .requests-page {
        --request-border: #e6e9ee;
        --request-ink: #20252c;
        --request-muted: #69727e;
        --request-soft: #f6f7f9;
        --request-red: #e21a1a;
        color: var(--request-ink);
        max-width: 1480px;
    }

    .requests-intro {
        align-items: flex-end;
        display: flex;
        gap: 32px;
        justify-content: space-between;
        margin-bottom: 28px;
    }

    .requests-eyebrow {
        color: var(--request-red);
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .1em;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .requests-title {
        color: var(--request-ink);
        font-size: clamp(1.8rem, 3vw, 2.5rem);
        font-weight: 750;
        letter-spacing: -.04em;
        line-height: 1.08;
        margin: 0 0 10px;
    }

    .requests-description {
        color: var(--request-muted);
        line-height: 1.65;
        margin: 0;
        max-width: 720px;
    }

    .requests-history-link {
        align-items: center;
        background: #fff;
        border: 1px solid var(--request-border);
        border-radius: 9px;
        color: var(--request-ink);
        display: inline-flex;
        flex: 0 0 auto;
        font-weight: 650;
        gap: 8px;
        padding: 10px 14px;
        text-decoration: none;
        transition: border-color .18s ease, color .18s ease;
    }

    .requests-history-link:hover {
        border-color: #c7ccd3;
        color: var(--request-red);
    }

    .request-metrics {
        display: grid;
        gap: 1px;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        background: var(--request-border);
        border: 1px solid var(--request-border);
        border-radius: 12px;
        margin-bottom: 38px;
        overflow: hidden;
    }

    .request-metric {
        background: #fff;
        min-height: 98px;
        padding: 18px 20px;
    }

    .request-metric-label {
        color: var(--request-muted);
        font-size: .76rem;
        font-weight: 650;
        margin-bottom: 10px;
    }

    .request-metric-value {
        color: var(--request-ink);
        font-size: 1.75rem;
        font-weight: 750;
        letter-spacing: -.04em;
        line-height: 1;
    }

    .request-metric.pending .request-metric-value {
        color: var(--request-red);
    }

    .request-section-heading {
        align-items: end;
        display: flex;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .request-section-heading h2 {
        color: var(--request-ink);
        font-size: 1.2rem;
        font-weight: 750;
        letter-spacing: -.02em;
        margin: 0 0 4px;
    }

    .request-section-heading p {
        color: var(--request-muted);
        font-size: .82rem;
        margin: 0;
    }

    .request-services {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .request-service {
        align-items: flex-start;
        background: #fff;
        border: 1px solid var(--request-border);
        border-radius: 12px;
        color: inherit;
        display: grid;
        gap: 18px;
        grid-template-columns: 44px minmax(0, 1fr) 24px;
        min-height: 154px;
        padding: 22px;
        position: relative;
        text-decoration: none;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .request-service:hover {
        border-color: #cfd4da;
        box-shadow: 0 10px 28px rgba(21, 27, 35, .06);
        color: inherit;
        transform: translateY(-2px);
    }

    .request-service-icon {
        align-items: center;
        background: var(--request-soft);
        border: 1px solid #eceef1;
        border-radius: 10px;
        color: #4d5865;
        display: inline-flex;
        font-size: 1.15rem;
        height: 44px;
        justify-content: center;
        width: 44px;
    }

    .request-service:hover .request-service-icon,
    .request-service.featured .request-service-icon {
        background: rgba(226, 26, 26, .08);
        border-color: rgba(226, 26, 26, .12);
        color: var(--request-red);
    }

    .request-service-kicker {
        color: var(--request-muted);
        font-size: .7rem;
        font-weight: 750;
        letter-spacing: .07em;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .request-service h3 {
        color: var(--request-ink);
        font-size: 1.05rem;
        font-weight: 750;
        letter-spacing: -.02em;
        margin: 0 0 7px;
    }

    .request-service p {
        color: var(--request-muted);
        font-size: .82rem;
        line-height: 1.55;
        margin: 0;
        max-width: 580px;
    }

    .request-service-arrow {
        color: #a0a7b0;
        font-size: 1.05rem;
        padding-top: 10px;
        transition: color .18s ease, transform .18s ease;
    }

    .request-service:hover .request-service-arrow {
        color: var(--request-red);
        transform: translateX(3px);
    }

    .request-service.featured {
        border-color: rgba(226, 26, 26, .24);
    }

    @media (max-width: 991.98px) {
        .request-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .request-metric:last-child {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 767.98px) {
        .requests-intro {
            align-items: flex-start;
            flex-direction: column;
            gap: 18px;
        }

        .request-services {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 479.98px) {
        .request-metrics {
            grid-template-columns: 1fr;
        }

        .request-metric:last-child {
            grid-column: auto;
        }

        .request-service {
            gap: 14px;
            grid-template-columns: 40px minmax(0, 1fr) 18px;
            padding: 18px;
        }

        .request-service-icon {
            height: 40px;
            width: 40px;
        }
    }
</style>
@endsection

@section('content')
@php
    $metrics = [
        ['label' => 'Total request', 'value' => $stats['total'] ?? 0],
        ['label' => 'Open', 'value' => $stats['open'] ?? 0],
        ['label' => 'Dalam proses', 'value' => $stats['in_progress'] ?? 0],
        ['label' => 'Selesai', 'value' => $stats['completed'] ?? 0],
        ['label' => 'Menunggu approval', 'value' => $stats['pending_approvals'] ?? 0, 'class' => 'pending'],
    ];

    $services = [
        [
            'title' => 'General Request',
            'kicker' => 'Tiket umum',
            'description' => 'Laporkan kebutuhan umum dan pantau status tiket yang pernah Anda buat.',
            'icon' => 'bi-ticket-perforated',
            'route' => 'ticket.general',
        ],
        [
            'title' => 'Permintaan Konsumsi',
            'kicker' => 'Kegiatan & rapat',
            'description' => 'Ajukan konsumsi kegiatan dengan alur approval atasan dan verifikasi Bagian Umum.',
            'icon' => 'bi-cup-hot',
            'route' => 'ticket.konsumsi.create',
        ],
        [
            'title' => 'Permintaan ATK / RTK',
            'kicker' => 'Kebutuhan kantor',
            'description' => 'Ajukan alat tulis atau perlengkapan rumah tangga kantor dari katalog yang tersedia.',
            'icon' => 'bi-box-seam',
            'route' => 'ticket.atk-rtk.create',
        ],
        [
            'title' => 'GA Permintaan & Temuan',
            'kicker' => 'Layanan fasilitas',
            'description' => 'Sampaikan kebutuhan dukungan atau temuan fasilitas agar dapat segera ditindaklanjuti.',
            'icon' => 'bi-building-gear',
            'route' => 'ticket.ga-permintaan-temuan.create',
            'class' => 'featured',
        ],
        [
            'title' => 'Approval Saya',
            'kicker' => 'Tugas persetujuan',
            'description' => 'Tinjau permintaan yang menunggu keputusan Anda dan lihat riwayat persetujuannya.',
            'icon' => 'bi-check2-square',
            'route' => 'ticket.approvals',
        ],
    ];
@endphp

<div class="container-fluid requests-page mt-7">
    @if(session('success'))
        <div class="alert alert-success border-0 mb-4">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 mb-4">{{ session('error') }}</div>
    @endif

    <header class="requests-intro">
        <div>
            <div class="requests-eyebrow">Request Center</div>
            <h1 class="requests-title">Apa yang Anda butuhkan?</h1>
            <p class="requests-description">Pilih layanan untuk membuat permintaan baru. Seluruh status, approval, dan progres tindak lanjut tersimpan dalam satu tempat.</p>
        </div>
        <a href="{{ route('ticket.general') }}" class="requests-history-link">
            <i class="bi bi-clock-history"></i>
            Lihat tiket saya
        </a>
    </header>

    <section class="request-metrics" aria-label="Ringkasan request">
        @foreach($metrics as $metric)
            <div class="request-metric {{ $metric['class'] ?? '' }}">
                <div class="request-metric-label">{{ $metric['label'] }}</div>
                <div class="request-metric-value">{{ number_format($metric['value']) }}</div>
            </div>
        @endforeach
    </section>

    <section>
        <div class="request-section-heading">
            <div>
                <h2>Layanan</h2>
                <p>Pilih jenis permintaan yang sesuai.</p>
            </div>
        </div>

        <div class="request-services">
            @foreach($services as $service)
                <a href="{{ route($service['route']) }}" class="request-service {{ $service['class'] ?? '' }}">
                    <span class="request-service-icon"><i class="bi {{ $service['icon'] }}"></i></span>
                    <span>
                        <span class="request-service-kicker">{{ $service['kicker'] }}</span>
                        <h3>{{ $service['title'] }}</h3>
                        <p>{{ $service['description'] }}</p>
                    </span>
                    <i class="bi bi-arrow-right request-service-arrow"></i>
                </a>
            @endforeach
        </div>
    </section>
</div>
@endsection
