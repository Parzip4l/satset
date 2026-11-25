@extends('partials.layouts.master')
@section('title', 'Impor Aset IT')
@section('pagetitle', 'Impor Aset IT')
@section('title-sub', 'Aset IT')
@section('css')@include('it.assets.style')@endsection
@section('content')
<div class="container-fluid py-4 it-assets">
    <header class="asset-hero mb-4"><h1>Impor Aset IT dari Excel</h1><p>Isi template, lalu unggah untuk mencatat beberapa aset sekaligus beserta penempatan dan spesifikasinya.</p></header>
    @if($errors->any())
    <div class="alert alert-danger" role="alert"><h2 class="h6">Impor belum disimpan</h2><p>Perbaiki kesalahan berikut pada file Excel, lalu unggah kembali. Tidak ada aset dari unggahan ini yang disimpan.</p><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="row g-4">
        <div class="col-lg-7"><section class="asset-card h-100"><h2 class="asset-section">1. Isi template Excel</h2>
            <p class="small">Gunakan sheet <strong>Aset IT</strong>. Baris pertama adalah judul kolom, dan data dimulai pada baris 2. Lihat sheet <strong>Panduan</strong> untuk contoh pengisian.</p>
            <a href="{{ route('it.assets.template') }}" class="btn btn-outline-secondary mb-4"><i class="bi bi-download me-1"></i>Download Template XLSX</a>
            <ul class="small ps-3 mb-0" style="line-height:1.8">
                <li>Wajib: <strong>Kode aset, Nama aset, Tipe aset, Lokasi, Status</strong>. Kode aset harus unik.</li>
                <li>Isi <strong>Email pemegang</strong> dengan email kerja yang aktif di Portal. Wajib untuk status Digunakan. Kosongkan untuk Tersedia atau Tidak Aktif.</li>
                <li>Tipe dan lokasi baru akan dibuat otomatis saat impor berhasil.</li>
                <li>Tanggal dan harga boleh kosong. Gunakan tanggal Excel atau <strong>YYYY-MM-DD</strong>. Isi harga sebagai angka, tanpa tulisan Rp atau pemisah ribuan.</li>
                <li>Tambahkan kolom <strong>Spesifikasi: Nama</strong>, misalnya Spesifikasi: Prosesor atau Spesifikasi: Lisensi. Kolom spesifikasi yang kosong akan dilewati.</li>
                <li>Impor menambahkan aset baru. Perubahan aset dan perpindahan pemegang dilakukan dari detail aset.</li>
            </ul>
        </section></div>
        <div class="col-lg-5"><section class="asset-card"><h2 class="asset-section">2. Unggah data aset</h2>
            <form method="POST" action="{{ route('it.assets.import') }}" enctype="multipart/form-data" data-asset-save>
                @csrf
                <label for="asset-workbook" class="form-label">File Excel (.xlsx)</label>
                <input id="asset-workbook" type="file" name="file" class="form-control" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required aria-describedby="asset-import-help">
                <p id="asset-import-help" class="asset-note mt-2">Maksimal 5 MB, 100 aset, dan 100 kolom spesifikasi per file. Gunakan nilai biasa tanpa formula.</p>
                <div class="bg-light rounded p-3 small my-4">Semua baris diperiksa sebelum disimpan. Jika satu baris bermasalah, perbaiki file lalu unggah ulang. Setiap aset yang berhasil diimpor memiliki riwayat pencatatan awal.</div>
                <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary">Validasi &amp; Impor Aset</button><a href="{{ route('it.assets.index') }}" class="btn btn-outline-secondary">Kembali</a></div>
            </form>
        </section></div>
    </div>
</div>
@endsection
@section('js')@include('it.assets.scripts')@endsection
