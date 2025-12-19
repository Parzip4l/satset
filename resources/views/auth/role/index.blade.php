@extends('partials.layouts.master')

@section('title', 'Role List')
@section('title-sub', 'System Management')
@section('pagetitle', 'Role Management')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- 1. HEADER CARD --}}
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">Role Management</h4>
                            <p class="text-muted mb-0">Kelola hak akses dan peran pengguna dalam sistem.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('role.create') }}" class="btn btn-primary shadow-sm px-4">
                                <i class="bi bi-plus-lg me-1"></i> Create Role
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. FILTER & SEARCH TOOLBAR --}}
            <div class="card border-0 mb-4 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form id="filter-form" method="GET" action="{{ route('role.index') }}">
                        <div class="row g-3 align-items-center">
                            {{-- Search --}}
                            <div class="col-md-6">
                                <div class="position-relative">
                                    <input type="text" id="search-input" name="search" class="form-control ps-5" 
                                           value="{{ request('search') }}" 
                                           placeholder="Cari nama role..."> 
                                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-2 ms-auto">
                                <a href="{{ route('role.index') }}" class="btn btn-outline-danger w-100">Reset</a>
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
                                    <th class="ps-4 py-3" width="80">No</th>
                                    <th class="py-3">Role Name</th>
                                    <th class="text-end pe-4 py-3" width="100">Action</th>
                                </tr>
                            </thead>
                            <tbody id="role-table-body" class="border-top-0">
                                @forelse($role as $data)
                                    <tr>
                                        <td class="ps-4 py-3 text-muted fw-semibold">
                                            {{ ($role->currentPage() - 1) * $role->perPage() + $loop->iteration }}
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-sm bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="min-width: 40px; height: 40px;">
                                                    <i class="bi bi-shield-lock-fill fs-5"></i>
                                                </div>
                                                <span class="fw-semibold text-dark">{{ $data->name }}</span>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-icon btn-light rounded-circle border-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                                                    <li>
                                                        <a class="dropdown-item py-2" href="{{ route('role.edit', $data->id) }}">
                                                            <i class="bi bi-pencil me-2 text-primary"></i> Edit Permission
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider opacity-50"></li>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmDelete({{ $data->id }}, '{{ $data->name }}')">
                                                            <i class="bi bi-trash me-2"></i> Delete
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-shield-slash fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Data role tidak ditemukan.</h6>
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
                            Showing {{ $role->firstItem() ?? 0 }} to {{ $role->lastItem() ?? 0 }} of {{ $role->total() }} entries
                        </div>
                        <div class="pagination-container">
                            {{ $role->appends(request()->query())->links('pagination::bootstrap-5') }}
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
                title: 'Success!',
                text: '{{ session('success') }}',
                timer: 3000,
                showConfirmButton: false
            });
        @endif

        // Flash Message Error
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Failed!',
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
            title: 'Delete Role?',
            text: "Role '" + name + "' will be deleted permanently!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Delete!',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/role/' + id,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.success) {
                            Swal.fire('Deleted!', 'Role has been deleted.', 'success')
                            .then(() => location.reload());
                        } else {
                            Swal.fire('Failed!', data.message || 'An error occurred.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Server connection error.', 'error');
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
</style>
@endsection