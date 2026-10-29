<aside class="pe-app-sidebar" id="sidebar">
    <div class="pe-app-sidebar-logo px-6 d-flex align-items-center position-relative">
        <a href="{{ url('/') }}" class="fs-18 fw-semibold">
            <img height="30" class="pe-app-sidebar-logo-default d-none" alt="Logo" src="{{ asset('assets/images/logo-lrtj.png') }}">
            <img height="30" class="pe-app-sidebar-logo-light d-none" alt="Logo" src="{{ asset('assets/images/logo-lrtj.png') }}">
            <img height="30" class="pe-app-sidebar-logo-minimize d-none" alt="Logo" src="{{ asset('assets/images/lrtj.png') }}">
            <img height="30" class="pe-app-sidebar-logo-minimize-light d-none" alt="Logo" src="{{ asset('assets/images/lrtj.png') }}">
        </a>
        </div> 

    <nav class="pe-app-sidebar-menu nav nav-pills" data-simplebar id="sidebar-simplebar">
        @php
            if (auth()->check()) {
                $roles = auth()->user()->role; 
                $menus = \App\Models\General\Menu::where('is_active', 1)
                    ->whereNull('parent_id')
                    ->with(['children' => function($query) {
                        $query->where('is_active', 1)->orderBy('order');
                    }])
                    ->orderBy('order')
                    ->get();

                $filteredMenus = $menus->filter(function ($menu) use ($roles) {
                    if (\App\Support\AccountAccess::isSuperadmin(auth()->user())) return true;
                    $roleIds = is_string($menu->role_id) ? json_decode($menu->role_id, true) : $menu->role_id;
                    return is_array($roleIds) && in_array($roles, $roleIds);
                });
            } else {
                $filteredMenus = collect();
            }

            $canAccessGaMenu = \App\Support\GaAccess::allowed(auth()->user());
            $canAccessApprovalMenu = auth()->check()
                && \Illuminate\Support\Facades\Schema::hasTable('approvals')
                && \App\Models\Master\Approval::where('approver_id', auth()->id())->exists();
            $pendingApprovalMenuCount = $canAccessApprovalMenu
                ? \App\Models\Master\Approval::where('approver_id', auth()->id())
                    ->whereRaw('LOWER(status) = ?', ['pending'])
                    ->count()
                : 0;
        @endphp

        <ul class="pe-main-menu list-unstyled">
            @foreach($filteredMenus as $menu)
                
                @if($menu->type == 'header')
                    <li class="pe-menu-title">{{ $menu->title }}</li>
                @else
                    
                    @php
                        // Filter sub-menu yang boleh dilihat user
                        $visibleChildren = $menu->children->filter(function($child) use ($roles) {
                            if (\App\Support\AccountAccess::isSuperadmin(auth()->user())) return true;
                            $childRoleIds = is_string($child->role_id) ? json_decode($child->role_id, true) : $child->role_id;
                            return is_array($childRoleIds) && in_array($roles, $childRoleIds);
                        });
                        $isAdminSettingsMenu = \App\Support\AccountAccess::isAdmin(auth()->user())
                            && (str_contains(strtolower((string) $menu->title), 'setting')
                                || str_contains(strtolower((string) $menu->title), 'pengaturan'));
                    @endphp

                    {{-- MENU TANPA SUB-MENU --}}
                    @if($visibleChildren->isEmpty() && !$isAdminSettingsMenu)
                        @php
                            $menuActive = $menu->url && $menu->url !== '#' && request()->routeIs($menu->url);
                        @endphp
                        <li class="pe-slide pe-has-sub">
                            <a href="{{ ($menu->url && $menu->url != '#') ? route($menu->url) : 'javascript:void(0)' }}" class="pe-nav-link {{ $menuActive ? 'active' : '' }}">
                                <i class="{{ $menu->icon }} pe-nav-icon"></i>
                                <span class="pe-nav-content">{{ $menu->title }}</span>
                            </a>
                        </li>
                    @else
                        {{-- MENU DENGAN SUB-MENU --}}
                        @php
                            $parentActive = $visibleChildren->contains(function ($child) {
                                return $child->url && $child->url !== '#' && request()->routeIs($child->url);
                            }) || ($isAdminSettingsMenu && request()->routeIs('settings.ticket-data.*', 'settings.it-service.*'));
                        @endphp
                        <li class="pe-slide pe-has-sub">
                            <a href="#collapseSide{{ $menu->id }}" class="pe-nav-link {{ $parentActive ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ $parentActive ? 'true' : 'false' }}" aria-controls="collapseSide{{ $menu->id }}">
                                <i class="{{ $menu->icon }} pe-nav-icon"></i>
                                <span class="pe-nav-content">{{ $menu->title }}</span>
                                <i class="ri-arrow-down-s-line pe-nav-arrow"></i>
                            </a>
                            <ul class="pe-slide-menu collapse {{ $parentActive ? 'show' : '' }}" id="collapseSide{{ $menu->id }}">
                                @foreach($visibleChildren as $child)
                                    @php
                                        $childActive = $child->url && $child->url !== '#' && request()->routeIs($child->url);
                                    @endphp
                                    <li class="pe-slide-item">
                                        <a href="{{ ($child->url && $child->url != '#') ? route($child->url) : 'javascript:void(0)' }}" class="pe-nav-link {{ $childActive ? 'active' : '' }}">
                                            {{ $child->title }}
                                        </a>
                                    </li>
                                @endforeach
                                @if($isAdminSettingsMenu)
                                    <li class="pe-slide-item">
                                        <a href="{{ route('settings.ticket-data.index') }}" class="pe-nav-link {{ request()->routeIs('settings.ticket-data.*') ? 'active' : '' }}">Backup & Data Tiket</a>
                                    </li>
                                    <li class="pe-slide-item"><a href="{{ route('settings.it-service.index') }}" class="pe-nav-link {{ request()->routeIs('settings.it-service.*') ? 'active' : '' }}">Status Layanan IT</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                @endif
            @endforeach

            @if($canAccessApprovalMenu)
            <li class="pe-menu-title">Persetujuan</li>
            <li class="pe-slide">
                <a href="{{ route('ticket.approvals') }}" class="pe-nav-link {{ request()->routeIs('ticket.approvals') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-check pe-nav-icon"></i>
                    <span class="pe-nav-content">Approval Saya</span>
                    @if($pendingApprovalMenuCount > 0)
                        <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingApprovalMenuCount > 99 ? '99+' : $pendingApprovalMenuCount }}</span>
                    @endif
                </a>
            </li>
            @endif

            <li class="pe-menu-title">Layanan IT</li>
            <li class="pe-slide">
                <a href="{{ route(\App\Support\ServiceDomains::itActive() ? 'it.index' : 'it.request') }}" class="pe-nav-link {{ request()->routeIs('it.index', 'it.show', 'it.request') ? 'active' : '' }}">
                    <i class="bi bi-laptop pe-nav-icon"></i><span class="pe-nav-content">Layanan IT</span>
                </a>
            </li>
            @if(\Illuminate\Support\Facades\Schema::hasTable('it_sla_clocks') && (\App\Support\ItAccess::operational(auth()->user()) || \App\Support\ItAccess::can(auth()->user(), 'view_it_queue') || \App\Support\ItAccess::can(auth()->user(), 'manage_it_configuration')))
            <li class="pe-slide"><a href="{{ route('it.dashboard') }}" class="pe-nav-link {{ request()->routeIs('it.dashboard') ? 'active' : '' }}"><i class="bi bi-bar-chart-line pe-nav-icon"></i><span class="pe-nav-content">Dashboard IT</span></a></li>
            @endif
            @if(\Illuminate\Support\Facades\Schema::hasTable('it_configuration_sets') && (\App\Support\ItAccess::can(auth()->user(), 'manage_it_configuration') || \App\Support\ItAccess::can(auth()->user(), 'publish_it_configuration')))
            <li class="pe-slide"><a href="{{ route('it.configuration.index') }}" class="pe-nav-link {{ request()->routeIs('it.configuration.*') ? 'active' : '' }}"><i class="bi bi-sliders pe-nav-icon"></i><span class="pe-nav-content">Konfigurasi IT</span></a></li>
            @endif
            @if($canAccessGaMenu)
            <li class="pe-menu-title">Operasional GA</li>
            @include('partials.ga-menu-items', ['topLevel' => true])
            @endif

        </ul>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
