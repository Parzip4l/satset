<li class="pe-slide-item">
    <a href="{{ route('bum.dashboard') }}" class="pe-nav-link {{ request()->routeIs('bum.dashboard') ? 'active' : '' }}">Ringkasan GA</a>
</li>

<li class="pe-menu-title mt-2 mb-1 opacity-75">Permintaan</li>
<li class="pe-slide-item">
    <a href="{{ route('ticket.ga-requests') }}" class="pe-nav-link {{ request()->routeIs('ticket.ga-requests') ? 'active' : '' }}">Daftar Permintaan</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('ticket.ga-permintaan-temuan.create') }}" class="pe-nav-link {{ request()->routeIs('ticket.ga-permintaan-temuan.create') ? 'active' : '' }}">Permintaan / Temuan</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('ticket.konsumsi.create') }}" class="pe-nav-link {{ request()->routeIs('ticket.konsumsi.create') ? 'active' : '' }}">Permintaan Konsumsi</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('ticket.atk-rtk.create') }}" class="pe-nav-link {{ request()->routeIs('ticket.atk-rtk.create') ? 'active' : '' }}">Permintaan ATK / RTK</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('ticket.atk-rtk.warehouse') }}" class="pe-nav-link {{ request()->routeIs('ticket.atk-rtk.warehouse') ? 'active' : '' }}">Proses Gudang ATK / RTK</a>
</li>

<li class="pe-menu-title mt-2 mb-1 opacity-75">Inventori</li>
<li class="pe-slide-item">
    <a href="{{ route('bum.items') }}" class="pe-nav-link {{ request()->routeIs('bum.items', 'bum.items.show') ? 'active' : '' }}">Master Barang</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('bum.receivings') }}" class="pe-nav-link {{ request()->routeIs('bum.receivings') ? 'active' : '' }}">Penerimaan Barang</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('bum.stock-card') }}" class="pe-nav-link {{ request()->routeIs('bum.stock-card') ? 'active' : '' }}">Kartu Stok</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('bum.opnames') }}" class="pe-nav-link {{ request()->routeIs('bum.opnames') ? 'active' : '' }}">Stock Opname</a>
</li>

<li class="pe-menu-title mt-2 mb-1 opacity-75">Analisis & Bantuan</li>
<li class="pe-slide-item">
    <a href="{{ route('bum.analytics') }}" class="pe-nav-link {{ request()->routeIs('bum.analytics') ? 'active' : '' }}">Analytics & Forecast</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('bum.reports') }}" class="pe-nav-link {{ request()->routeIs('bum.reports') ? 'active' : '' }}">Laporan</a>
</li>
<li class="pe-slide-item">
    <a href="{{ route('bum.guide') }}" class="pe-nav-link {{ request()->routeIs('bum.guide') ? 'active' : '' }}">Panduan Penggunaan</a>
</li>
