@if(\App\Support\ProcurementAccess::allowed(auth()->user()))
    @php
        $procurementDetailActive = request()->routeIs('ticket.show')
            && request()->route('ticket') instanceof \App\Models\Master\Ticket
            && \App\Support\ProcurementAccess::is(request()->route('ticket'));
        $procurementGroupActive = request()->routeIs('procurement.*') || $procurementDetailActive;
        $procurementMenuId = $topLevel ? 'collapseGaProcurementVertical' : 'collapseGaProcurementHorizontal';
    @endphp
    <li class="{{ $topLevel ? 'pe-slide' : 'pe-slide-item' }} pe-has-sub ga-compact-menu-item">
        <a href="#{{ $procurementMenuId }}" class="pe-nav-link {{ $procurementGroupActive ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ $procurementGroupActive ? 'true' : 'false' }}" aria-controls="{{ $procurementMenuId }}">
            @if($topLevel)<i class="bi bi-cart-check pe-nav-icon"></i>@endif
            <span class="pe-nav-content">Pengadaan Barang</span>
            <i class="ri-arrow-down-s-line pe-nav-arrow"></i>
        </a>
        <ul class="pe-slide-menu collapse {{ $procurementGroupActive ? 'show' : '' }}" id="{{ $procurementMenuId }}">
            <li class="pe-slide-item"><a href="{{ route('procurement.index') }}" class="pe-nav-link {{ request()->routeIs('procurement.index', 'procurement.create') || $procurementDetailActive ? 'active' : '' }}">Daftar Pengajuan</a></li>
            <li class="pe-slide-item"><a href="{{ route('procurement.reports') }}" class="pe-nav-link {{ request()->routeIs('procurement.reports*') ? 'active' : '' }}">Laporan Pengadaan</a></li>
        </ul>
    </li>
@endif
