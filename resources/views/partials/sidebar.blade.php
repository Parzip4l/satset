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
        @endphp

        @if($filteredMenus->isNotEmpty())
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
                        <li class="pe-slide pe-has-sub">
                            <a href="{{ ($menu->url && $menu->url != '#') ? route($menu->url) : 'javascript:void(0)' }}" class="pe-nav-link">
                                <i class="{{ $menu->icon }} pe-nav-icon"></i>
                                <span class="pe-nav-content">{{ $menu->title }}</span>
                            </a>
                        </li>
                    @else
                        {{-- MENU DENGAN SUB-MENU --}}
                        <li class="pe-slide pe-has-sub">
                            <a href="#collapseSide{{ $menu->id }}" class="pe-nav-link" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapseSide{{ $menu->id }}">
                                <i class="{{ $menu->icon }} pe-nav-icon"></i>
                                <span class="pe-nav-content">{{ $menu->title }}</span>
                                <i class="ri-arrow-down-s-line pe-nav-arrow"></i>
                            </a>
                            <ul class="pe-slide-menu collapse" id="collapseSide{{ $menu->id }}">
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
        </ul>
        @endif
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>