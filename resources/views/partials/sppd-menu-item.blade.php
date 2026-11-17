@auth
    <li class="pe-slide">
        <a href="{{ route('sppd.index') }}" class="pe-nav-link {{ request()->routeIs('sppd.*') ? 'active' : '' }}">
            <i class="bi bi-briefcase pe-nav-icon"></i>
            <span class="pe-nav-content">Perjalanan Dinas</span>
        </a>
    </li>
    @if(\App\Support\SppdAccess::isOperator(auth()->user()))
        <li class="pe-slide">
            <a href="{{ route('settings.sppd-service.index') }}" class="pe-nav-link {{ request()->routeIs('settings.sppd-service.*') ? 'active' : '' }}">
                <i class="bi bi-sliders pe-nav-icon"></i>
                <span class="pe-nav-content">Status SPPD</span>
            </a>
        </li>
        <li class="pe-slide">
            <a href="{{ route('ticket.index') }}" class="pe-nav-link {{ request()->routeIs('ticket.*', 'it.*') ? 'active' : '' }}">
                <i class="bi bi-ticket-perforated pe-nav-icon"></i>
                <span class="pe-nav-content">Permintaan</span>
            </a>
        </li>
    @endif
@endauth
