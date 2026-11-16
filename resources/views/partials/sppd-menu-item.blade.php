@auth
    <li class="pe-slide">
        <a href="{{ route('sppd.index') }}" class="pe-nav-link {{ request()->routeIs('sppd.*') ? 'active' : '' }}">
            <i class="bi bi-briefcase pe-nav-icon"></i>
            <span class="pe-nav-content">Perjalanan Dinas</span>
        </a>
    </li>
@endauth
