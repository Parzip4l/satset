@extends('partials.layouts.master')
@section('title', 'Pengajuan Pengadaan Barang')
@section('pagetitle', 'Pengajuan Pengadaan Barang')
@section('title-sub', 'Requests')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-4">
        <div><h1 class="h3">Pengajuan Pengadaan Barang</h1><p class="text-muted mb-0">Ajukan kebutuhan barang untuk persetujuan kadep/manager dan pemenuhan oleh tim Procurement.</p></div>
        <a href="{{ route('ticket.mine') }}" class="btn btn-outline-secondary">Tiket Saya</a>
    </div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($profileError)<div class="alert alert-warning">{{ $profileError }}</div>@endif
    <form action="{{ route('procurement.store') }}" method="POST" id="procurement-form">
        @csrf
        <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="card mb-4"><div class="card-body row g-3">
            <div class="col-md-6"><label for="requester-name" class="form-label">Nama</label><input id="requester-name" class="form-control" value="{{ $profile['requester_name'] }}" readonly></div>
            <div class="col-md-6"><label for="requester-department" class="form-label">Departemen</label><input id="requester-department" class="form-control" value="{{ $profile['requester_department'] }}" readonly></div>
            <div class="col-12 small text-muted">Identitas mengikuti akun login. Persetujuan diarahkan ke kadep/manager pemohon dari Portal.</div>
        </div></div>
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center"><h2 class="h5 mb-0">Rincian kebutuhan</h2><button type="button" id="add-item" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah baris</button></div>
            <div class="card-body"><p class="text-muted small">Perkiraan anggaran adalah total anggaran per baris untuk seluruh qty, dalam Rupiah.</p>
                <div class="table-responsive"><table class="table align-middle" style="min-width: 1000px">
                    <thead><tr><th>No.</th><th>Kebutuhan detail</th><th>Detail deskripsi</th><th>Lokasi</th><th style="width:100px">Qty</th><th style="width:190px">Perkiraan anggaran (Rp)</th><th>Aksi</th></tr></thead>
                    <tbody id="procurement-items">
                    @foreach(array_values(old('items', [['need' => '', 'description' => '', 'location' => '', 'quantity' => 1, 'estimated_budget' => '']])) as $index => $item)
                        @include('procurement.row', ['index' => $index, 'item' => $item])
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="5" class="text-end fw-semibold">Total perkiraan anggaran</td><td colspan="2" id="budget-total" class="fw-bold">Rp0</td></tr></tfoot>
                </table></div>
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end"><a href="{{ route('ticket.index') }}" class="btn btn-outline-secondary">Kembali</a><button type="submit" class="btn btn-primary" @disabled($profileError)>Kirim Pengajuan</button></div>
    </form>
</div>
<template id="procurement-row-template">@include('procurement.row', ['index' => '__INDEX__', 'item' => []])</template>
@endsection
@section('js')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.getElementById('procurement-items');
    let nextIndex = rows.children.length;
    const refresh = () => {
        let cents = 0;
        [...rows.children].forEach((row, index) => {
            row.querySelector('[data-number]').textContent = index + 1;
            row.querySelector('[data-remove]').disabled = rows.children.length === 1;
            cents += Math.round((Number(row.querySelector('[data-budget]').value) || 0) * 100);
        });
        document.getElementById('budget-total').textContent = new Intl.NumberFormat('id-ID', {style: 'currency', currency: 'IDR'}).format(cents / 100);
    };
    document.getElementById('add-item').addEventListener('click', () => {
        rows.insertAdjacentHTML('beforeend', document.getElementById('procurement-row-template').innerHTML.replaceAll('__INDEX__', nextIndex++));
        refresh();
        rows.lastElementChild.querySelector('input').focus();
    });
    rows.addEventListener('click', event => { const button = event.target.closest('[data-remove]'); if (button && rows.children.length > 1) {button.closest('tr').remove(); refresh();} });
    rows.addEventListener('input', refresh);
    document.getElementById('procurement-form').addEventListener('submit', event => {event.submitter.disabled = true; event.submitter.textContent = 'Mengirim…';});
    refresh();
});
</script>
@endsection
