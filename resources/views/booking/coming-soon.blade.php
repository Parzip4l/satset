@extends('partials.layouts.master')

@section('title', 'Booking Room')
@section('title-sub', 'Facility Management')
@section('pagetitle', 'Booking Room')

@section('css')
<style>
    .booking-development {
        min-height: calc(100vh - 250px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 0 56px;
    }

    .booking-development-card {
        width: min(100%, 980px);
        overflow: hidden;
        border: 1px solid #edf0f4;
        border-radius: 24px;
        background: #fff;
        box-shadow: 0 20px 50px rgba(23, 32, 51, 0.08);
    }

    .booking-development-copy {
        padding: clamp(32px, 6vw, 72px);
    }

    .booking-development-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border: 1px solid rgba(226, 26, 26, 0.16);
        border-radius: 999px;
        background: rgba(226, 26, 26, 0.07);
        color: #d71920;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .booking-development-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #e21a1a;
        box-shadow: 0 0 0 5px rgba(226, 26, 26, 0.1);
    }

    .booking-development-title {
        margin: 22px 0 14px;
        color: #172033;
        font-size: clamp(30px, 4vw, 46px);
        font-weight: 800;
        letter-spacing: -0.04em;
        line-height: 1.08;
    }

    .booking-development-description {
        max-width: 510px;
        margin: 0;
        color: #6f798c;
        font-size: 16px;
        line-height: 1.75;
    }

    .booking-development-note {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 26px;
        color: #525d71;
        font-size: 13px;
        font-weight: 600;
    }

    .booking-development-visual {
        min-height: 430px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 36px;
        background:
            radial-gradient(circle at 80% 16%, rgba(255,255,255,.9) 0 7%, transparent 7.5%),
            linear-gradient(145deg, #fff5f5 0%, #f9fafc 58%, #eef2f7 100%);
    }

    .booking-development-visual svg {
        width: min(100%, 390px);
        height: auto;
        filter: drop-shadow(0 22px 22px rgba(43, 55, 79, 0.10));
    }

    @media (max-width: 991.98px) {
        .booking-development-copy { text-align: center; }
        .booking-development-description { margin-inline: auto; }
        .booking-development-note { justify-content: center; }
        .booking-development-visual { min-height: 340px; }
    }
</style>
@endsection

@section('content')
<div class="booking-development">
    <div class="booking-development-card">
        <div class="row g-0 align-items-stretch">
            <div class="col-lg-6">
                <div class="booking-development-copy h-100 d-flex flex-column justify-content-center">
                    <div>
                        <span class="booking-development-badge">
                            <span class="booking-development-dot"></span>
                            Dalam Pengembangan
                        </span>
                        <h1 class="booking-development-title">Booking Room segera hadir.</h1>
                        <p class="booking-development-description">
                            Kami sedang menyiapkan pengalaman pemesanan ruang rapat yang lebih praktis, mulai dari melihat ketersediaan hingga mengatur jadwal dalam satu tempat.
                        </p>
                        <div class="booking-development-note">
                            <i class="ri-tools-line fs-5 text-primary"></i>
                            Fitur sedang disempurnakan oleh tim pengembang.
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="booking-development-visual" aria-hidden="true">
                    <svg viewBox="0 0 520 430" role="img" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="calendarGradient" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#ed2931"/>
                                <stop offset="1" stop-color="#c81018"/>
                            </linearGradient>
                            <linearGradient id="tableGradient" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#42506a"/>
                                <stop offset="1" stop-color="#202a3e"/>
                            </linearGradient>
                        </defs>

                        <ellipse cx="260" cy="384" rx="190" ry="25" fill="#dfe4ec" opacity=".75"/>

                        <rect x="72" y="70" width="376" height="236" rx="22" fill="#fff" stroke="#dce1ea" stroke-width="4"/>
                        <path d="M94 70h332c12 0 22 10 22 22v48H72V92c0-12 10-22 22-22z" fill="url(#calendarGradient)"/>
                        <circle cx="112" cy="105" r="8" fill="#fff" opacity=".95"/>
                        <circle cx="140" cy="105" r="8" fill="#fff" opacity=".7"/>
                        <rect x="328" y="96" width="88" height="18" rx="9" fill="#fff" opacity=".9"/>

                        <g fill="#edf0f5">
                            <rect x="98" y="166" width="54" height="38" rx="8"/>
                            <rect x="165" y="166" width="54" height="38" rx="8"/>
                            <rect x="232" y="166" width="54" height="38" rx="8"/>
                            <rect x="299" y="166" width="54" height="38" rx="8"/>
                            <rect x="366" y="166" width="54" height="38" rx="8"/>
                            <rect x="98" y="218" width="54" height="38" rx="8"/>
                            <rect x="165" y="218" width="54" height="38" rx="8"/>
                            <rect x="299" y="218" width="54" height="38" rx="8"/>
                            <rect x="366" y="218" width="54" height="38" rx="8"/>
                        </g>
                        <rect x="232" y="218" width="54" height="38" rx="8" fill="#ffe0e1"/>
                        <path d="M248 237l8 8 16-18" fill="none" stroke="#e21a1a" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>

                        <path d="M166 327h188l42 54H124z" fill="url(#tableGradient)"/>
                        <path d="M161 381h10v24h-10zm188 0h10v24h-10z" fill="#202a3e"/>

                        <g fill="#fff" stroke="#d8dee8" stroke-width="3">
                            <rect x="118" y="292" width="50" height="68" rx="15"/>
                            <rect x="352" y="292" width="50" height="68" rx="15"/>
                        </g>
                        <g fill="#e21a1a">
                            <circle cx="143" cy="309" r="7"/>
                            <circle cx="377" cy="309" r="7"/>
                        </g>

                        <circle cx="400" cy="61" r="43" fill="#fff" stroke="#dce1ea" stroke-width="4"/>
                        <path d="M400 39v23l15 10" fill="none" stroke="#e21a1a" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="400" cy="61" r="4" fill="#e21a1a"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
