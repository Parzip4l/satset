@extends('partials.layouts.master')

@section('title', 'Data Department')
@section('title-sub', 'Master Data')
@section('pagetitle', 'Department List')

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
                            <h4 class="fw-bold mb-1">Data Department</h4>
                            <p class="text-muted mb-0">Kelola daftar departemen dan divisi terkait.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#ModalDepartemen">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Department
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. FILTER & SEARCH TOOLBAR --}}
            <div class="card border-0 mb-4 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form id="filter-form" method="GET" action="{{ route('department.index') }}">
                        <div class="row g-3 align-items-center">
                            {{-- Search --}}
                            <div class="col-md-6">
                                <div class="position-relative">
                                    <input type="text" id="search-input" name="search" class="form-control ps-5" 
                                           value="{{ request('search') }}" 
                                           placeholder="Cari nama departemen atau kode..."> 
                                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-2 ms-auto">
                                <a href="{{ route('department.index') }}" class="btn btn-outline-danger w-100">Reset</a>
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
                                    <th class="py-3">Nama Department</th>
                                    <th class="py-3">Divisi Induk</th>
                                    <th class="py-3">Kode</th>
                                    <th class="py-3">Email PIC</th>
                                    <th class="text-end pe-4 py-3" width="100">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="department-table-body" class="border-top-0">
                                @forelse($department as $data)
                                    <tr>
                                        <td class="ps-4 py-3 text-muted fw-semibold">
                                            {{ ($department->currentPage() - 1) * $department->perPage() + $loop->iteration }}
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-sm bg-soft-info text-info rounded d-flex align-items-center justify-content-center fw-bold" style="min-width: 40px; height: 40px;">
                                                    {{ strtoupper(substr($data->name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <span class="fw-semibold text-dark d-block">{{ $data->name }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $data->division->name ?? '-' }}
                                            </span>
                                        </td>
                                        <td><span class="text-muted fw-mono">{{ $data->code }}</span></td>
                                        <td><span class="text-muted">{{ $data->email }}</span></td>
                                        <td class="text-end pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-icon btn-light rounded-circle border-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#ModalDepartemenUpdate{{ $data->id }}">
                                                            <i class="bi bi-pencil me-2 text-primary"></i> Edit
                                                        </button>
                                                    </li>
                                                    <li><hr class="dropdown-divider opacity-50"></li>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmDelete({{ $data->id }}, '{{ $data->name }}')">
                                                            <i class="bi bi-trash me-2"></i> Hapus
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-building fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Data department tidak ditemukan.</h6>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 4. PAGINATION FOOTER --}}
                <div class="card-footer bg-white border-top py-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div class="text-muted small fw-medium">
                            Menampilkan {{ $department->firstItem() ?? 0 }} sampai {{ $department->lastItem() ?? 0 }} dari {{ $department->total() }} data
                        </div>
                        <div class="pagination-container">
                            {{ $department->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

{{-- MODAL CREATE --}}
<div class="modal fade" id="ModalDepartemen" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Department Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('department.store')}}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Nama Department</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Kode</label>
                            <input type="text" name="code" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Divisi Induk</label>
                            <select name="division_id" class="form-select select2-modal" style="width: 100%;" required>
                                <option value="">-- Pilih Divisi --</option>
                                @foreach($divisi as $div)
                                    <option value="{{ $div->id }}">{{ $div->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Email PIC</label>
                        <input type="email" name="email" class="form-control" placeholder="pic@company.com">
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
@foreach($department as $data)
<div class="modal fade" id="ModalDepartemenUpdate{{$data->id}}" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('department.update', $data->id)}}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Nama Department</label>
                        <input type="text" name="name" class="form-control" value="{{ $data->name }}" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Kode</label>
                            <input type="text" name="code" class="form-control" value="{{ $data->code }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Divisi Induk</label>
                            <select name="division_id" class="form-select select2-modal" style="width: 100%;" required>
                                <option value="">-- Pilih Divisi --</option>
                                @foreach($divisi as $div)
                                    <option value="{{ $div->id }}" {{ $data->division_id == $div->id ? 'selected' : '' }}>
                                        {{ $div->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Email PIC</label>
                        <input type="email" name="email" class="form-control" value="{{ $data->email }}">
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
        // PENTING: dropdownParent diperlukan agar Select2 bisa fokus di dalam Modal Bootstrap
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
            title: 'Hapus Department?',
            text: "Department '" + name + "' akan dihapus permanen!",
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
                    url: '/department/' + id,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.success) {
                            Swal.fire('Terhapus!', 'Data department berhasil dihapus.', 'success')
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