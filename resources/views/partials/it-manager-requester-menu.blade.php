@if(\App\Support\ItAccess::isManager(auth()->user()))
    @php
        $hasRequesterMenu = $filteredMenus->contains(function ($menu) use ($roles) {
            if ($menu->url === 'ticket.index') return true;
            return $menu->children->contains(function ($child) use ($roles) {
                $childRoles = is_string($child->role_id) ? json_decode($child->role_id, true) : $child->role_id;
                return $child->url === 'ticket.index' && is_array($childRoles) && count(array_intersect($roles, $childRoles)) > 0;
            });
        });
    @endphp
    @unless($hasRequesterMenu)
    <li class="pe-slide"><a href="{{ route('ticket.index') }}" class="pe-nav-link {{ request()->routeIs('ticket.*', 'procurement.create', 'procurement.store') ? 'active' : '' }}"><i class="bi bi-send pe-nav-icon"></i><span class="pe-nav-content">Pengajuan Satset</span></a></li>
    @endunless
@endif
