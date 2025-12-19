<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>@yield('title') | SatSet Admin & Dashboards</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta content="SatSet Ticketing System - IT LRTJ" name="description" />
    <meta content="IT LRTJ" name="author" />

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script type="module" src="{{ asset('assets/js/layout-setup.js') }}"></script>

    <link rel="shortcut icon" href="{{ asset('assets/images/lrtj.png') }}">

    @yield('css')

    @include('partials.head-css')
</head>

<body>

    @include('partials.header')

    @include('partials.sidebar')

    <main class="app-wrapper">
        <div class="container-fluid">
            
            @include('partials.page-title')

            @yield('content')

        </div></main>@include('partials.switcher')
    @include('partials.scroll-to-top')
    @include('partials.footer')

    @include('partials.vendor-scripts')

    @yield('js')
    @yield('script') {{-- Tambahan jika ada halaman yang memakai section 'script' --}}

</body>

</html>