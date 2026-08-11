@extends('partials.layouts.master_auth')

@section('title', 'Public Request')

@section('css')
<style>
    :root {
        --public-red: #ed1c24;
        --public-red-dark: #dc141c;
        --public-ink: #1f2937;
        --public-muted: #667085;
        --public-line: #e6ebf2;
    }

    body {
        background:
            radial-gradient(circle at 0 82%, rgba(148, 163, 184, .1) 0 1px, transparent 1px) 0 0 / 16px 16px,
            linear-gradient(180deg, #ffffff 0%, #f6f9fc 54%, #ffffff 100%);
        color: var(--public-ink);
    }

    .public-request-page {
        min-height: 100vh;
        padding-bottom: 42px;
        overflow: hidden;
    }

    .public-request-header {
        background: #fff;
        border-bottom: 1px solid var(--public-line);
        box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
    }

    .public-request-shell {
        width: min(100% - 32px, 1440px);
        margin: 0 auto;
    }

    .public-request-header .public-request-shell {
        min-height: 96px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
    }

    .public-brand {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .public-brand img {
        width: 188px;
        max-width: 52vw;
        height: auto;
        display: block;
    }

    .public-help {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        color: var(--public-ink);
        text-decoration: none;
    }

    .public-help:hover {
        color: var(--public-red);
    }

    .public-help-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f4f6f9;
        border: 1px solid #e7ebf0;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .85);
        font-size: 1.35rem;
    }

    .public-help-title {
        display: block;
        font-weight: 800;
        line-height: 1.1;
    }

    .public-help-subtitle {
        display: block;
        color: var(--public-muted);
        margin-top: 5px;
    }

    .public-request-content {
        padding-top: 38px;
    }

    .request-hero {
        min-height: 188px;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) 330px;
        align-items: center;
        gap: 34px;
        position: relative;
        overflow: hidden;
        margin: 0 auto 34px;
        padding: 50px 64px;
        background: rgba(255, 255, 255, .88);
        border: 1px solid #dfe6ef;
        border-radius: 18px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .07);
        backdrop-filter: blur(8px);
    }

    .request-hero::after {
        content: "";
        position: absolute;
        inset: auto -1px -1px auto;
        width: min(38%, 390px);
        height: 100%;
        background: linear-gradient(110deg, transparent 0%, rgba(237, 28, 36, .04) 100%);
        pointer-events: none;
    }

    .request-hero-icon {
        width: 112px;
        height: 112px;
        border-radius: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff0f2;
        box-shadow: 0 18px 38px rgba(237, 28, 36, .12);
    }

    .request-hero-qr {
        width: 58px;
        height: 58px;
        background: var(--public-red);
        -webkit-mask: url("{{ asset('assets/images/apps/qr-code.svg') }}") center / contain no-repeat;
        mask: url("{{ asset('assets/images/apps/qr-code.svg') }}") center / contain no-repeat;
    }

    .request-hero h1 {
        margin: 0 0 12px;
        font-size: clamp(2rem, 3vw, 2.75rem);
        font-weight: 800;
        line-height: 1.08;
        letter-spacing: 0;
    }

    .request-hero p {
        max-width: 610px;
        margin: 0;
        color: var(--public-muted);
        font-size: 1.12rem;
        line-height: 1.55;
    }

    .request-illustration {
        position: relative;
        align-self: stretch;
        min-height: 132px;
        z-index: 1;
    }

    .request-building {
        position: absolute;
        right: 42px;
        bottom: 4px;
        width: 118px;
        height: 128px;
        border-radius: 4px 4px 0 0;
        background: linear-gradient(140deg, rgba(237, 28, 36, .06), rgba(237, 28, 36, .24));
        transform: skewY(-7deg);
    }

    .request-building::before {
        content: "";
        position: absolute;
        right: 0;
        top: 24px;
        width: 58px;
        height: 116px;
        background: rgba(237, 28, 36, .12);
        transform: translateX(42px) skewY(16deg);
        transform-origin: left top;
    }

    .request-building::after {
        content: "";
        position: absolute;
        inset: 26px 24px auto;
        width: 54px;
        height: 64px;
        background:
            linear-gradient(90deg, rgba(255, 255, 255, .42) 11px, transparent 11px 21px, rgba(255, 255, 255, .42) 21px 32px, transparent 32px 42px, rgba(255, 255, 255, .42) 42px) 0 0 / 54px 18px repeat-y;
    }

    .request-leaf {
        position: absolute;
        bottom: 6px;
        width: 48px;
        height: 108px;
        border-radius: 50% 50% 8px 50%;
        background: rgba(237, 28, 36, .12);
        transform-origin: bottom center;
    }

    .request-leaf.one {
        right: 184px;
        transform: rotate(-42deg);
    }

    .request-leaf.two {
        right: 146px;
        height: 92px;
        transform: rotate(-22deg);
    }

    .request-leaf.three {
        right: 10px;
        height: 98px;
        transform: rotate(24deg);
    }

    .request-cloud {
        position: absolute;
        right: 46px;
        top: 20px;
        width: 72px;
        height: 28px;
        border: 4px solid rgba(237, 28, 36, .06);
        border-top-color: transparent;
        border-radius: 999px;
    }

    .request-cloud::before {
        content: "";
        position: absolute;
        left: 14px;
        top: -22px;
        width: 34px;
        height: 34px;
        border: 4px solid rgba(237, 28, 36, .06);
        border-bottom: 0;
        border-radius: 50% 50% 0 0;
    }

    .request-hill {
        position: absolute;
        right: -28px;
        bottom: -34px;
        width: 330px;
        height: 76px;
        border-radius: 50% 50% 0 0;
        background: #fff;
        border-top: 1px solid rgba(237, 28, 36, .08);
    }

    .request-section-heading {
        display: grid;
        grid-template-columns: auto max-content minmax(40px, 1fr);
        align-items: center;
        gap: 14px;
        margin-bottom: 10px;
    }

    .request-section-icon {
        width: 24px;
        height: 24px;
        display: grid;
        grid-template-columns: repeat(2, 8px);
        grid-template-rows: repeat(2, 8px);
        gap: 4px;
    }

    .request-section-icon span {
        border-radius: 3px;
        background: linear-gradient(135deg, #ff6949, var(--public-red));
    }

    .request-section-heading h2 {
        margin: 0;
        font-size: 1.18rem;
        font-weight: 800;
    }

    .request-section-line {
        height: 1px;
        background: var(--public-line);
    }

    .request-section-subtitle {
        margin: 0 0 28px;
        color: var(--public-muted);
        font-size: 1rem;
    }

    .request-card-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 28px;
    }

    .request-card {
        min-height: 318px;
        display: block;
        position: relative;
        padding: 38px 42px 36px;
        background: rgba(255, 255, 255, .92);
        border: 1px solid var(--public-line);
        border-radius: 14px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .06);
        color: inherit;
        text-decoration: none;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .request-card.is-featured {
        border-color: var(--public-red);
        box-shadow: 0 22px 48px rgba(237, 28, 36, .16);
    }

    .request-card:hover {
        border-color: var(--public-red);
        box-shadow: 0 22px 48px rgba(237, 28, 36, .15);
        color: inherit;
        transform: translateY(-2px);
    }

    .request-icon {
        width: 82px;
        height: 82px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 26px;
        background: #ffe9ec;
        color: var(--public-red);
        font-size: 2.15rem;
    }

    .request-card h3 {
        margin: 0 0 12px;
        color: var(--public-ink);
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .request-card p {
        min-height: 78px;
        margin: 0 0 28px;
        color: var(--public-muted);
        font-size: 1rem;
        line-height: 1.55;
    }

    .request-badge {
        position: absolute;
        right: 26px;
        top: 28px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 13px;
        border-radius: 999px;
        background: #ffe4e8;
        color: var(--public-red);
        font-size: .78rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .request-action {
        min-height: 52px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 0 22px;
        border: 1px solid var(--public-line);
        border-radius: 8px;
        background: linear-gradient(90deg, #fff3f5 0%, #ffffff 100%);
        color: var(--public-ink);
        font-weight: 800;
    }

    .request-card.is-featured .request-action,
    .request-card:hover .request-action {
        color: var(--public-red);
        border-color: #ffd6dc;
        background: linear-gradient(90deg, #fff0f2 0%, #fff6f7 100%);
    }

    .request-action i {
        font-size: 1.35rem;
    }

    .request-trust {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 46px;
        color: var(--public-muted);
        font-weight: 600;
    }

    .request-trust i {
        font-size: 1.15rem;
    }

    @media (max-width: 1199.98px) {
        .request-card-grid {
            gap: 20px;
        }

        .request-card {
            padding: 34px 32px 32px;
        }
    }

    @media (max-width: 991.98px) {
        .public-request-shell {
            width: min(100% - 48px, 900px);
        }

        .request-hero {
            grid-template-columns: auto minmax(0, 1fr) 280px;
            padding: 42px;
        }

        .request-card-grid {
            grid-template-columns: 1fr;
        }

        .request-card {
            min-height: 0;
            display: grid;
            grid-template-columns: 132px minmax(0, 1fr);
            gap: 0 34px;
            align-items: center;
        }

        .request-icon {
            grid-row: 1 / span 2;
            margin-bottom: 0;
        }

        .request-card p {
            min-height: 0;
            margin-bottom: 26px;
        }

        .request-action {
            grid-column: 1 / -1;
            margin-top: 6px;
        }
    }

    @media (max-width: 767.98px) {
        .public-request-header .public-request-shell {
            min-height: 88px;
        }

        .public-brand img {
            width: 154px;
        }

        .public-help {
            gap: 10px;
        }

        .public-help-icon {
            width: 40px;
            height: 40px;
        }

        .public-help-text {
            display: none;
        }

        .public-request-content {
            padding-top: 24px;
        }

        .request-hero {
            grid-template-columns: 148px minmax(0, 1fr);
            min-height: 342px;
            gap: 18px 20px;
            padding: 34px 42px 38px;
        }

        .request-hero-icon {
            width: 148px;
            height: 148px;
            border-radius: 26px;
        }

        .request-hero-qr {
            width: 68px;
            height: 68px;
        }

        .request-hero h1 {
            font-size: 2rem;
        }

        .request-hero p {
            font-size: 1.03rem;
        }

        .request-illustration {
            grid-column: 2;
            grid-row: 1 / span 2;
            min-height: 210px;
            width: 100%;
        }

        .request-building {
            right: 16px;
            bottom: -2px;
            width: 92px;
            height: 112px;
        }

        .request-hill {
            width: 260px;
            right: -72px;
        }

        .request-leaf.one {
            right: 126px;
        }

        .request-leaf.two {
            right: 96px;
        }

        .request-leaf.three {
            right: -10px;
        }

        .request-cloud {
            right: 12px;
        }

        .request-section-heading {
            grid-template-columns: auto minmax(0, max-content);
        }

        .request-section-line {
            display: none;
        }

        .request-card {
            padding: 32px 42px;
            grid-template-columns: 132px minmax(0, 1fr);
            gap: 0 22px;
        }

        .request-badge {
            right: 30px;
            top: 26px;
        }
    }

    @media (max-width: 575.98px) {
        .public-request-shell {
            width: min(100% - 28px, 460px);
        }

        .public-brand img {
            width: 132px;
        }

        .request-hero {
            min-height: 0;
            grid-template-columns: 1fr;
            padding: 28px 28px 170px;
        }

        .request-hero-icon {
            width: 96px;
            height: 96px;
        }

        .request-hero-qr {
            width: 48px;
            height: 48px;
        }

        .request-illustration {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 230px;
            min-height: 166px;
        }

        .request-hero h1 {
            font-size: 1.8rem;
        }

        .request-card {
            display: block;
            padding: 30px 28px;
        }

        .request-icon {
            width: 74px;
            height: 74px;
            margin-bottom: 22px;
            font-size: 1.9rem;
        }

        .request-card p {
            margin-bottom: 24px;
        }

        .request-badge {
            right: 20px;
            top: 24px;
            max-width: calc(100% - 120px);
            font-size: .72rem;
        }

        .request-trust {
            flex-wrap: wrap;
            text-align: center;
            margin-top: 34px;
        }
    }
</style>
@endsection

@section('content')
<div class="public-request-page">
    <header class="public-request-header">
        <div class="public-request-shell">
            <a href="{{ route('public.requests') }}" class="public-brand" aria-label="LRT Jakarta SatSet Public Request">
                <img src="{{ asset('assets/images/logo-lrtj.png') }}" alt="LRT Jakarta">
            </a>

            <a href="mailto:helpdesk@lrtjakarta.co.id" class="public-help">
                <span class="public-help-icon">
                    <i class="bi bi-question-circle"></i>
                </span>
                <span class="public-help-text">
                    <span class="public-help-title">Butuh bantuan?</span>
                    <span class="public-help-subtitle">Hubungi tim kami</span>
                </span>
            </a>
        </div>
    </header>

    <main class="public-request-content">
        <div class="public-request-shell">
            <section class="request-hero" aria-labelledby="public-request-title">
                <span class="request-hero-icon" aria-hidden="true">
                    <span class="request-hero-qr"></span>
                </span>
                <div>
                    <h1 id="public-request-title">Public Request Bagian Umum</h1>
                    <p>Pilih jenis permintaan atau laporan yang ingin dikirim. Anda tidak perlu login untuk mengirim request.</p>
                </div>
                <div class="request-illustration" aria-hidden="true">
                    <span class="request-cloud"></span>
                    <span class="request-leaf one"></span>
                    <span class="request-leaf two"></span>
                    <span class="request-building"></span>
                    <span class="request-leaf three"></span>
                    <span class="request-hill"></span>
                </div>
            </section>

            <section aria-labelledby="request-category-title">
                <div class="request-section-heading">
                    <span class="request-section-icon" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                    <h2 id="request-category-title">Pilih Kategori Request</h2>
                    <span class="request-section-line"></span>
                </div>
                <p class="request-section-subtitle">Pilih salah satu kategori di bawah ini sesuai kebutuhan Anda.</p>

                <div class="request-card-grid">
                    <a href="{{ route('public.ticket.konsumsi.create') }}" class="request-card is-featured">
                        <span class="request-badge">
                            <i class="bi bi-star-fill"></i>
                            Paling sering dipilih
                        </span>
                        <span class="request-icon">
                            <i class="bi bi-cup-hot"></i>
                        </span>
                        <h3>Konsumsi</h3>
                        <p>Ajukan kebutuhan konsumsi untuk rapat, kegiatan, pelatihan, atau acara operasional.</p>
                        <span class="request-action">
                            Pilih Konsumsi
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>

                    <a href="{{ route('public.ticket.atk-rtk.create') }}" class="request-card">
                        <span class="request-icon">
                            <i class="bi bi-box-seam"></i>
                        </span>
                        <h3>ATK / RTK</h3>
                        <p>Ajukan kebutuhan alat tulis kantor atau rumah tangga kantor untuk operasional.</p>
                        <span class="request-action">
                            Pilih ATK / RTK
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>

                    <a href="{{ route('public.ticket.ga-permintaan-temuan.create') }}" class="request-card">
                        <span class="request-icon">
                            <i class="bi bi-tools"></i>
                        </span>
                        <h3>Permintaan / Temuan</h3>
                        <p>Ajukan dukungan fasilitas atau laporkan temuan kerusakan dan kondisi area kerja.</p>
                        <span class="request-action">
                            Pilih Permintaan / Temuan
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </a>
                </div>
            </section>

            <div class="request-trust">
                <i class="bi bi-shield-check"></i>
                <span>SatSet Public Request · Aman, Cepat, dan Terpercaya</span>
            </div>
        </div>
    </main>
</div>
@endsection
