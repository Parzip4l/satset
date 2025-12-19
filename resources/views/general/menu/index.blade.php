@extends('partials.layouts.master')

@section('title', 'Menu List')
@section('title-sub', 'System Management')
@section('pagetitle', 'Menu Management')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- 1. HEADER CARD --}}
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">Menu Management</h4>
                            <p class="text-muted mb-0">Kelola struktur menu dan akses navigasi sistem.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('menu.create') }}" class="btn btn-primary shadow-sm px-4">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Menu
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. FILTER & SEARCH TOOLBAR --}}
            <div class="card border-0 mb-4 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form id="filter-form" method="GET" action="{{ route('menu.index') }}">
                        <div class="row g-3 align-items-center">
                            {{-- Search --}}
                            <div class="col-md-6">
                                <div class="position-relative">
                                    <input type="text" id="search-input" name="search" class="form-control ps-5" 
                                           value="{{ request('search') }}" 
                                           placeholder="Cari nama menu..."> 
                                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-2 ms-auto">
                                <a href="{{ route('menu.index') }}" class="btn btn-outline-danger w-100">Reset</a>
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
                                    <th class="ps-4 py-3">Nama Menu</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Urutan</th>
                                    <th class="text-end pe-4 py-3" width="100">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="menu-table-body" class="border-top-0">
                                @forelse($menu as $data)
                                    @if($data->parent_id == null)
                                        <tr class="bg-light bg-opacity-25">
                                            <td class="ps-4 py-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-folder2-open text-primary"></i>
                                                    <span class="fw-bold text-dark">{{ $data->title }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" 
                                                           data-id="{{ $data->id }}" {{ $data->is_active == 1 ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">{{ $data->order }}</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-icon btn-light rounded-circle border-0" type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                                                        <li>
                                                            <a class="dropdown-item py-2" href="{{ route('menu.edit', $data->id) }}">
                                                                <i class="bi bi-pencil me-2 text-primary"></i> Edit
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider opacity-50"></li>
                                                        <li>
                                                            <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmDelete({{ $data->id }})">
                                                                <i class="bi bi-trash me-2"></i> Hapus
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        @foreach($menu->where('parent_id', $data->id) as $child)
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <div class="d-flex align-items-center" style="padding-left: 24px;">
                                                        <span class="text-muted me-2">└─</span>
                                                        <span class="text-dark">{{ $child->title }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" role="switch" 
                                                               data-id="{{ $child->id }}" {{ $child->is_active == 1 ? 'checked' : '' }}>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-muted border">{{ $child->order }}</span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-icon btn-light rounded-circle border-0" type="button" data-bs-toggle="dropdown">
                                                            <i class="bi bi-three-dots-vertical"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                                                            <li>
                                                                <a class="dropdown-item py-2" href="{{ route('menu.edit', $child->id) }}">
                                                                    <i class="bi bi-pencil me-2 text-primary"></i> Edit
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider opacity-50"></li>
                                                            <li>
                                                                <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmDelete({{ $child->id }})">
                                                                    <i class="bi bi-trash me-2"></i> Hapus
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-menu-button-wide fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Data menu tidak ditemukan.</h6>
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
                            Menampilkan {{ $menu->firstItem() ?? 0 }} sampai {{ $menu->lastItem() ?? 0 }} dari {{ $menu->total() }} data
                        </div>
                        <div class="pagination-container">
                            {{ $menu->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
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

        // Toggle Status Switch
        $('.form-check-input').on('change', function() {
            let isActive = this.checked ? 1 : 0;
            let id = $(this).data('id');

            fetch('/update-status/' + id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ is_active: isActive })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Status menu berhasil diperbarui'
                    });
                } else {
                    Swal.fire('Gagal!', 'Gagal memperbarui status.', 'error');
                    // Revert checkbox state if failed
                    this.checked = !isActive;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'Terjadi kesalahan server.', 'error');
                this.checked = !isActive;
            });
        });
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Menu?',
            text: "Menu ini akan dihapus permanen!",
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
                    url: '/menu/' + id,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.success) {
                            Swal.fire('Terhapus!', 'Menu berhasil dihapus.', 'success')
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
    
    .btn-icon {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0;
    }

    .table thead th {
        letter-spacing: 0.05em;
        border-bottom: 1px solid #f1f5f9;
    }

    .form-control {
        border-color: #e2e8f0;
        border-radius: 8px;
    }

    .form-control:focus {
        box-shadow: 0 0 0 3px rgba(59, 113, 254, 0.1);
        border-color: #3b71fe;
    }
    
    /* Toggle Switch Color */
    .form-check-input:checked {
        background-color: #3b71fe;
        border-color: #3b71fe;
    }
</style>
@endsection