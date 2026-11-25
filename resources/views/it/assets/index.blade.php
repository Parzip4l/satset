@extends('partials.layouts.master')
@section('title', 'Aset IT')
@section('pagetitle', 'Aset IT')
@section('title-sub', 'Layanan IT')
@section('css')@include('it.assets.style')@endsection
@section('content')
<div class="container-fluid py-4 it-assets">
    <header class="asset-hero d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><h1>Daftar Aset IT</h1><p>Pantau identitas, penempatan, umur, dan status aset beserta riwayat perpindahannya.</p></div><a href="{{ route('it.assets.create') }}" class="btn btn-light"><i class="bi bi-plus-lg me-1"></i>Tambah Aset</a></header>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="row g-3 mb-4">
        @foreach([['Total aset', $summary['total']], ['Digunakan', $summary['in_use']], ['Tersedia', $summary['available']], ['Perbaikan / rusak', $summary['maintenance']]] as [$label, $value])<div class="col-sm-6 col-xl-3"><div class="asset-card h-100"><div class="asset-label">{{ $label }}</div><div class="asset-value">{{ number_format($value) }}</div></div></div>@endforeach
    </div>
    <div class="asset-card mb-4"><form method="GET" action="{{ route('it.assets.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-xl-4"><label for="asset-search" class="form-label small">Cari aset / karyawan</label><input id="asset-search" name="search" class="form-control" maxlength="200" value="{{ request('search') }}" placeholder="Kode, nama, nomor seri, atau pemegang"></div>
        <div class="col-md-6 col-xl-2"><label for="filter-type" class="form-label small">Tipe</label><select id="filter-type" name="type_id" class="form-select"><option value="">Semua tipe</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected(request('type_id') == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
        <div class="col-md-6 col-xl-3"><label for="filter-location" class="form-label small">Lokasi</label><select id="filter-location" name="location_id" class="form-select"><option value="">Semua lokasi</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(request('location_id') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
        <div class="col-md-6 col-xl-3"><label for="filter-status" class="form-label small">Status</label><select id="filter-status" name="status" class="form-select"><option value="">Semua status</option>@foreach(\App\Support\ItAssetAccess::STATUSES as $value => $definition)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $definition['label'] }}</option>@endforeach</select></div>
        <div class="col-md-7"><label for="filter-employee" class="form-label small">Pemegang / PIC</label><select id="filter-employee" name="assigned_employee_portal_id" class="form-select" data-portal-employee data-portal-url="{{ route('it.assets.employees') }}"><option value="">Semua karyawan</option>@if($selectedEmployee)<option value="{{ $selectedEmployee->portal_id }}" selected>{{ $selectedEmployee->name }} · {{ $selectedEmployee->email }}</option>@endif</select>
            <div class="portal-employee-search mt-2"><div class="input-group"><input type="search" class="form-control" maxlength="150" placeholder="Cari karyawan dari Portal" aria-label="Cari karyawan dari Portal" autocomplete="off" data-portal-search><button type="button" class="btn btn-outline-secondary" data-portal-find>Cari Portal</button></div><div class="asset-note mt-2" data-portal-message role="status" aria-live="polite">Ketik nama, email, atau NIK untuk mencari di Portal.</div><div class="asset-suggestions portal-employee-results" role="listbox" hidden data-portal-results aria-label="Hasil karyawan Portal"></div><button type="button" class="btn btn-link btn-sm px-0" hidden data-portal-more>Muat hasil berikutnya</button></div></div>
        <div class="col-md-3"><label for="filter-sort" class="form-label small">Urutan</label><select id="filter-sort" name="sort" class="form-select"><option value="newest">Pencatatan terbaru</option><option value="oldest_asset" @selected(request('sort') === 'oldest_asset')>Umur aset tertua</option><option value="asset_tag" @selected(request('sort') === 'asset_tag')>Kode aset</option></select></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Cari</button><a href="{{ route('it.assets.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </form></div>
    <div class="asset-card"><div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div class="asset-note">{{ $assets->total() }} aset sesuai filter</div><div class="asset-note">Harga tercatat: <strong>Rp{{ number_format($summary['known_price_total'], 2, ',', '.') }}</strong> dari {{ $summary['known_price_count'] }} aset · Harga kosong tidak dihitung sebagai Rp0.</div></div>
        <div class="table-responsive"><table class="table asset-table"><thead><tr><th>Kode / Nama aset</th><th>Tipe</th><th>Lokasi</th><th>Pemegang / PIC</th><th>Status</th><th>Umur aset</th><th class="text-end">Harga perolehan</th><th></th></tr></thead><tbody>
        @forelse($assets as $asset)
        @php($state = \App\Support\ItAssetAccess::STATUSES[$asset->status] ?? ['label' => $asset->status, 'color' => 'secondary'])
        <tr><td><a class="fw-semibold" href="{{ route('it.assets.show', $asset) }}">{{ $asset->asset_tag }}</a><div>{{ $asset->name }}</div>@if($asset->serial_number)<div class="asset-note">SN: {{ $asset->serial_number }}</div>@endif</td><td>{{ $asset->type->name }}</td><td>{{ $asset->location->name }}</td><td>{{ $asset->assignee?->name ?? 'Persediaan / tanpa pemegang' }}</td><td><span class="badge bg-{{ $state['color'] }}">{{ $state['label'] }}</span></td><td>{{ $asset->age_label }}@if($asset->acquired_on)<div class="asset-note">{{ $asset->acquired_on->format('d M Y') }}</div>@endif</td><td class="text-end text-nowrap">{{ $asset->purchase_price !== null ? 'Rp'.number_format($asset->purchase_price, 2, ',', '.') : 'Belum dicatat' }}</td><td><a class="btn btn-outline-secondary btn-sm" href="{{ route('it.assets.show', $asset) }}">Detail</a></td></tr>
        @empty<tr><td colspan="8"><div class="asset-empty"><i class="bi bi-pc-display fs-1 text-muted"></i><h2 class="h5 mt-3">Belum ada aset IT</h2><div class="asset-note">Tambahkan aset baru atau ubah filter pencarian.</div></div></td></tr>@endforelse
        </tbody></table></div>{{ $assets->links() }}
    </div>
</div>
@endsection
@section('js')@include('it.assets.scripts')@endsection
