@extends('partials.layouts.master')
@section('title', 'Aset IT · '.$asset->asset_tag)
@section('pagetitle', 'Detail Aset IT')
@section('title-sub', 'Aset IT')
@section('css')@include('it.assets.style')@endsection
@section('content')
@php
    $state = \App\Support\ItAssetAccess::STATUSES[$asset->status];
    $historyLabels = ['created' => 'Aset didaftarkan', 'updated' => 'Data aset diperbarui', 'transferred' => 'Perpindahan pemegang', 'relocated' => 'Perpindahan lokasi', 'status_changed' => 'Perubahan status'];
    $fieldLabels = ['asset_tag' => 'Kode aset', 'name' => 'Nama aset', 'type_name' => 'Tipe', 'location_name' => 'Lokasi', 'assignee_name' => 'Pemegang / PIC', 'assignee_email' => 'Email pemegang', 'status' => 'Status', 'brand' => 'Merek / penerbit', 'model' => 'Model / versi', 'serial_number' => 'Nomor seri / referensi', 'acquired_on' => 'Tanggal perolehan', 'purchase_price' => 'Harga perolehan', 'expires_on' => 'Garansi / lisensi berakhir', 'specifications' => 'Spesifikasi', 'notes' => 'Catatan'];
    $displayHistory = function ($key, $value) {
        if ($key === 'specifications') return collect($value ?? [])->map(fn ($row) => $row['name'].': '.$row['value'])->implode("\n") ?: '—';
        if ($value === null || $value === '') return '—';
        if ($key === 'status') return \App\Support\ItAssetAccess::STATUSES[$value]['label'] ?? $value;
        if ($key === 'purchase_price') return 'Rp'.number_format($value, 2, ',', '.');
        return $value;
    };
@endphp
<div class="container-fluid py-4 it-assets">
    <header class="asset-hero d-flex flex-wrap gap-3 justify-content-between align-items-start mb-4"><div><div class="small mb-2">{{ $asset->asset_tag }} · {{ $asset->type->name }}</div><h1>{{ $asset->name }}</h1><p>{{ trim(($asset->brand ?? '').' '.($asset->model ?? '')) ?: 'Identitas aset dan riwayat penempatan' }}</p></div><div class="d-flex flex-wrap gap-2"><a href="{{ route('it.assets.index') }}" class="btn btn-outline-secondary">Daftar Aset</a><a href="{{ route('it.assets.edit', $asset) }}" class="btn btn-primary">Ubah Data Aset</a></div></header>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="row g-4">
        <div class="col-xl-7">
            <section class="asset-card mb-4"><h2 class="asset-section">Kondisi dan penempatan saat ini</h2><div class="row g-4">
                <div class="col-sm-6"><div class="asset-label">Status</div><span class="badge bg-{{ $state['color'] }}">{{ $state['label'] }}</span></div>
                <div class="col-sm-6"><div class="asset-label">Lokasi</div><div class="asset-meta">{{ $asset->location->name }}</div></div>
                <div class="col-sm-6"><div class="asset-label">Pemegang / PIC</div><div class="asset-meta">{{ $asset->assignee?->name ?? 'Persediaan / tanpa pemegang' }}</div><div class="asset-note">{{ $asset->assignee?->email }}</div></div>
                <div class="col-sm-6"><div class="asset-label">Nomor seri / referensi</div><div class="asset-meta">{{ $asset->serial_number ?: 'Belum dicatat' }}</div></div>
                <div class="col-sm-6"><div class="asset-label">Umur aset</div><div class="asset-meta">{{ $asset->age_label }}</div><div class="asset-note">Tanggal perolehan: {{ $asset->acquired_on?->format('d M Y') ?? 'Belum dicatat' }}</div></div>
                <div class="col-sm-6"><div class="asset-label">Harga perolehan</div><div class="asset-meta">{{ $asset->purchase_price !== null ? 'Rp'.number_format($asset->purchase_price, 2, ',', '.') : 'Belum dicatat' }}</div></div>
                <div class="col-sm-6"><div class="asset-label">Garansi / lisensi berakhir</div><div class="asset-meta">{{ $asset->expires_on?->format('d M Y') ?? 'Belum dicatat' }}</div>@if($asset->expires_on && $asset->expires_on->lt(now()->startOfDay()))<div class="asset-note text-danger">Tanggal garansi / lisensi telah lewat.</div>@endif</div>
                <div class="col-sm-6"><div class="asset-label">Terakhir diperbarui</div><div>{{ $asset->updated_at->format('d M Y H:i') }}</div></div>
                <div class="col-12"><div class="asset-label">Catatan tambahan</div><div style="white-space:pre-wrap">{{ $asset->notes ?: 'Belum ada catatan.' }}</div></div>
            </div></section>
            <section class="asset-card mb-4"><h2 class="asset-section">Spesifikasi aset</h2>
                @if($asset->specifications)<table class="table asset-table mb-0"><tbody>@foreach($asset->specifications as $spec)<tr><th scope="row" style="width:35%">{{ $spec['name'] }}</th><td style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $spec['value'] }}</td></tr>@endforeach</tbody></table>
                @else<p class="asset-note mb-0">Belum ada spesifikasi yang dicatat.</p>@endif
            </section>
            <section class="asset-card"><h2 class="asset-section">Riwayat aset <span class="badge bg-light text-dark">{{ $histories->total() }}</span></h2><p class="asset-note mb-4">Setiap pencatatan, perubahan data, status, lokasi, dan pemegang tersimpan bersama nama petugas serta alasannya.</p>
                @foreach($histories as $history)
                <article class="asset-history"><div class="d-flex flex-wrap justify-content-between gap-2"><h3 class="h6 mb-1">{{ $historyLabels[$history->action] ?? $history->action }}</h3><span class="asset-note">{{ $history->created_at->format('d M Y H:i') }}</span></div><div class="asset-note mb-2">Dicatat oleh {{ $history->actor_name }}</div><p class="small mb-3" style="white-space:pre-wrap">{{ $history->reason }}</p>
                    <div class="table-responsive"><table class="table table-sm asset-table"><thead><tr><th>Data</th>@if($history->before_state !== null)<th>Sebelum</th>@endif<th>{{ $history->before_state !== null ? 'Sesudah' : 'Nilai awal' }}</th></tr></thead><tbody>
                    @foreach($fieldLabels as $key => $label)
                        @php($beforeValue = data_get($history->before_state, $key))
                        @php($afterValue = data_get($history->after_state, $key))
                        @if($history->before_state === null ? ($afterValue !== null && $afterValue !== '') : ($beforeValue !== $afterValue))
                        <tr><td>{{ $label }}</td>@if($history->before_state !== null)<td style="white-space:pre-wrap;min-width:120px">{{ $displayHistory($key, $beforeValue) }}</td>@endif<td style="white-space:pre-wrap;min-width:120px">{{ $displayHistory($key, $afterValue) }}</td></tr>
                        @endif
                    @endforeach
                    </tbody></table></div>
                </article>
                @endforeach
                {{ $histories->links() }}
            </section>
        </div>
        <div class="col-xl-5"><section class="asset-card"><h2 class="asset-section">Mutasi / Penempatan</h2><p class="asset-note mb-4">Gunakan form ini untuk perpindahan karyawan, pemindahan lokasi, pengembalian ke persediaan, atau perubahan status.</p><form action="{{ route('it.assets.move', $asset) }}" method="POST" data-asset-save>@csrf<input type="hidden" name="lock_version" value="{{ $asset->lock_version }}"><div class="row g-3">
            @include('it.assets.placement')
            <div class="col-12"><label for="move-reason" class="form-label">Alasan / catatan mutasi <span class="text-danger">*</span></label><textarea name="reason" id="move-reason" class="form-control" rows="3" minlength="3" maxlength="2000" placeholder="Contoh: Laptop dialihkan dari Andi ke Budi untuk penempatan kerja baru." required>{{ old('reason') }}</textarea></div>
            <div class="col-12"><button class="btn btn-primary w-100">Simpan Mutasi / Status</button></div>
        </div></form></section></div>
    </div>
</div>
@endsection
@section('js')@include('it.assets.scripts')@endsection
