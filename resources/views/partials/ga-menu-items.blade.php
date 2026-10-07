@php
    $topLevel = $topLevel ?? false;
    $gaMenuItems = [
        [
            'label' => 'Ringkasan GA',
            'route' => 'bum.dashboard',
            'patterns' => ['bum.dashboard'],
            'icon' => 'bi-speedometer2',
        ],
        [
            'label' => 'Daftar Permintaan',
            'route' => 'ticket.ga-requests',
            'patterns' => [
                'ticket.ga-requests',
                'ticket.ga-permintaan-temuan.create',
                'ticket.konsumsi.create',
                'ticket.atk-rtk.create',
            ],
            'icon' => 'bi-inbox',
        ],
        [
            'label' => 'Proses Gudang',
            'route' => 'ticket.atk-rtk.warehouse',
            'patterns' => ['ticket.atk-rtk.warehouse'],
            'icon' => 'bi-box-seam',
        ],
        [
            'label' => 'Penerimaan Barang',
            'route' => 'bum.receivings',
            'patterns' => ['bum.receivings'],
            'icon' => 'bi-box-arrow-in-down',
        ],
        [
            'label' => 'Kartu Stok',
            'route' => 'bum.stock-card',
            'patterns' => ['bum.stock-card'],
            'icon' => 'bi-card-list',
        ],
        [
            'label' => 'Stock Opname',
            'route' => 'bum.opnames',
            'patterns' => ['bum.opnames'],
            'icon' => 'bi-clipboard-check',
        ],
        [
            'label' => 'Analytics & Forecast',
            'route' => 'bum.analytics',
            'patterns' => ['bum.analytics'],
            'icon' => 'bi-graph-up-arrow',
        ],
        [
            'label' => 'Laporan',
            'route' => 'bum.reports',
            'patterns' => ['bum.reports'],
            'icon' => 'bi-file-earmark-bar-graph',
        ],
        [
            'label' => 'Master Barang',
            'route' => 'bum.items',
            'patterns' => ['bum.items', 'bum.items.show'],
            'icon' => 'bi-database',
        ],
        [
            'label' => 'Satuan Barang',
            'route' => 'bum.uoms',
            'patterns' => ['bum.uoms'],
            'icon' => 'bi-rulers',
        ],
        [
            'label' => 'Panduan',
            'route' => 'bum.guide',
            'patterns' => ['bum.guide'],
            'icon' => 'bi-question-circle',
        ],
    ];
@endphp

@foreach($gaMenuItems as $item)
    @php($itemActive = request()->routeIs(...$item['patterns']))
    <li class="{{ $topLevel ? 'pe-slide' : 'pe-slide-item' }} ga-compact-menu-item">
        <a href="{{ route($item['route']) }}" class="pe-nav-link {{ $itemActive ? 'active' : '' }}">
            @if($topLevel)
                <i class="bi {{ $item['icon'] }} pe-nav-icon"></i>
                <span class="pe-nav-content">{{ $item['label'] }}</span>
            @else
                {{ $item['label'] }}
            @endif
        </a>
    </li>
@endforeach
