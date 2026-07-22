@extends('partials.layouts.master')

@section('title', 'Master Barang Habis Pakai')
@section('css')
    @include('bum.partials.mobile-style')
    <style>
        .items-page {
            --item-ink: #1f2933;
            --item-muted: #7b8794;
            --item-line: #e7ecf2;
            --item-soft: #f8fafc;
            --item-primary: #e21a1a;
        }

        .items-page .page-title {
            color: var(--item-ink);
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .3rem;
        }

        .items-page .page-subtitle,
        .items-page .muted-text {
            color: var(--item-muted);
        }

        .items-actions,
        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
        }

        .items-btn {
            border-radius: 8px;
            font-size: .84rem;
            font-weight: 800;
            min-height: 36px;
            padding: .45rem .85rem;
        }

        .items-card {
            background: #fff;
            border: 1px solid var(--item-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
            overflow: hidden;
        }

        .filter-card {
            padding: 1rem;
        }

        .items-table thead th {
            background: var(--item-soft);
            border-bottom: 1px solid var(--item-line);
            color: #394150;
            font-size: .78rem;
            font-weight: 800;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .items-table tbody td {
            border-color: var(--item-line);
            color: #364152;
            font-size: .84rem;
            padding: .82rem 1rem;
            vertical-align: middle;
        }

        .soft-badge {
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 800;
            padding: .32rem .55rem;
        }

        .stock-chip {
            color: var(--item-ink);
            font-weight: 800;
        }

        .stock-chip.low {
            color: #ef476f;
        }

        .item-modal .modal-dialog {
            max-width: min(980px, calc(100vw - 2rem));
        }

        .item-modal .modal-content {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
        }

        .item-modal .modal-header,
        .item-modal .modal-footer {
            border-color: var(--item-line);
            padding: 1.1rem 1.35rem;
        }

        .item-modal .modal-title {
            color: var(--item-ink);
            font-size: 1.05rem;
            font-weight: 800;
        }

        .item-modal .modal-body {
            padding: 1.35rem;
        }

        .form-section-title {
            color: var(--item-ink);
            font-size: .82rem;
            font-weight: 900;
            letter-spacing: .02em;
            margin-bottom: .9rem;
            text-transform: uppercase;
        }

        .items-pagination {
            align-items: center;
            border-top: 1px solid var(--item-line);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1rem;
        }

        .items-pagination .pagination {
            margin-bottom: 0;
        }

        .items-pagination .page-link {
            border-radius: 6px;
            color: #596575;
            font-size: .78rem;
            font-weight: 800;
            margin: 0 .1rem;
            min-width: 32px;
            text-align: center;
        }

        .items-pagination .active > .page-link {
            background: var(--item-primary);
            border-color: var(--item-primary);
            color: #fff;
        }

        .empty-state {
            color: var(--item-muted);
            padding: 3rem 1rem;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .items-actions,
            .filter-actions,
            .items-pagination {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page items-page">
    @php
        $sortLink = function (string $key) {
            $currentSort = request('sort', 'name');
            $currentDirection = request('direction', 'asc');
            $nextDirection = $currentSort === $key && $currentDirection === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDirection, 'page' => null]);
        };
        $sortIcon = function (string $key) {
            if (request('sort', 'name') !== $key) {
                return 'bi-arrow-down-up text-muted';
            }

            return request('direction', 'asc') === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
        };
    @endphp

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <h3 class="page-title">Master Barang Habis Pakai</h3>
            <p class="page-subtitle mb-0">Kelola ATK/RTK, UOM besar-kecil, stok Gudang Besar, Gudang Kecil, dan binloc.</p>
        </div>
        <div class="items-actions">
            <a href="{{ route('bum.dashboard') }}" class="btn btn-light border items-btn">Dashboard</a>
            <button type="button" class="btn btn-primary items-btn" data-bs-toggle="modal" data-bs-target="#createItemModal">
                <i class="bi bi-plus-lg me-1"></i> Tambah Barang
            </button>
        </div>
    </div>

    <div class="items-card mb-3 filter-card">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-lg-3 col-md-4">
                <select name="category" class="form-select">
                    <option value="">Semua Kategori</option>
                    <option value="ATK" @selected(request('category') === 'ATK')>ATK</option>
                    <option value="RTK" @selected(request('category') === 'RTK')>RTK</option>
                </select>
            </div>
            <div class="col-lg-7 col-md-5">
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari kode atau nama barang">
            </div>
            <div class="col-lg-2 col-md-3">
                <button class="btn btn-primary items-btn w-100">Filter</button>
            </div>
        </form>
    </div>

    <div class="items-card">
        <div class="table-responsive items-table">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><a href="{{ $sortLink('code') }}" class="text-dark text-decoration-none">Kode <i class="bi {{ $sortIcon('code') }}"></i></a></th>
                        <th style="min-width: 320px;"><a href="{{ $sortLink('name') }}" class="text-dark text-decoration-none">Nama <i class="bi {{ $sortIcon('name') }}"></i></a></th>
                        <th><a href="{{ $sortLink('category') }}" class="text-dark text-decoration-none">Kategori <i class="bi {{ $sortIcon('category') }}"></i></a></th>
                        <th><a href="{{ $sortLink('location') }}" class="text-dark text-decoration-none">Lokasi <i class="bi {{ $sortIcon('location') }}"></i></a></th>
                        <th class="text-end"><a href="{{ $sortLink('current_stock') }}" class="text-dark text-decoration-none">Gudang Besar <i class="bi {{ $sortIcon('current_stock') }}"></i></a></th>
                        <th class="text-end">Gudang Kecil</th>
                        <th class="text-end"><a href="{{ $sortLink('minimum_stock') }}" class="text-dark text-decoration-none">Min/Buffer <i class="bi {{ $sortIcon('minimum_stock') }}"></i></a></th>
                        <th><a href="{{ $sortLink('is_active') }}" class="text-dark text-decoration-none">Status <i class="bi {{ $sortIcon('is_active') }}"></i></a></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="fw-bold">{{ $item->code }}</td>
                            <td class="wrap">
                                <div class="fw-semibold">{{ $item->name }}</div>
                                <div class="muted-text small">1 {{ $item->large_uom }} = {{ $item->conversion_qty }} {{ $item->small_uom }} | Rp{{ number_format($item->unit_price, 0, ',', '.') }}/{{ $item->small_uom }}</div>
                            </td>
                            <td><span class="badge bg-light text-dark border soft-badge">{{ $item->category }}</span></td>
                            <td>{{ $item->location ?? '-' }}</td>
                            <td class="text-end">
                                <span class="stock-chip {{ $item->current_stock <= $item->minimum_stock ? 'low' : '' }}">{{ $item->current_stock }} {{ $item->large_uom }}</span>
                            </td>
                            <td class="text-end"><span class="stock-chip">{{ $item->small_stock }} {{ $item->small_uom }}</span></td>
                            <td class="text-end">{{ $item->minimum_stock }} / {{ $item->buffer_stock }}</td>
                            <td>
                                @if($item->is_active)
                                    <span class="badge bg-success-subtle text-success soft-badge">Aktif</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary soft-badge">Nonaktif</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('bum.items.show', $item) }}" class="btn btn-sm btn-light border items-btn">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">Belum ada master barang.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="items-pagination">
            <div class="muted-text small">
                Menampilkan {{ $items->firstItem() ?? 0 }} - {{ $items->lastItem() ?? 0 }} dari {{ $items->total() }} barang
            </div>
            {{ $items->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<div class="modal fade item-modal" id="createItemModal" tabindex="-1" aria-labelledby="createItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('bum.items.store') }}">
                @csrf
                <div class="modal-header align-items-start">
                    <div>
                        <h5 class="modal-title" id="createItemModalLabel">Tambah Barang</h5>
                        <div class="text-muted small">Simpan barang, lalu form tetap siap untuk input berikutnya.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="form-section-title">Identitas Barang</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kode</label>
                                    <input name="code" class="form-control" value="{{ old('code') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kategori</label>
                                    <select name="category" class="form-select" required>
                                        <option value="ATK" @selected(old('category') === 'ATK')>ATK</option>
                                        <option value="RTK" @selected(old('category') === 'RTK')>RTK</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Nama</label>
                                    <input name="name" class="form-control" value="{{ old('name') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">UOM Besar</label>
                                    <input name="large_uom" class="form-control" value="{{ old('large_uom', 'box') }}" placeholder="box/dus/rim" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">UOM Kecil</label>
                                    <input name="small_uom" class="form-control" value="{{ old('small_uom', 'pcs') }}" placeholder="pcs/lembar/buah" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Isi per UOM Besar</label>
                                    <input name="conversion_qty" type="number" min="1" class="form-control" value="{{ old('conversion_qty', 1) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Harga per UOM Kecil</label>
                                    <input name="unit_price" type="number" min="0" class="form-control" value="{{ old('unit_price', 0) }}">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-section-title">Kontrol Stok</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Stok Awal Gudang Besar</label>
                                    <input name="current_stock" type="number" min="0" class="form-control" value="{{ old('current_stock', 0) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Stok Awal Gudang Kecil</label>
                                    <input name="small_stock" type="number" min="0" class="form-control" value="{{ old('small_stock', 0) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Minimum</label>
                                    <input name="minimum_stock" type="number" min="0" class="form-control" value="{{ old('minimum_stock', 0) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Buffer</label>
                                    <input name="buffer_stock" type="number" min="0" class="form-control" value="{{ old('buffer_stock', 0) }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Binloc</label>
                                    <input name="location" class="form-control" value="{{ old('location') }}" placeholder="Rak A-01">
                                </div>
                                <div class="col-12 form-check ms-2">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', 1))>
                                    <label class="form-check-label">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex flex-column flex-md-row gap-2">
                    <button class="btn btn-primary items-btn flex-fill">Simpan & Tambah Lagi</button>
                    <button type="button" class="btn btn-light border items-btn" data-bs-dismiss="modal">Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if($errors->any() || request('create'))
            bootstrap.Modal.getOrCreateInstance(document.getElementById('createItemModal')).show();
        @endif
    });
</script>
@endsection
