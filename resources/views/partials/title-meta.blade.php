<meta charset="utf-8" />
{{-- Menggunakan variabel title dari controller atau default --}}
<title>@yield('title', 'Dashboard') | SatSet Admin & Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<meta content="SatSet Ticketing System - Efficient IT Support" name="description" />
<meta content="IT LRTJ Division" name="author" />

<meta name="csrf-token" content="{{ csrf_token() }}">

<script type="module" src="{{ asset('assets/js/layout-setup.js') }}"></script>

<link rel="shortcut icon" href="{{ asset('assets/images/k_favicon_32x.png') }}">