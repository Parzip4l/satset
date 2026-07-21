<link rel="stylesheet" href="{{ asset('assets/libs/simplebar/simplebar.min.css') }}">
<link href="{{ asset('assets/libs/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/libs/nouislider/nouislider.min.css') }}" rel="stylesheet">

<link href="{{ asset('assets/css/bootstrap.min.css') }}" id="bootstrap-style" rel="stylesheet" type="text/css">
<link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

<link href="{{ asset('assets/css/app.min.css') }}" id="app-style" rel="stylesheet" type="text/css">

<style>
    :root {
        --brand-primary: #e21a1a;
        --brand-primary-rgb: 226, 26, 26;
        --brand-primary-hover: #b91c1c;
        --brand-primary-hover-rgb: 185, 28, 28;
        --bs-primary: var(--brand-primary);
        --bs-primary-rgb: var(--brand-primary-rgb);
        --pe-primary: var(--brand-primary);
        --pe-primary-rgb: var(--brand-primary-rgb);
        --pe-link-color-rgb: var(--brand-primary-rgb);
        --pe-link-hover-color-rgb: var(--brand-primary-hover-rgb);
        --pe-primary-bg-subtle: rgba(var(--brand-primary-rgb), 0.1);
        --pe-primary-border-subtle: rgba(var(--brand-primary-rgb), 0.45);
    }
    
    .card {
        border-radius: 12px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    /* Styling tambahan untuk elemen yang kita buat sebelumnya */
    .btn-primary {
        --bs-btn-bg: var(--brand-primary);
        --bs-btn-border-color: var(--brand-primary);
        --bs-btn-hover-bg: var(--brand-primary-hover);
        --bs-btn-hover-border-color: var(--brand-primary-hover);
        --bs-btn-active-bg: var(--brand-primary-hover);
        --bs-btn-active-border-color: var(--brand-primary-hover);
        --bs-btn-disabled-bg: var(--brand-primary);
        --bs-btn-disabled-border-color: var(--brand-primary);
    }
    .btn-outline-primary {
        --bs-btn-color: var(--brand-primary);
        --bs-btn-border-color: var(--brand-primary);
        --bs-btn-hover-bg: var(--brand-primary);
        --bs-btn-hover-border-color: var(--brand-primary);
        --bs-btn-active-bg: var(--brand-primary-hover);
        --bs-btn-active-border-color: var(--brand-primary-hover);
    }
    .text-primary { color: var(--brand-primary) !important; }
    .bg-primary { background-color: var(--brand-primary) !important; }
    .border-primary { border-color: var(--brand-primary) !important; }
    .link-primary { color: var(--brand-primary) !important; }
    .bg-primary-subtle, .bg-soft-primary { background-color: rgba(var(--brand-primary-rgb), 0.1) !important; }
    .border-primary-subtle { border-color: rgba(var(--brand-primary-rgb), 0.45) !important; }
    .form-control:focus, .form-select:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 0.2rem rgba(var(--brand-primary-rgb), 0.14);
    }
    aside.pe-app-sidebar .pe-app-sidebar-menu .pe-nav-link {
        border-radius: 0 14px 14px 0;
        margin: 2px 10px 2px 0;
        position: relative;
    }
    aside.pe-app-sidebar .pe-app-sidebar-menu .pe-nav-link.active {
        background-color: rgba(var(--brand-primary-rgb), 0.1) !important;
        color: var(--brand-primary) !important;
        font-weight: 800;
    }
    aside.pe-app-sidebar .pe-app-sidebar-menu .pe-nav-link.active::before {
        background-color: var(--brand-primary);
        border-radius: 0 999px 999px 0;
        content: "";
        height: 100%;
        left: -10px;
        position: absolute;
        top: 0;
        width: 5px;
    }
    aside.pe-app-sidebar .pe-app-sidebar-menu .pe-nav-link.active .pe-nav-icon,
    aside.pe-app-sidebar .pe-app-sidebar-menu .pe-nav-link.active .pe-nav-content,
    aside.pe-app-sidebar .pe-app-sidebar-menu .pe-nav-link.active .pe-nav-arrow {
        color: var(--brand-primary) !important;
    }
    aside.pe-app-sidebar .pe-app-sidebar-menu .pe-slide-menu .pe-nav-link.active {
        border-radius: 8px;
        margin-left: 0;
    }
    .bg-success-subtle { background-color: rgba(25, 135, 84, 0.1) !important; }
    .bg-warning-subtle { background-color: rgba(255, 193, 7, 0.1) !important; }
    .bg-danger-subtle { background-color: rgba(220, 53, 69, 0.1) !important; }
    .bg-info-subtle { background-color: rgba(13, 202, 240, 0.1) !important; }

    .avatar-md { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; }
</style>
