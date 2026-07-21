@extends('partials.layouts.master')

@section('title', 'Stock Opname')
@section('css')
    @include('bum.partials.mobile-style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .opname-page {
            --opname-ink: #1f2933;
            --opname-muted: #7b8794;
            --opname-line: #e7ecf2;
            --opname-soft: #f8fafc;
            --opname-primary: #e21a1a;
        }

        .opname-page .page-title {
            color: var(--opname-ink);
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .3rem;
        }

        .opname-page .page-subtitle,
        .opname-page .muted-text {
            color: var(--opname-muted);
        }

        .opname-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
        }

        .opname-btn {
            border-radius: 8px;
            font-size: .84rem;
            font-weight: 800;
            min-height: 36px;
            padding: .45rem .85rem;
        }

        .opname-card {
            background: #fff;
            border: 1px solid var(--opname-line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(31, 41, 51, .04);
            overflow: hidden;
        }

        .opname-section-head {
            align-items: center;
            border-bottom: 1px solid var(--opname-line);
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: 1rem 1.1rem;
        }

        .opname-section-title {
            color: var(--opname-ink);
            font-size: .95rem;
            font-weight: 800;
        }

        .opname-table thead th {
            background: var(--opname-soft);
            border-bottom: 1px solid var(--opname-line);
            color: #394150;
            font-size: .78rem;
            font-weight: 800;
            padding: .85rem 1rem;
            white-space: nowrap;
        }

        .opname-table tbody td {
            border-color: var(--opname-line);
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

        .opname-pagination {
            align-items: center;
            border-top: 1px solid var(--opname-line);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1rem;
        }

        .opname-pagination .pagination {
            margin-bottom: 0;
        }

        .opname-pagination .page-link {
            border-radius: 6px;
            color: #596575;
            font-size: .78rem;
            font-weight: 800;
            margin: 0 .1rem;
            min-width: 32px;
            text-align: center;
        }

        .opname-pagination .active > .page-link {
            background: var(--opname-primary);
            border-color: var(--opname-primary);
            color: #fff;
        }

        .opname-modal .modal-dialog {
            max-width: min(980px, calc(100vw - 2rem));
        }

        .opname-modal .modal-content {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
        }

        .opname-modal .modal-header,
        .opname-modal .modal-footer {
            border-color: var(--opname-line);
            padding: 1.1rem 1.35rem;
        }

        .opname-modal .modal-title {
            color: var(--opname-ink);
            font-size: 1.05rem;
            font-weight: 800;
        }

        .opname-modal .modal-body {
            padding: 1.35rem;
        }

        .form-section-title {
            color: var(--opname-ink);
            font-size: .82rem;
            font-weight: 900;
            letter-spacing: .02em;
            margin-bottom: .9rem;
            text-transform: uppercase;
        }

        .opname-note {
            background: var(--opname-soft);
            border: 1px solid var(--opname-line);
            border-radius: 8px;
            color: #52606d;
            font-size: .82rem;
            padding: .85rem 1rem;
        }

        .select2-container--bootstrap-5 .select2-selection {
            border-color: #e7ecf2;
            min-height: 42px;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
        }

        @media (max-width: 767.98px) {
            .opname-actions,
            .opname-pagination {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid pb-5 bum-page opname-page">
    @php
        $sortLink = function (string $key) {
            $currentSort = request('sort', 'created_at');
            $currentDirection = request('direction', 'desc');
            $nextDirection = $currentSort === $key && $currentDirection === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDirection, 'page' => null]);
        };
        $sortIcon = function (string $key) {
            if (request('sort', 'created_at') !== $key) {
                return 'bi-arrow-down-up text-muted';
            }

            return request('direction', 'desc') === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
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
            <h3 class="page-title">Stock Opname</h3>
            <p class="page-subtitle mb-0">Cek stok fisik, tutup periode, dan catat adjustment otomatis ke stock card.</p>
        </div>
        <div class="opname-actions">
            <a href="{{ route('bum.stock-card') }}" class="btn btn-light border opname-btn">Stock Card</a>
            <button type="button" class="btn btn-primary opname-btn" data-bs-toggle="modal" data-bs-target="#createOpnameModal">
                <i class="bi bi-plus-lg me-1"></i> Input Opname
            </button>
        </div>
    </div>

    <div class="opname-card">
        <div class="opname-section-head">
            <div>
                <div class="opname-section-title">Riwayat Opname</div>
                <div class="muted-text small">Daftar periode opname dan jumlah item yang sudah dihitung.</div>
            </div>
        </div>
        <div class="table-responsive opname-table">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><a href="{{ $sortLink('period') }}" class="text-dark text-decoration-none">Periode <i class="bi {{ $sortIcon('period') }}"></i></a></th>
                        <th><a href="{{ $sortLink('status') }}" class="text-dark text-decoration-none">Status <i class="bi {{ $sortIcon('status') }}"></i></a></th>
                        <th class="text-end">Item</th>
                        <th>Catatan</th>
                        <th><a href="{{ $sortLink('created_at') }}" class="text-dark text-decoration-none">Dibuat <i class="bi {{ $sortIcon('created_at') }}"></i></a></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($opnames as $opname)
                        <tr id="opname-{{ $opname->id }}">
                            <td class="fw-bold">{{ $opname->period }}</td>
                            <td><span class="badge bg-success-subtle text-success soft-badge">{{ $opname->status }}</span></td>
                            <td class="text-end">{{ $opname->items->count() }}</td>
                            <td class="wrap">{{ $opname->notes ?: '-' }}</td>
                            <td>{{ $opname->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada stock opname.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="opname-pagination">
            <div class="muted-text small">
                Menampilkan {{ $opnames->firstItem() ?? 0 }} - {{ $opnames->lastItem() ?? 0 }} dari {{ $opnames->total() }} opname
            </div>
            {{ $opnames->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<div class="modal fade opname-modal" id="createOpnameModal" tabindex="-1" aria-labelledby="createOpnameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('bum.opnames.store') }}">
                @csrf
                <div class="modal-header align-items-start">
                    <div>
                        <h5 class="modal-title" id="createOpnameModalLabel">Input Stock Opname</h5>
                        <div class="text-muted small">Simpan satu item, lalu form tetap siap untuk item berikutnya.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="form-section-title">Periode</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Periode Opname</label>
                                    <input type="month" name="period" value="{{ old('period', now()->format('Y-m')) }}" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan Opname</label>
                                    <textarea name="notes" class="form-control" rows="5" placeholder="Contoh: opname bulanan area gudang ATK">{{ old('notes') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="opname-note">
                                        Jika stok fisik berbeda dengan stok sistem, aplikasi otomatis membuat movement <strong>ADJUSTMENT</strong>.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="form-section-title">Barang</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Barang</label>
                                    <select name="items[0][item_id]" class="form-select opname-item-select" required>
                                        <option value="">Cari kode atau nama barang</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" @selected((string) old('items.0.item_id') === (string) $item->id)>
                                                {{ $item->code }} - {{ $item->name }} (sistem: {{ $item->current_stock }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Stok Fisik</label>
                                    <input type="number" min="0" name="items[0][physical_stock]" class="form-control" value="{{ old('items.0.physical_stock') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Catatan Item</label>
                                    <input name="items[0][notes]" class="form-control" value="{{ old('items.0.notes') }}" placeholder="Opsional">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex flex-column flex-md-row gap-2">
                    <button class="btn btn-primary opname-btn flex-fill">Simpan & Input Item Lagi</button>
                    <button type="button" class="btn btn-light border opname-btn" data-bs-dismiss="modal">Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('createOpnameModal');

        $('.opname-item-select').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#createOpnameModal'),
            width: '100%',
            placeholder: 'Cari kode atau nama barang'
        });

        @if($errors->any() || request('create'))
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        @endif
    });
</script>
@endsection
