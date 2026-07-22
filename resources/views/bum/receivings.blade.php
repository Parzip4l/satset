@extends('partials.layouts.master')

@section('title', 'Pengadaan & Penerimaan')
@section('css')
    @include('bum.partials.mobile-style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .receiving-page {
            --rcv-ink: #1f2933;
            --rcv-muted: #7b8794;
            --rcv-line: #e7ecf2;
            --rcv-soft: #f8fafc;
            --rcv-primary: #e21a1a;
        }

        .receiving-page .page-title {
            color: var(--rcv-ink);
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .3rem;
        }

        .receiving-page .page-subtitle {
            color: var(--rcv-muted);
            margin-bottom: 0;
        }

        .receiving-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
        }

        .receiving-btn {
            border-radius: 8px;
            font-size: .84rem;
            font-weight: 800;
            min-height: 36px;
            padding: .45rem .85rem;
        }

        .info-strip,
        .receiving-filter,
        .metric-card,
        .receiving-card {
            background: #fff;
            border: 1px solid var(--rcv-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
            overflow: hidden;
        }

        .info-strip {
            align-items: center;
            display: grid;
            gap: 1rem;
            grid-template-columns: 1.1fr repeat(3, minmax(0, .8fr));
            padding: 1rem;
        }

        .info-step {
            align-items: center;
            display: flex;
            gap: .75rem;
        }

        .info-step-icon,
        .metric-icon {
            align-items: center;
            border-radius: 8px;
            display: flex;
            flex: 0 0 42px;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .info-step-title,
        .metric-label {
            color: var(--rcv-ink);
            font-size: .82rem;
            font-weight: 800;
        }

        .info-step-desc,
        .metric-hint,
        .muted-text {
            color: var(--rcv-muted);
        }

        .metric-card {
            align-items: center;
            display: flex;
            gap: .9rem;
            min-height: 86px;
            padding: 1rem;
        }

        .metric-value {
            color: var(--rcv-ink);
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1;
            margin-top: .2rem;
        }

        .receiving-filter {
            padding: 1rem;
        }

        .receiving-card-head {
            align-items: flex-start;
            border-bottom: 1px solid var(--rcv-line);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
        }

        .receiving-ref {
            color: var(--rcv-ink);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: .25rem;
        }

        .receiving-meta {
            color: var(--rcv-muted);
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .65rem;
            font-size: .82rem;
        }

        .receiving-summary {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .7rem;
        }

        .summary-chip {
            background: var(--rcv-soft);
            border: 1px solid var(--rcv-line);
            border-radius: 8px;
            color: #52606d;
            font-size: .78rem;
            font-weight: 800;
            padding: .38rem .6rem;
        }

        .receiving-table thead th {
            background: var(--rcv-soft);
            border-bottom: 1px solid var(--rcv-line);
            color: #394150;
            font-size: .78rem;
            font-weight: 800;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .receiving-table tbody td {
            border-color: var(--rcv-line);
            color: #364152;
            font-size: .84rem;
            padding: .8rem 1rem;
            vertical-align: middle;
        }

        .receiving-card-footer {
            background: #fff;
            border-top: 1px solid var(--rcv-line);
            padding: 1rem 1.1rem;
        }

        .soft-badge {
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 800;
            padding: .32rem .55rem;
        }

        .empty-state {
            align-items: center;
            background: #fff;
            border: 1px dashed var(--rcv-line);
            border-radius: 8px;
            color: var(--rcv-muted);
            display: flex;
            justify-content: center;
            min-height: 180px;
            text-align: center;
        }

        .receiving-modal .modal-dialog {
            max-width: min(1120px, calc(100vw - 2rem));
        }

        .receiving-modal .modal-content {
            border: 0;
            border-radius: 10px;
            box-shadow: 0 24px 70px rgba(31, 41, 51, .18);
            overflow: hidden;
        }

        .receiving-modal .modal-header {
            border-bottom: 1px solid var(--rcv-line);
            padding: 1.1rem 1.25rem;
        }

        .receiving-modal .modal-title {
            color: var(--rcv-ink);
            font-size: 1.08rem;
            font-weight: 800;
        }

        .receiving-modal .modal-body {
            background: #fff;
            padding: 1.25rem;
        }

        .receiving-modal .modal-footer {
            border-top: 1px solid var(--rcv-line);
            padding: 1rem 1.25rem;
        }

        .document-panel,
        .items-panel {
            border: 1px solid var(--rcv-line);
            border-radius: 8px;
            padding: 1rem;
            height: 100%;
        }

        .items-panel {
            background: #fff;
        }

        .items-panel-body {
            max-height: min(58vh, 620px);
            overflow-y: auto;
            padding-right: .25rem;
        }

        .form-section-title {
            color: var(--rcv-ink);
            font-size: .85rem;
            font-weight: 800;
            margin-bottom: .8rem;
            text-transform: uppercase;
        }

        .item-row {
            background: var(--rcv-soft);
            border: 1px solid var(--rcv-line);
            border-radius: 8px;
            padding: 1rem;
        }

        .item-row + .item-row {
            margin-top: .8rem;
        }

        .select2-container--bootstrap-5 .select2-selection {
            border-color: var(--rcv-line);
            border-radius: 8px;
            min-height: 42px;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 2.05;
        }

        @media (max-width: 767.98px) {
            .info-strip {
                grid-template-columns: 1fr;
            }

            .receiving-actions,
            .receiving-card-head {
                flex-direction: column;
                align-items: stretch;
            }

            .receiving-modal .modal-dialog {
                max-width: calc(100vw - 1rem);
                margin: .5rem;
            }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page receiving-page">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <h3 class="page-title">Pengadaan & Penerimaan Barang</h3>
            <p class="page-subtitle">Kelola PO, DO/SJ, GR, partial receive, reject, dan update stock card Gudang Besar.</p>
        </div>
        <div class="receiving-actions">
            <a href="{{ route('bum.stock-card') }}" class="btn btn-outline-primary receiving-btn">Stock Card</a>
            <button type="button" class="btn btn-primary receiving-btn" data-bs-toggle="modal" data-bs-target="#createReceivingModal">
                <i class="bi bi-plus-lg me-1"></i> Buat Penerimaan
            </button>
        </div>
    </div>

    <div class="info-strip mb-3">
        <div>
            <div class="fw-bold text-dark mb-1">Fungsi halaman ini</div>
            <div class="muted-text small">Mencatat dokumen pengadaan dan penerimaan barang, lalu menambah Gudang Besar berdasarkan qty yang benar-benar diterima.</div>
        </div>
        <div class="info-step">
            <div class="info-step-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-file-earmark-plus"></i></div>
            <div>
                <div class="info-step-title">1. Buat Dokumen</div>
                <div class="info-step-desc small">Input vendor, PO, DO/SJ, GR, dan item.</div>
            </div>
        </div>
        <div class="info-step">
            <div class="info-step-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-pencil-square"></i></div>
            <div>
                <div class="info-step-title">2. Update Terima</div>
                <div class="info-step-desc small">Isi qty terima/tolak sesuai fisik.</div>
            </div>
        </div>
        <div class="info-step">
            <div class="info-step-icon bg-success bg-opacity-10 text-success"><i class="bi bi-box-arrow-in-down"></i></div>
            <div>
                <div class="info-step-title">3. Gudang Besar Bertambah</div>
                <div class="info-step-desc small">Stock card otomatis mencatat selisih terima ke Gudang Besar.</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach([
            ['label' => 'Dokumen Filter', 'value' => $receivingStats['documents'], 'icon' => 'bi-receipt', 'color' => 'primary', 'hint' => 'Dokumen tampil'],
            ['label' => 'Qty Order', 'value' => $receivingStats['order_qty'], 'icon' => 'bi-cart-check', 'color' => 'info', 'hint' => 'Total dipesan'],
            ['label' => 'Qty Terima', 'value' => $receivingStats['received_qty'], 'icon' => 'bi-box-arrow-in-down', 'color' => 'success', 'hint' => 'Masuk Gudang Besar'],
            ['label' => 'Qty Tolak', 'value' => $receivingStats['rejected_qty'], 'icon' => 'bi-x-octagon', 'color' => 'danger', 'hint' => 'Tidak masuk stok'],
        ] as $metric)
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-icon bg-{{ $metric['color'] }} bg-opacity-10 text-{{ $metric['color'] }}">
                        <i class="bi {{ $metric['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="metric-label">{{ $metric['label'] }}</div>
                        <div class="metric-value">{{ number_format($metric['value']) }}</div>
                        <div class="metric-hint small">{{ $metric['hint'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="receiving-filter mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label small text-muted fw-bold">Cari</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Ref, vendor, PO, DO/SJ, GR, barang">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small text-muted fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach(['SUBMITTED', 'RECEIVED', 'PARTIALLY_RECEIVED', 'REJECTED', 'STORED', 'CLOSED'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small text-muted fw-bold">Dari</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small text-muted fw-bold">Sampai</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small text-muted fw-bold">Urutkan</label>
                <select name="sort" class="form-select">
                    <option value="created_at" @selected(request('sort', 'created_at') === 'created_at')>Dibuat</option>
                    <option value="reference_number" @selected(request('sort') === 'reference_number')>No Dokumen</option>
                    <option value="vendor_name" @selected(request('sort') === 'vendor_name')>Vendor</option>
                    <option value="status" @selected(request('sort') === 'status')>Status</option>
                    <option value="received_date" @selected(request('sort') === 'received_date')>Tanggal Terima</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-6">
                <label class="form-label small text-muted fw-bold">Arah</label>
                <select name="direction" class="form-select">
                    <option value="desc" @selected(request('direction', 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(request('direction') === 'asc')>Asc</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-column flex-md-row gap-2 justify-content-end">
                <a href="{{ route('bum.receivings') }}" class="btn btn-light border receiving-btn">Reset</a>
                <button class="btn btn-primary receiving-btn">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <div class="d-flex flex-column gap-3">
        @forelse($receivings as $receiving)
            @php
                $totalOrder = $receiving->items->sum('qty_ordered');
                $totalReceived = $receiving->items->sum('qty_received');
                $totalRejected = $receiving->items->sum('qty_rejected');
                $progressPercent = $totalOrder > 0 ? min(100, round(($totalReceived / $totalOrder) * 100)) : 0;
            @endphp
            <div class="receiving-card" id="receiving-{{ $receiving->id }}">
                <div class="receiving-card-head">
                    <div>
                        <div class="receiving-ref">{{ $receiving->reference_number }}</div>
                        <div class="receiving-meta">
                            <span>Vendor {{ $receiving->vendor_name ?? '-' }}</span>
                            <span>PO {{ $receiving->po_number ?? '-' }}</span>
                            <span>DO/SJ {{ $receiving->do_number ?? '-' }}</span>
                            <span>GR {{ $receiving->gr_number ?? '-' }}</span>
                            <span>Terima {{ optional($receiving->received_date)->format('d M Y') ?? '-' }}</span>
                        </div>
                        <div class="receiving-summary">
                            <span class="summary-chip">Order {{ number_format($totalOrder) }}</span>
                            <span class="summary-chip text-success">Terima {{ number_format($totalReceived) }}</span>
                            <span class="summary-chip text-danger">Tolak {{ number_format($totalRejected) }}</span>
                            <span class="summary-chip">Progress {{ $progressPercent }}%</span>
                        </div>
                    </div>
                    <span class="badge bg-light text-dark border soft-badge">{{ $receiving->status }}</span>
                </div>
                <form method="POST" action="{{ route('bum.receivings.receive', $receiving) }}">
                    @csrf
                    <div class="table-responsive receiving-table">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="min-width: 320px;">Barang</th>
                                    <th class="text-end">Order</th>
                                    <th class="text-end" style="min-width: 150px;">Terima</th>
                                    <th class="text-end" style="min-width: 150px;">Tolak</th>
                                    <th style="min-width: 220px;">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receiving->items as $line)
                                    <tr>
                                        <td class="wrap fw-semibold">{{ $line->item->code ?? '-' }} - {{ $line->item->name ?? '-' }}</td>
                                        <td class="text-end">{{ $line->qty_ordered }}</td>
                                        <td><input type="number" min="0" name="items[{{ $line->id }}][qty_received]" value="{{ $line->qty_received }}" class="form-control text-end"></td>
                                        <td><input type="number" min="0" name="items[{{ $line->id }}][qty_rejected]" value="{{ $line->qty_rejected }}" class="form-control text-end"></td>
                                        <td><input name="items[{{ $line->id }}][notes]" value="{{ $line->notes }}" class="form-control"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="receiving-card-footer">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <input type="date" name="received_date" value="{{ optional($receiving->received_date)->format('Y-m-d') ?? now()->format('Y-m-d') }}" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <select name="status" class="form-select">
                                    @foreach(['RECEIVED', 'PARTIALLY_RECEIVED', 'REJECTED', 'STORED', 'CLOSED'] as $status)
                                        <option value="{{ $status }}" @selected($receiving->status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <button class="btn btn-primary receiving-btn w-100">Update Penerimaan & Stok</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @empty
            <div class="empty-state">
                <div>
                    <div class="fw-bold text-dark mb-1">Belum ada dokumen penerimaan</div>
                    <div>Buat dokumen pertama lewat tombol Buat Penerimaan.</div>
                </div>
            </div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $receivings->appends(request()->query())->links('pagination::bootstrap-5') }}
    </div>
</div>

<div class="modal fade receiving-modal" tabindex="-1" id="createReceivingModal" aria-labelledby="createReceivingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('bum.receivings.store') }}" id="createReceivingForm">
            @csrf
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="createReceivingModalLabel">Buat Penerimaan</h5>
                    <div class="text-muted small">Simpan dokumen, lalu form tetap siap untuk input berikutnya.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="document-panel">
                            <div class="form-section-title">Dokumen</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Vendor</label>
                                    <input name="vendor_name" class="form-control" value="{{ old('vendor_name') }}">
                                </div>
                                <div class="col-md-4 col-lg-12">
                                    <label class="form-label">PO</label>
                                    <input name="po_number" class="form-control" value="{{ old('po_number') }}">
                                </div>
                                <div class="col-md-4 col-lg-12">
                                    <label class="form-label">DO/SJ</label>
                                    <input name="do_number" class="form-control" value="{{ old('do_number') }}">
                                </div>
                                <div class="col-md-4 col-lg-12">
                                    <label class="form-label">GR</label>
                                    <input name="gr_number" class="form-control" value="{{ old('gr_number') }}">
                                </div>
                                <div class="col-md-6 col-lg-12">
                                    <label class="form-label">Jadwal</label>
                                    <input type="date" name="scheduled_delivery_date" class="form-control" value="{{ old('scheduled_delivery_date') }}">
                                </div>
                                <div class="col-md-6 col-lg-12">
                                    <label class="form-label">Tanggal Terima</label>
                                    <input type="date" name="received_date" class="form-control" value="{{ old('received_date') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan Dokumen</label>
                                    <textarea name="notes" class="form-control" rows="4">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="items-panel">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <div class="form-section-title mb-1">Barang</div>
                                    <div class="text-muted small">Cari item dengan kode atau nama barang.</div>
                                </div>
                                <button type="button" class="btn btn-outline-primary receiving-btn" id="addReceivingItem">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Barang
                                </button>
                            </div>
                            <div class="items-panel-body" id="receivingItems">
                                <div class="item-row" data-item-row>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Barang</label>
                                            <select name="items[0][item_id]" class="form-select item-select" data-placeholder="Cari kode atau nama barang" required>
                                                <option></option>
                                                @foreach($items as $item)
                                                    <option value="{{ $item->id }}" @selected(old('items.0.item_id') == $item->id)>{{ $item->code }} - {{ $item->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Order</label>
                                            <input type="number" min="0" name="items[0][qty_ordered]" class="form-control" value="{{ old('items.0.qty_ordered') }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Terima</label>
                                            <input type="number" min="0" name="items[0][qty_received]" class="form-control" value="{{ old('items.0.qty_received', 0) }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tolak</label>
                                            <input type="number" min="0" name="items[0][qty_rejected]" class="form-control" value="{{ old('items.0.qty_rejected', 0) }}">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Catatan Item</label>
                                            <input name="items[0][notes]" class="form-control" value="{{ old('items.0.notes') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border receiving-btn" data-bs-dismiss="modal">Tutup</button>
                <button type="submit" class="btn btn-primary receiving-btn px-4">Simpan & Tambah Lagi</button>
            </div>
        </form>
        </div>
    </div>
</div>

<template id="receivingItemTemplate">
    <div class="item-row" data-item-row>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-bold text-dark small">Barang tambahan</div>
            <button type="button" class="btn btn-sm btn-light border" data-remove-item>Hapus</button>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Barang</label>
                <select data-name="items[__INDEX__][item_id]" class="form-select item-select" data-placeholder="Cari kode atau nama barang" required>
                    <option></option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}">{{ $item->code }} - {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Order</label>
                <input type="number" min="0" data-name="items[__INDEX__][qty_ordered]" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Terima</label>
                <input type="number" min="0" data-name="items[__INDEX__][qty_received]" class="form-control" value="0">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tolak</label>
                <input type="number" min="0" data-name="items[__INDEX__][qty_rejected]" class="form-control" value="0">
            </div>
            <div class="col-12">
                <label class="form-label">Catatan Item</label>
                <input data-name="items[__INDEX__][notes]" class="form-control">
            </div>
        </div>
    </div>
</template>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(function () {
        const modalElement = document.getElementById('createReceivingModal');
        const modal = new bootstrap.Modal(modalElement);
        let itemIndex = 1;

        function initItemSelect(scope) {
            $(scope).find('.item-select').each(function () {
                const select = $(this);
                if (select.hasClass('select2-hidden-accessible')) {
                    return;
                }

                select.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    allowClear: true,
                    dropdownParent: $('#createReceivingModal'),
                    placeholder: select.data('placeholder') || 'Pilih barang'
                });
            });
        }

        function applyTemplateNames(row, index) {
            row.querySelectorAll('[data-name]').forEach((field) => {
                field.name = field.dataset.name.replace('__INDEX__', index);
                field.removeAttribute('data-name');
            });
        }

        initItemSelect(document);

        $('#addReceivingItem').on('click', function () {
            const template = document.getElementById('receivingItemTemplate');
            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('[data-item-row]');
            applyTemplateNames(row, itemIndex++);
            document.getElementById('receivingItems').appendChild(fragment);
            initItemSelect(row);
        });

        $('#receivingItems').on('click', '[data-remove-item]', function () {
            const row = this.closest('[data-item-row]');
            $(row).find('.item-select').select2('destroy');
            row.remove();
        });

        @if($errors->any() || request('create'))
            modal.show();
        @endif
    });
</script>
@endsection
