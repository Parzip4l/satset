@php
    $menuIdPrefix = $menuIdPrefix ?? 'ga';
    $requestsActive = request()->routeIs(
        'ticket.ga-requests',
        'ticket.ga-permintaan-temuan.create',
        'ticket.konsumsi.create',
        'ticket.atk-rtk.create',
        'ticket.atk-rtk.warehouse'
    );
    $inventoryActive = request()->routeIs('bum.receivings', 'bum.stock-card', 'bum.opnames');
    $analyticsActive = request()->routeIs('bum.analytics', 'bum.reports', 'bum.guide');
    $masterActive = request()->routeIs('bum.items', 'bum.items.show', 'bum.uoms');
@endphp

<li class="pe-slide-item">
    <a href="{{ route('bum.dashboard') }}" class="pe-nav-link {{ request()->routeIs('bum.dashboard') ? 'active' : '' }}">Ringkasan GA</a>
</li>

<li class="pe-slide pe-has-sub">
    <a href="#{{ $menuIdPrefix }}Requests" class="pe-nav-link {{ $requestsActive ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ $requestsActive ? 'true' : 'false' }}" aria-controls="{{ $menuIdPrefix }}Requests">
        <span class="pe-nav-content">Permintaan</span>
        <i class="ri-arrow-down-s-line pe-nav-arrow ms-auto"></i>
    </a>
    <ul class="pe-slide-menu collapse {{ $requestsActive ? 'show' : '' }}" id="{{ $menuIdPrefix }}Requests">
        <li class="pe-slide-item"><a href="{{ route('ticket.ga-requests') }}" class="pe-nav-link {{ request()->routeIs('ticket.ga-requests') ? 'active' : '' }}">Daftar Permintaan</a></li>
        <li class="pe-slide-item"><a href="{{ route('ticket.ga-permintaan-temuan.create') }}" class="pe-nav-link {{ request()->routeIs('ticket.ga-permintaan-temuan.create') ? 'active' : '' }}">Permintaan / Temuan</a></li>
        <li class="pe-slide-item"><a href="{{ route('ticket.konsumsi.create') }}" class="pe-nav-link {{ request()->routeIs('ticket.konsumsi.create') ? 'active' : '' }}">Permintaan Konsumsi</a></li>
        <li class="pe-slide-item"><a href="{{ route('ticket.atk-rtk.create') }}" class="pe-nav-link {{ request()->routeIs('ticket.atk-rtk.create') ? 'active' : '' }}">Permintaan ATK / RTK</a></li>
        <li class="pe-slide-item"><a href="{{ route('ticket.atk-rtk.warehouse') }}" class="pe-nav-link {{ request()->routeIs('ticket.atk-rtk.warehouse') ? 'active' : '' }}">Proses Gudang</a></li>
    </ul>
</li>

<li class="pe-slide pe-has-sub">
    <a href="#{{ $menuIdPrefix }}Inventory" class="pe-nav-link {{ $inventoryActive ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ $inventoryActive ? 'true' : 'false' }}" aria-controls="{{ $menuIdPrefix }}Inventory">
        <span class="pe-nav-content">Inventori</span>
        <i class="ri-arrow-down-s-line pe-nav-arrow ms-auto"></i>
    </a>
    <ul class="pe-slide-menu collapse {{ $inventoryActive ? 'show' : '' }}" id="{{ $menuIdPrefix }}Inventory">
        <li class="pe-slide-item"><a href="{{ route('bum.receivings') }}" class="pe-nav-link {{ request()->routeIs('bum.receivings') ? 'active' : '' }}">Penerimaan Barang</a></li>
        <li class="pe-slide-item"><a href="{{ route('bum.stock-card') }}" class="pe-nav-link {{ request()->routeIs('bum.stock-card') ? 'active' : '' }}">Kartu Stok</a></li>
        <li class="pe-slide-item"><a href="{{ route('bum.opnames') }}" class="pe-nav-link {{ request()->routeIs('bum.opnames') ? 'active' : '' }}">Stock Opname</a></li>
    </ul>
</li>

<li class="pe-slide pe-has-sub">
    <a href="#{{ $menuIdPrefix }}Analytics" class="pe-nav-link {{ $analyticsActive ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ $analyticsActive ? 'true' : 'false' }}" aria-controls="{{ $menuIdPrefix }}Analytics">
        <span class="pe-nav-content">Analisis & Bantuan</span>
        <i class="ri-arrow-down-s-line pe-nav-arrow ms-auto"></i>
    </a>
    <ul class="pe-slide-menu collapse {{ $analyticsActive ? 'show' : '' }}" id="{{ $menuIdPrefix }}Analytics">
        <li class="pe-slide-item"><a href="{{ route('bum.analytics') }}" class="pe-nav-link {{ request()->routeIs('bum.analytics') ? 'active' : '' }}">Analytics & Forecast</a></li>
        <li class="pe-slide-item"><a href="{{ route('bum.reports') }}" class="pe-nav-link {{ request()->routeIs('bum.reports') ? 'active' : '' }}">Laporan</a></li>
        <li class="pe-slide-item"><a href="{{ route('bum.guide') }}" class="pe-nav-link {{ request()->routeIs('bum.guide') ? 'active' : '' }}">Panduan Penggunaan</a></li>
    </ul>
</li>

<li class="pe-slide pe-has-sub">
    <a href="#{{ $menuIdPrefix }}Master" class="pe-nav-link {{ $masterActive ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ $masterActive ? 'true' : 'false' }}" aria-controls="{{ $menuIdPrefix }}Master">
        <span class="pe-nav-content">Data Master</span>
        <i class="ri-arrow-down-s-line pe-nav-arrow ms-auto"></i>
    </a>
    <ul class="pe-slide-menu collapse {{ $masterActive ? 'show' : '' }}" id="{{ $menuIdPrefix }}Master">
        <li class="pe-slide-item"><a href="{{ route('bum.items') }}" class="pe-nav-link {{ request()->routeIs('bum.items', 'bum.items.show') ? 'active' : '' }}">Master Barang</a></li>
        <li class="pe-slide-item"><a href="{{ route('bum.uoms') }}" class="pe-nav-link {{ request()->routeIs('bum.uoms') ? 'active' : '' }}">Satuan Barang</a></li>
    </ul>
</li>
