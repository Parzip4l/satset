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

    <link rel="shortcut icon" href="{{ asset('assets/images/lrtj.png') }}">

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

    {{-- JS tambahan dari masing-masing page --}}
    @yield('js')
    
    {{-- Alias section untuk beberapa page yang menggunakan @section('script') --}}
    @yield('script')

</body>

</html>