<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    {{-- Menangani judul halaman secara dinamis --}}
    <title>@yield('title') | SatSet Admin & Dashboard</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta content="SatSet Ticketing System - IT LRTJ" name="description" />
    <meta content="IT LRTJ Division" name="author" />

    {{-- CSRF Token: WAJIB untuk AJAX (seperti delete ticket & filter) --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script type="module" src="{{ asset('assets/js/layout-setup.js') }}"></script>

    <link rel="shortcut icon" href="{{ asset('assets/images/k_favicon_32x.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#e31b23">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/satset-icon-192.png') }}">

    {{-- CSS tambahan dari masing-masing page --}}
    @yield('css')

    {{-- Global CSS (Bootstrap, Icons, dll) --}}
    @include('partials.head-css')
</head>

<body>

    {{-- Pengaturan Header & Sidebar --}}
    @include('partials.header')
    @include('partials.sidebar')
    @include('partials.horizontal')

    <main class="app-wrapper">
        <div class="container-fluid">

            {{-- Breadcrumb / Judul Halaman Otomatis --}}
            @include('partials.page-title')

            {{-- Konten Utama --}}
            @yield('content')
            
        </div>
    </main>

    {{-- Komponen Pelengkap --}}
    @include('partials.switcher')
    @include('partials.scroll-to-top')
    @include('partials.footer')

    {{-- Global Scripts (jQuery, Bootstrap JS, dll) --}}
    @include('partials.vendor-scripts')
    <script src="{{ asset('assets/js/file-upload-guard.js') }}?v={{ filemtime(public_path('assets/js/file-upload-guard.js')) }}"></script>
    @include('partials.web-push')

    {{-- JS tambahan dari masing-masing page --}}
    @yield('js')
    
    {{-- Alias section untuk beberapa page yang menggunakan @section('script') --}}
    @yield('script')

</body>

</html>
