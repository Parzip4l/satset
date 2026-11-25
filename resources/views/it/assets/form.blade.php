@extends('partials.layouts.master')
@section('title', $asset->exists ? 'Ubah Aset IT' : 'Tambah Aset IT')
@section('pagetitle', $asset->exists ? 'Ubah Aset IT' : 'Tambah Aset IT')
@section('title-sub', 'Aset IT')
@section('css')@include('it.assets.style')@endsection
@section('content')
<div class="container-fluid py-4 it-assets">
    <div class="asset-hero mb-4"><h1>{{ $asset->exists ? 'Ubah data aset' : 'Catat aset IT baru' }}</h1><p>Catat identitas aset, tipe, lokasi, dan pemegangnya. Tanggal perolehan dan harga boleh dilengkapi kemudian.</p></div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ $asset->exists ? route('it.assets.update', $asset) : route('it.assets.store') }}" method="POST" data-asset-save>
        @csrf
        @if($asset->exists) @method('PUT') <input type="hidden" name="lock_version" value="{{ $asset->lock_version }}"> @endif
        <div class="asset-card mb-4"><h2 class="asset-section">Identitas aset</h2><div class="row g-3">
            <div class="col-md-5"><label for="asset-tag" class="form-label">Kode aset <span class="text-danger">*</span></label><input name="asset_tag" id="asset-tag" class="form-control" maxlength="80" value="{{ old('asset_tag', $asset->asset_tag) }}" required><div class="form-text">Kode unik; dapat disesuaikan dengan penomoran perusahaan.</div></div>
            <div class="col-md-7"><label for="asset-name" class="form-label">Nama aset <span class="text-danger">*</span></label><input name="name" id="asset-name" class="form-control" maxlength="200" value="{{ old('name', $asset->name) }}" placeholder="Contoh: Laptop Lenovo ThinkPad" required></div>
            <div class="col-md-6"><label for="asset-type" class="form-label">Tipe aset <span class="text-danger">*</span></label><input name="type_name" id="asset-type" list="asset-type-options" class="form-control" maxlength="150" value="{{ old('type_name', $asset->type?->name ?? 'Hardware') }}" autocomplete="off" required><datalist id="asset-type-options">@foreach($types as $type)<option value="{{ $type->name }}"></option>@endforeach</datalist><div class="form-text">Pilih Hardware, Software, atau ketik tipe baru untuk membuatnya.</div></div>
            <div class="col-md-6"><label for="asset-serial" class="form-label">Nomor seri / referensi</label><input name="serial_number" id="asset-serial" class="form-control" maxlength="150" value="{{ old('serial_number', $asset->serial_number) }}"></div>
            <div class="col-md-6"><label for="asset-brand" class="form-label">Merek / penerbit</label><input name="brand" id="asset-brand" class="form-control" maxlength="100" value="{{ old('brand', $asset->brand) }}"></div>
            <div class="col-md-6"><label for="asset-model" class="form-label">Model / versi</label><input name="model" id="asset-model" class="form-control" maxlength="150" value="{{ old('model', $asset->model) }}"></div>
        </div></div>
        <section class="asset-card mb-4" data-specifications>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2"><h2 class="asset-section mb-0">Spesifikasi aset</h2><button type="button" class="btn btn-outline-secondary btn-sm" data-spec-add><i class="bi bi-plus-lg me-1"></i>Tambah spesifikasi</button></div>
            <p class="asset-note mb-3">Tambahkan sesuai kebutuhan, misalnya Prosesor, RAM, Penyimpanan, atau Jumlah lisensi. Bagian ini opsional.</p>
            <input type="hidden" name="specifications" value="">
            <div data-spec-rows>
                @foreach(old('specifications', $asset->specifications ?? [['name' => '', 'value' => '']]) ?? [] as $index => $spec)
                <div class="asset-spec-row" data-spec-row>
                    <div><label for="spec-name-{{ $index }}" class="form-label small">Nama spesifikasi</label><input id="spec-name-{{ $index }}" name="specifications[{{ $index }}][name]" class="form-control" maxlength="100" placeholder="Contoh: RAM" value="{{ $spec['name'] ?? '' }}"></div>
                    <div><label for="spec-value-{{ $index }}" class="form-label small">Nilai / detail</label><input id="spec-value-{{ $index }}" name="specifications[{{ $index }}][value]" class="form-control" maxlength="1000" placeholder="Contoh: 16 GB DDR5" value="{{ $spec['value'] ?? '' }}"></div>
                    <button type="button" class="btn btn-outline-secondary" data-spec-remove aria-label="Hapus spesifikasi"><i class="bi bi-trash"></i></button>
                </div>
                @endforeach
            </div>
            <p class="asset-note mb-0" data-spec-empty @if(count(old('specifications', $asset->specifications ?? [['name' => '', 'value' => '']]) ?? [])) hidden @endif>Belum ada spesifikasi. Klik Tambah spesifikasi untuk mengisi.</p>
        </section>
        @if(! $asset->exists)<div class="asset-card mb-4"><h2 class="asset-section">Penempatan awal</h2><div class="row g-3">@include('it.assets.placement')</div></div>
        @else<div class="alert alert-light border">Untuk mengubah pemegang, lokasi, atau status, gunakan Mutasi / Penempatan di detail aset agar perpindahannya tercatat.</div>@endif
        <div class="asset-card mb-4"><h2 class="asset-section">Perolehan dan informasi tambahan</h2><div class="row g-3">
            <div class="col-md-4"><label for="asset-acquired" class="form-label">Tanggal perolehan <span class="text-muted small">(opsional)</span></label><input type="date" name="acquired_on" id="asset-acquired" class="form-control" max="{{ now()->toDateString() }}" value="{{ old('acquired_on', $asset->acquired_on?->toDateString()) }}"><div class="form-text">Umur aset dihitung otomatis dari tanggal ini.</div></div>
            <div class="col-md-4"><label for="asset-price" class="form-label">Harga perolehan (Rp) <span class="text-muted small">(opsional)</span></label><input type="number" name="purchase_price" id="asset-price" class="form-control" min="0" step="0.01" max="999999999999999.99" value="{{ old('purchase_price', $asset->purchase_price) }}"><div class="form-text">Kosongkan jika harga belum diketahui.</div></div>
            <div class="col-md-4"><label for="asset-expires" class="form-label">Garansi / lisensi berakhir</label><input type="date" name="expires_on" id="asset-expires" class="form-control" value="{{ old('expires_on', $asset->expires_on?->toDateString()) }}"></div>
            <div class="col-12"><label for="asset-notes" class="form-label">Catatan tambahan</label><textarea name="notes" id="asset-notes" rows="3" maxlength="10000" class="form-control">{{ old('notes', $asset->notes) }}</textarea></div>
            @if($asset->exists)<div class="col-12"><label for="asset-reason" class="form-label">Alasan perubahan <span class="text-danger">*</span></label><textarea name="reason" id="asset-reason" class="form-control" rows="2" minlength="3" maxlength="2000" required>{{ old('reason') }}</textarea></div>@endif
        </div></div>
        <div class="d-flex gap-2 justify-content-end"><a class="btn btn-outline-secondary" href="{{ $asset->exists ? route('it.assets.show', $asset) : route('it.assets.index') }}">Kembali</a><button class="btn btn-primary">{{ $asset->exists ? 'Simpan Perubahan' : 'Simpan Aset' }}</button></div>
    </form>
</div>
@endsection
@section('js')@include('it.assets.scripts')@endsection
