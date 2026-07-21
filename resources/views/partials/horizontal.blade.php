
<aside class="pe-app-sidebar horizontal-sidebar" id="horizontal-aside">
    <div class="pe-app-sidebar-logo px-6 d-flex align-items-center position-relative">
        <!--begin::Brand Image-->
        <a href="index" class="fs-18 fw-semibold">
            <img height="30" class="pe-app-sidebar-logo-default d-none" alt="Logo" src="{{ asset('assets/images/logo-lrtj.png') }}">
            <img height="30" class="pe-app-sidebar-logo-light d-none" alt="Logo" src="{{ asset('assets/images/logo-lrtj.png') }}">
            <img height="30" class="pe-app-sidebar-logo-minimize d-none" alt="Logo" src="{{ asset('assets/images/lrtj.png') }}">
            <img height="30" class="pe-app-sidebar-logo-minimize-light d-none" alt="Logo" src="{{ asset('assets/images/lrtj.png') }}">
            <!-- FabKin -->
        </a>
        <!--end::Brand Image-->
    </div> 
    <!-- data-simplebar id="sidebar-simplebar" -->
    <nav class="pe-app-sidebar-menu nav nav-pills">
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
    @endphp

    <ul class="pe-horizontal-menu list-unstyled" id="horizontal-menu">
        @foreach($filteredMenus as $menu)
            
            @if($menu->type == 'header')
                <li class="pe-menu-title">{{ $menu->title }}</li>
            @else
                
                @php
                    // Filter children berdasarkan role terlebih dahulu
                    $visibleChildren = $menu->children->filter(function($child) use ($roles) {
                        $childRoleIds = is_string($child->role_id) ? json_decode($child->role_id, true) : $child->role_id;
                        return is_array($childRoleIds) && in_array($roles, $childRoleIds);
                    });
                @endphp

                {{-- MENU TANPA SUB-MENU --}}
                @if($visibleChildren->isEmpty())
                    <li class="pe-slide">
                        <a href="{{ ($menu->url && $menu->url != '#') ? route($menu->url) : 'javascript:void(0)' }}" class="pe-nav-link">
                            {{-- Gunakan !! !! jika icon mengandung tag html, atau pastikan class terpanggil --}}
                            <i class="{{ $menu->icon }} pe-nav-icon"></i>
                            <span class="pe-nav-content">{{ $menu->title }}</span>
                        </a>
                    </li>
                @else
                    {{-- MENU DENGAN SUB-MENU --}}
                    <li class="pe-slide pe-has-sub">
                        <a href="#collapse{{ $menu->id }}" class="pe-nav-link" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse{{ $menu->id }}">
                            <i class="{{ $menu->icon }} pe-nav-icon"></i>
                            <span class="pe-nav-content">{{ $menu->title }}</span>
                            <i class="ri-arrow-down-s-line pe-nav-arrow"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapse{{ $menu->id }}">
                            @foreach($visibleChildren as $child)
                                <li class="pe-slide-item">
                                    <a href="{{ ($child->url && $child->url != '#') ? route($child->url) : 'javascript:void(0)' }}" class="pe-nav-link">
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
        <li class="pe-slide pe-has-sub">
            <a href="#collapseBumHorizontal" class="pe-nav-link {{ $bumMenuActive ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ $bumMenuActive ? 'true' : 'false' }}" aria-controls="collapseBumHorizontal">
                <i class="bi bi-box-seam pe-nav-icon"></i>
                <span class="pe-nav-content">GA & Inventori</span>
                <i class="ri-arrow-down-s-line pe-nav-arrow"></i>
            </a>
            <ul class="pe-slide-menu collapse {{ $bumMenuActive ? 'show' : '' }}" id="collapseBumHorizontal">
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
    </ul>
</nav>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
