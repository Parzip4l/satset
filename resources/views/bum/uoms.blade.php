@extends('partials.layouts.master')

@section('title', 'Master UOM')
@section('css')
    @include('bum.partials.mobile-style')
    <style>
        .uom-page {
            --uom-line: #e7ecf2;
            --uom-muted: #7b8794;
        }

        .uom-card {
            background: #fff;
            border: 1px solid var(--uom-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
        }

        .uom-table th {
            background: #f8fafc;
            color: #394150;
            font-size: .78rem;
            font-weight: 800;
            padding: .85rem 1rem;
        }

        .uom-table td {
            border-color: var(--uom-line);
            padding: .82rem 1rem;
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page uom-page">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Master UOM</h3>
            <p class="text-muted mb-0">Kelola satuan barang agar UOM besar dan kecil tidak diinput bebas.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('bum.items') }}" class="btn btn-light border">Master Barang</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="uom-card p-4">
                <h5 class="fw-bold text-dark mb-3">Tambah UOM</h5>
                <form method="POST" action="{{ route('bum.uoms.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Kode UOM</label>
                        <input name="code" class="form-control" value="{{ old('code') }}" placeholder="pcs / box / rim" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Nama</label>
                        <input name="name" class="form-control" value="{{ old('name') }}" placeholder="Pieces / Box / Rim" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Urutan</label>
                        <input name="sort_order" type="number" min="0" class="form-control" value="{{ old('sort_order', 0) }}">
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', 1))>
                        <label class="form-check-label">Aktif</label>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary w-100">Simpan UOM</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="uom-card">
                <div class="p-3 border-bottom">
                    <form method="GET" class="row g-2">
                        <div class="col-md-9">
                            <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari kode atau nama UOM">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
                <div class="table-responsive uom-table">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th class="text-end">Urutan</th>
                                <th>Status</th>
                                <th style="min-width: 260px;">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($uoms as $uom)
                                <tr>
                                    <td class="fw-bold">{{ $uom->code }}</td>
                                    <td>{{ $uom->name }}</td>
                                    <td class="text-end">{{ $uom->sort_order }}</td>
                                    <td>
                                        @if($uom->is_active)
                                            <span class="badge bg-success-subtle text-success">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('bum.uoms.update', $uom) }}" class="d-flex gap-2 align-items-center">
                                            @csrf
                                            @method('PUT')
                                            <input name="code" value="{{ $uom->code }}" class="form-control form-control-sm" required>
                                            <input name="name" value="{{ $uom->name }}" class="form-control form-control-sm" required>
                                            <input name="sort_order" type="number" min="0" value="{{ $uom->sort_order }}" class="form-control form-control-sm" style="width:80px;">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($uom->is_active)>
                                            <button class="btn btn-sm btn-outline-primary">Simpan</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada UOM.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top">
                    {{ $uoms->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
