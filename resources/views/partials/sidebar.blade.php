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
                    $roleIds = is_string($menu->role_id) ? json_decode($menu->role_id, true) : $menu->role_id;
                    return is_array($roleIds) && in_array($roles, $roleIds);
                });
            } else {
                $filteredMenus = collect();
            }

            $canAccessGaMenu = \App\Support\GaAccess::allowed(auth()->user());
        @endphp

        <ul class="pe-main-menu list-unstyled">
            @foreach($filteredMenus as $menu)
                
                @if($menu->type == 'header')
                    <li class="pe-menu-title">{{ $menu->title }}</li>
                @else
                    
                    @php
                        // Filter sub-menu yang boleh dilihat user
                        $visibleChildren = $menu->children->filter(function($child) use ($roles) {
                            $childRoleIds = is_string($child->role_id) ? json_decode($child->role_id, true) : $child->role_id;
                            return is_array($childRoleIds) && in_array($roles, $childRoleIds);
                        });
                    @endphp

                    {{-- MENU TANPA SUB-MENU --}}
                    @if($visibleChildren->isEmpty())
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
                            });
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
                            </ul>
                        </li>
                    @endif

                @endif
            @endforeach

            @php
                $bumMenuActive = request()->routeIs('bum.*')
                    || request()->routeIs('ticket.ga-permintaan-temuan.create')
                    || request()->routeIs('ticket.atk-rtk.create')
                    || request()->routeIs('ticket.atk-rtk.warehouse');
            @endphp
            @if($canAccessGaMenu)
            <li class="pe-menu-title">Operasional GA</li>
            <li class="pe-slide pe-has-sub">
                <a href="#collapseSideBum" class="pe-nav-link {{ $bumMenuActive ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ $bumMenuActive ? 'true' : 'false' }}" aria-controls="collapseSideBum">
                    <i class="bi bi-box-seam pe-nav-icon"></i>
                    <span class="pe-nav-content">GA & Inventori</span>
                    <i class="ri-arrow-down-s-line pe-nav-arrow"></i>
                </a>
                <ul class="pe-slide-menu collapse {{ $bumMenuActive ? 'show' : '' }}" id="collapseSideBum">
                    <li class="pe-slide-item">
                        <a href="{{ route('bum.dashboard') }}" class="pe-nav-link {{ request()->routeIs('bum.dashboard') ? 'active' : '' }}">Ringkasan</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('bum.guide') }}" class="pe-nav-link {{ request()->routeIs('bum.guide') ? 'active' : '' }}">Manual Guide</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('ticket.ga-permintaan-temuan.create') }}" class="pe-nav-link {{ request()->routeIs('ticket.ga-permintaan-temuan.create') ? 'active' : '' }}">Input Permintaan / Temuan</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('ticket.atk-rtk.create') }}" class="pe-nav-link {{ request()->routeIs('ticket.atk-rtk.create') ? 'active' : '' }}">Request ATK / RTK</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('ticket.atk-rtk.warehouse') }}" class="pe-nav-link {{ request()->routeIs('ticket.atk-rtk.warehouse') ? 'active' : '' }}">Gudang ATK / RTK</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('bum.items') }}" class="pe-nav-link {{ request()->routeIs('bum.items', 'bum.items.show') ? 'active' : '' }}">Master Barang</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('bum.receivings') }}" class="pe-nav-link {{ request()->routeIs('bum.receivings') ? 'active' : '' }}">Penerimaan Barang</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('bum.stock-card') }}" class="pe-nav-link {{ request()->routeIs('bum.stock-card') ? 'active' : '' }}">Stock Card</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('bum.opnames') }}" class="pe-nav-link {{ request()->routeIs('bum.opnames') ? 'active' : '' }}">Stock Opname</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('bum.analytics') }}" class="pe-nav-link {{ request()->routeIs('bum.analytics') ? 'active' : '' }}">Analytics & Forecast</a>
                    </li>
                    <li class="pe-slide-item">
                        <a href="{{ route('bum.reports') }}" class="pe-nav-link {{ request()->routeIs('bum.reports') ? 'active' : '' }}">Laporan</a>
                    </li>
                </ul>
            </li>
            @endif
        </ul>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
