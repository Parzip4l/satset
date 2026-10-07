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
        background:
            radial-gradient(circle at 8% 2%, rgba(226, 26, 26, .08), transparent 27%),
            radial-gradient(circle at 92% 12%, rgba(70, 118, 169, .12), transparent 30%),
            linear-gradient(145deg, rgba(255, 255, 255, .68), rgba(244, 247, 250, .45));
        border: 1px solid rgba(255, 255, 255, .82);
        border-radius: 22px;
        box-shadow: 0 20px 55px rgba(32, 37, 44, .06);
        color: var(--request-ink);
        max-width: 1480px;
        padding: 16px;
    }

    .requests-intro {
        align-items: center;
        -webkit-backdrop-filter: blur(18px) saturate(125%);
        backdrop-filter: blur(18px) saturate(125%);
        background: linear-gradient(115deg, rgba(31, 35, 41, .96), rgba(48, 54, 63, .88));
        border: 1px solid rgba(255, 255, 255, .13);
        border-radius: 16px;
        display: flex;
        gap: 32px;
        justify-content: space-between;
        margin-bottom: 16px;
        overflow: hidden;
        padding: 20px 24px;
        position: relative;
    }

    .requests-intro::after {
        border: 28px solid rgba(255, 255, 255, .04);
        border-radius: 50%;
        content: "";
        height: 160px;
        position: absolute;
        right: 18%;
        top: -92px;
        width: 160px;
    }

    .requests-eyebrow {
        color: var(--request-red);
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .1em;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .requests-title {
        color: #fff;
        font-size: clamp(1.55rem, 2.5vw, 2.1rem);
        font-weight: 750;
        letter-spacing: -.04em;
        line-height: 1.08;
        margin: 0 0 6px;
    }

    .requests-description {
        color: #b9bec6;
        font-size: .83rem;
        line-height: 1.5;
        margin: 0;
        max-width: 720px;
    }

    .requests-history-link {
        align-items: center;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: 9px;
        color: #fff;
        display: inline-flex;
        flex: 0 0 auto;
        font-weight: 650;
        gap: 8px;
        padding: 10px 14px;
        text-decoration: none;
        transition: border-color .18s ease, color .18s ease;
        z-index: 1;
    }

    .requests-history-link:hover {
        background: #fff;
        border-color: #fff;
        color: #202328;
    }

    .request-metrics {
        -webkit-backdrop-filter: blur(14px) saturate(120%);
        backdrop-filter: blur(14px) saturate(120%);
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        background: rgba(255, 255, 255, .28);
        border: 1px solid rgba(255, 255, 255, .72);
        border-radius: 14px;
        margin-bottom: 18px;
        overflow: hidden;
        padding: 8px;
    }

    .request-metric {
        align-items: center;
        background: rgba(255, 255, 255, .67);
        border: 1px solid rgba(255, 255, 255, .86);
        border-radius: 11px;
        display: grid;
        gap: 14px;
        grid-template-columns: 38px minmax(0, 1fr);
        min-height: 70px;
        padding: 11px 13px;
    }

    .request-metric-icon {
        align-items: center;
        background: var(--request-soft);
        border-radius: 9px;
        color: #65707d;
        display: inline-flex;
        font-size: 1rem;
        height: 38px;
        justify-content: center;
        width: 38px;
    }

    .request-metric-label {
        color: var(--request-muted);
        font-size: .76rem;
        font-weight: 650;
        margin-bottom: 5px;
    }

    .request-metric-value {
        color: var(--request-ink);
        font-size: 1.45rem;
        font-weight: 750;
        letter-spacing: -.04em;
        line-height: 1;
    }

    .request-metric.pending .request-metric-value {
        color: var(--request-red);
    }

    .request-metric.pending .request-metric-icon {
        background: rgba(226, 26, 26, .08);
        color: var(--request-red);
    }

    .request-section-heading {
        align-items: end;
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
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
        gap: 12px;
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .request-service {
        -webkit-backdrop-filter: blur(16px) saturate(125%);
        backdrop-filter: blur(16px) saturate(125%);
        background: linear-gradient(145deg, rgba(255, 255, 255, .82), rgba(255, 255, 255, .54));
        border: 1px solid rgba(255, 255, 255, .94);
        border-radius: 14px;
        color: inherit;
        display: flex;
        flex-direction: column;
        min-height: 190px;
        overflow: hidden;
        padding: 18px;
        position: relative;
        text-decoration: none;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .request-service:hover {
        border-color: #cfd4da;
        box-shadow: 0 14px 32px rgba(21, 27, 35, .1), inset 0 1px 0 rgba(255, 255, 255, .7);
        color: inherit;
        transform: translateY(-2px);
    }

    .request-service::before {
        background: var(--service-accent, #7a838e);
        content: "";
        height: 3px;
        left: 0;
        position: absolute;
        right: 0;
        top: 0;
    }

    .request-service.tone-blue { --service-accent: #4676a9; --service-soft: #edf3f9; }
    .request-service.tone-teal { --service-accent: #31828a; --service-soft: #edf7f7; }
    .request-service.tone-amber { --service-accent: #a9762a; --service-soft: #faf4e9; }
    .request-service.tone-red { --service-accent: #e21a1a; --service-soft: #fff0f0; }
    .request-service.tone-slate { --service-accent: #626c78; --service-soft: #f1f3f5; }

    .request-service-icon {
        align-items: center;
        background: var(--service-soft, var(--request-soft));
        border-radius: 12px;
        color: var(--service-accent, #4d5865);
        display: inline-flex;
        font-size: 1.25rem;
        height: 42px;
        justify-content: center;
        width: 42px;
    }

    .request-service:hover .request-service-icon,
    .request-service.featured .request-service-icon {
        color: var(--service-accent, var(--request-red));
    }

    .request-service-kicker {
        color: var(--request-muted);
        font-size: .7rem;
        font-weight: 750;
        letter-spacing: .07em;
        margin-bottom: 6px;
        margin-top: 13px;
        text-transform: uppercase;
    }

    .request-service h3 {
        color: var(--request-ink);
        font-size: .98rem;
        font-weight: 750;
        letter-spacing: -.02em;
        margin: 0 0 7px;
    }

    .request-service p {
        color: var(--request-muted);
        display: -webkit-box;
        font-size: .76rem;
        line-height: 1.45;
        margin: 0;
        max-width: 580px;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
    }

    .request-service-arrow {
        align-items: center;
        align-self: flex-end;
        background: var(--request-soft);
        border-radius: 50%;
        color: #a0a7b0;
        display: inline-flex;
        font-size: 1.05rem;
        height: 30px;
        justify-content: center;
        margin-top: auto;
        transition: color .18s ease, transform .18s ease;
        width: 30px;
    }

    .request-service:hover .request-service-arrow {
        color: var(--request-red);
        transform: translateX(3px);
    }

    .request-service.featured {
        border-color: rgba(226, 26, 26, .24);
    }

    .request-service.featured p {
        max-width: 680px;
    }

    @media (max-width: 991.98px) {
        .request-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .request-metric:last-child {
            grid-column: 1 / -1;
        }

        .request-services {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .requests-page {
            padding: 12px;
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
            min-height: 184px;
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
        ['label' => 'Total request', 'value' => $stats['total'] ?? 0, 'icon' => 'bi-layers'],
        ['label' => 'Open', 'value' => $stats['open'] ?? 0, 'icon' => 'bi-inbox'],
        ['label' => 'Dalam proses', 'value' => $stats['in_progress'] ?? 0, 'icon' => 'bi-arrow-repeat'],
        ['label' => 'Selesai', 'value' => $stats['completed'] ?? 0, 'icon' => 'bi-check2-circle'],
        ['label' => 'Menunggu approval', 'value' => $stats['pending_approvals'] ?? 0, 'icon' => 'bi-person-check', 'class' => 'pending'],
    ];

    $services = [
        [
            'title' => 'General Request',
            'kicker' => 'Tiket umum',
            'description' => 'Laporkan kebutuhan umum dan pantau status tiket yang pernah Anda buat.',
            'icon' => 'bi-ticket-perforated',
            'route' => 'ticket.general',
            'class' => 'tone-blue',
        ],
        [
            'title' => 'Permintaan Konsumsi',
            'kicker' => 'Kegiatan & rapat',
            'description' => 'Ajukan konsumsi kegiatan dengan alur approval atasan dan verifikasi Bagian Umum.',
            'icon' => 'bi-cup-hot',
            'route' => 'ticket.konsumsi.create',
            'class' => 'tone-teal',
        ],
        [
            'title' => 'Permintaan ATK / RTK',
            'kicker' => 'Kebutuhan kantor',
            'description' => 'Ajukan alat tulis atau perlengkapan rumah tangga kantor dari katalog yang tersedia.',
            'icon' => 'bi-box-seam',
            'route' => 'ticket.atk-rtk.create',
            'class' => 'tone-amber',
        ],
        [
            'title' => 'GA Permintaan & Temuan',
            'kicker' => 'Layanan fasilitas',
            'description' => 'Sampaikan kebutuhan dukungan atau temuan fasilitas agar dapat segera ditindaklanjuti.',
            'icon' => 'bi-building-gear',
            'route' => 'ticket.ga-permintaan-temuan.create',
            'class' => 'featured tone-red',
        ],
        [
            'title' => 'Approval Saya',
            'kicker' => 'Tugas persetujuan',
            'description' => 'Tinjau permintaan yang menunggu keputusan Anda dan lihat riwayat persetujuannya.',
            'icon' => 'bi-check2-square',
            'route' => 'ticket.approvals',
            'class' => 'tone-slate',
        ],
    ];
@endphp

<div class="container-fluid requests-page mt-2">
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
                <span class="request-metric-icon"><i class="bi {{ $metric['icon'] }}"></i></span>
                <span>
                    <span class="request-metric-label d-block">{{ $metric['label'] }}</span>
                    <span class="request-metric-value d-block">{{ number_format($metric['value']) }}</span>
                </span>
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
