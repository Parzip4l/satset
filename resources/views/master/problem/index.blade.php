@extends('partials.layouts.master')

@section('title', 'Data Problem Kategori')
@section('title-sub', 'Master Data')
@section('pagetitle', 'Problem Categories')

@section('css')
    {{-- Select2 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
@endsection

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- 1. HEADER CARD --}}
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">Data Problem Kategori</h4>
                            <p class="text-muted mb-0">Kelola hierarki kategori masalah untuk tiket.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#ModalKategori">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. FILTER & SEARCH TOOLBAR --}}
            <div class="card border-0 mb-4 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form id="filter-form" method="GET" action="{{ route('problem-category.index') }}">
                        <div class="row g-3 align-items-center">
                            {{-- Search --}}
                            <div class="col-md-6">
                                <div class="position-relative">
                                    <input type="text" id="search-input" name="search" class="form-control ps-5" 
                                           value="{{ request('search') }}" 
                                           placeholder="Cari nama kategori atau kode..."> 
                                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-2 ms-auto">
                                <a href="{{ route('problem-category.index') }}" class="btn btn-outline-danger w-100">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- 3. DATA TABLE --}}
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted text-uppercase fs-11 fw-bold">
                                <tr>
                                    <th class="ps-4 py-3" width="60">No</th>
                                    <th class="py-3">Nama Kategori</th>
                                    <th class="py-3">Kode</th>
                                    <th class="text-end pe-4 py-3" width="100">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="category-table-body" class="border-top-0">
                                @php
                                    $no = 1;
                                    // Fungsi renderTree di dalam blade (sebaiknya dipindah ke Helper/Presenter, tapi ini workable)
                                    if (!function_exists('renderCategoryTree')) {
                                        function renderCategoryTree($categories, $parentId = null, $level = 0, &$no) {
                                            $filtered = $categories->where('parent_id', $parentId);
                                            
                                            foreach ($filtered as $cat) {
                                                echo '<tr>';
                                                
                                                // No
                                                echo '<td class="ps-4 py-3 text-muted fw-semibold">' . $no++ . '</td>';
                                                
                                                // Nama (Indentation logic)
                                                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                                                $icon = $level > 0 ? '<span class="text-muted me-2">└─</span>' : '';
                                                $avatarColor = $level == 0 ? 'primary' : 'secondary';
                                                
                                                echo '<td>
                                                        <div class="d-flex align-items-center">
                                                            ' . $indent . $icon . '
                                                            <span class="fw-semibold text-dark">' . e($cat->name) . '</span>
                                                        </div>
                                                      </td>';
                                                
                                                // Kode
                                                echo '<td><span class="badge bg-light text-dark border fw-mono">' . e($cat->code) . '</span></td>';
                                                
                                                // Aksi
                                                echo '<td class="text-end pe-4">
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-icon btn-light rounded-circle border-0" type="button" data-bs-toggle="dropdown">
                                                                <i class="bi bi-three-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                                                                <li>
                                                                    <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#ModalKategoriUpdate' . $cat->id . '">
                                                                        <i class="bi bi-pencil me-2 text-primary"></i> Edit
                                                                    </button>
                                                                </li>
                                                                <li><hr class="dropdown-divider opacity-50"></li>
                                                                <li>
                                                                    <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmDelete(' . $cat->id . ', \'' . e($cat->name) . '\')">
                                                                        <i class="bi bi-trash me-2"></i> Hapus
                                                                    </button>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                      </td>';
                                                
                                                echo '</tr>';
                                                
                                                // Recursive call
                                                renderCategoryTree($categories, $cat->id, $level + 1, $no);
                                            }
                                        }
                                    }
                                @endphp

                                @if($problem->count() > 0)
                                    @php renderCategoryTree($problem, null, 0, $no); @endphp
                                @else
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-diagram-3 fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Data kategori tidak ditemukan.</h6>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 4. PAGINATION FOOTER --}}
                <div class="card-footer bg-white border-top py-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div class="text-muted small fw-medium">
                            Menampilkan {{ $problem->firstItem() ?? 0 }} sampai {{ $problem->lastItem() ?? 0 }} dari {{ $problem->total() }} data
                        </div>
                        <div class="pagination-container">
                            {{ $problem->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

{{-- MODAL CREATE --}}
<div class="modal fade" id="ModalKategori" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Kategori Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('problem-category.store')}}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Nama Kategori</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Kode Kategori</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Parent Kategori</label>
                        <select name="parent_id" class="form-select select2-modal" style="width: 100%;">
                            <option value="">-- Kategori Utama (Root) --</option>
                            @foreach($problem as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- MODAL EDIT (Looping) --}}
@foreach($problem as $data)
<div class="modal fade" id="ModalKategoriUpdate{{$data->id}}" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('problem-category.update', $data->id)}}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Nama Kategori</label>
                        <input type="text" name="name" class="form-control" value="{{ $data->name }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Kode Kategori</label>
                        <input type="text" name="code" class="form-control" value="{{ $data->code }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Parent Kategori</label>
                        <select name="parent_id" class="form-select select2-modal" style="width: 100%;">
                            <option value="">-- Kategori Utama (Root) --</option>
                            @foreach($problem as $cat)
                                {{-- Prevent selecting self as parent --}}
                                @if($cat->id != $data->id)
                                    <option value="{{ $cat->id }}" {{ $data->parent_id == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3">{{ $data->description }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Init Select2 for Modals
        $('.select2-modal').each(function() {
            $(this).select2({
                theme: 'bootstrap-5',
                dropdownParent: $(this).closest('.modal') 
            });
        });

        // Flash Message Success
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '{{ session('success') }}',
                timer: 3000,
                showConfirmButton: false
            });
        @endif

        // Flash Message Error
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: '{{ session('error') }}'
            });
        @endif

        // Search Debounce (Auto Submit)
        let timer;
        $('#search-input').on('keyup', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                $('#filter-form').submit();
            }, 700);
        });
    });

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Hapus Kategori?',
            text: "Kategori '" + name + "' akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/problem-category/' + id,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.success) {
                            Swal.fire('Terhapus!', 'Kategori berhasil dihapus.', 'success')
                            .then(() => location.reload());
                        } else {
                            Swal.fire('Gagal!', data.message || 'Terjadi kesalahan.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Terjadi kesalahan server.', 'error');
                    }
                });
            }
        });
    }
</script>

<style>
    /* Styling Tambahan Konsisten */
    .bg-soft-primary { background-color: rgba(59, 113, 254, 0.1); }
    .bg-soft-info { background-color: rgba(13, 202, 240, 0.1); }
    
    .btn-icon {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0;
    }

    .table thead th {
        letter-spacing: 0.05em;
        border-bottom: 1px solid #f1f5f9;
    }

    .form-control, .form-select {
        border-color: #e2e8f0;
        border-radius: 8px;
    }

    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 0 3px rgba(59, 113, 254, 0.1);
        border-color: #3b71fe;
    }
    
    .fw-mono { font-family: monospace; }
</style>
@endsection