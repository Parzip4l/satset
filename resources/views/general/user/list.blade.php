@extends('partials.layouts.master')

@section('title', 'User List')
@section('title-sub', 'System Management')
@section('pagetitle', 'User Management')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- 1. HEADER CARD --}}
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">User Management</h4>
                            <p class="text-muted mb-0">Kelola daftar pengguna sistem dan hak akses mereka.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('user.create') }}" class="btn btn-primary shadow-sm px-4">
                                <i class="bi bi-plus-lg me-1"></i> Tambah User
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. FILTER & SEARCH TOOLBAR --}}
            <div class="card border-0 mb-4 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form id="filter-form" method="GET" action="{{ route('user.index') }}">
                        <div class="row g-3 align-items-center">
                            {{-- Search --}}
                            <div class="col-md-6">
                                <div class="position-relative">
                                    <input type="text" id="search-input" name="search" class="form-control ps-5" 
                                           value="{{ request('search') }}" 
                                           placeholder="Cari nama atau email user..."> 
                                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-2 ms-auto">
                                <a href="{{ route('user.index') }}" class="btn btn-outline-danger w-100">Reset</a>
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
                                    <th class="ps-4 py-3">Nama Lengkap</th>
                                    <th class="py-3">Username</th>
                                    <th class="py-3">Email</th>
                                    <th class="py-3">Role</th>
                                    <th class="text-end pe-4 py-3" width="100">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="user-table-body" class="border-top-0">
                                @forelse($user as $data)
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-sm bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="min-width: 40px; height: 40px;">
                                                    {{ strtoupper(substr($data->name, 0, 1)) }}
                                                </div>
                                                <span class="fw-semibold text-dark">{{ $data->name }}</span>
                                            </div>
                                        </td>
                                        <td><span class="text-muted fw-mono">{{ $data->username ?? '-' }}</span></td>
                                        <td><span class="text-muted">{{ $data->email }}</span></td>
                                        <td>
                                            <span class="badge bg-light text-dark border fw-normal">
                                                {{ $data->role ?? 'No Role' }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-icon btn-light rounded-circle border-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#ModalUserUpdate{{ $data->id }}">
                                                            <i class="bi bi-pencil me-2 text-primary"></i> Edit & Password
                                                        </button>
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
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-people fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Data user tidak ditemukan.</h6>
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
                            Menampilkan {{ $user->firstItem() ?? 0 }} sampai {{ $user->lastItem() ?? 0 }} dari {{ $user->total() }} data
                        </div>
                        <div class="pagination-container">
                            {{ $user->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

{{-- MODAL EDIT (Looping) --}}
@foreach($user as $data)
<div class="modal fade" id="ModalUserUpdate{{$data->id}}" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Update Data User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('user.update', $data->id)}}" method="POST" id="formUpdate{{$data->id}}">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Username</label>
                        <input type="text" name="username" class="form-control" value="{{$data->username}}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-uppercase fw-bold text-muted">Email</label>
                        <input type="email" name="email" class="form-control" value="{{$data->email}}" required>
                    </div>

                    <hr class="border-dashed my-4">
                    <h6 class="fw-bold mb-3 small text-muted">UBAH PASSWORD (OPSIONAL)</h6>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Password Baru</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password{{$data->id}}" class="form-control border-end-0">
                            <button class="btn btn-outline-light border border-start-0 text-muted toggle-password" type="button" data-target="password{{$data->id}}">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="text-danger small mt-1" id="passwordError{{$data->id}}"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Konfirmasi Password</label>
                        <div class="input-group">
                            <input type="password" name="password_confirmation" id="password_confirmation{{$data->id}}" class="form-control border-end-0">
                            <button class="btn btn-outline-light border border-start-0 text-muted toggle-password" type="button" data-target="password_confirmation{{$data->id}}">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Update Data</button>
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

        // Toggle Password Visibility
        $(document).on('click', '.toggle-password', function() {
            const targetId = $(this).data('target');
            const input = $('#' + targetId);
            const icon = $(this).find('i');

            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }
        });

        // Validasi Password Client-side
        $('form[id^="formUpdate"]').on('submit', function(e) {
            const form = $(this);
            const passwordInput = form.find('input[name="password"]');
            const passwordVal = passwordInput.val();
            const errorDiv = form.find('[id^="passwordError"]');
            let errors = [];

            errorDiv.html(''); // Reset error

            if (passwordVal.length > 0) {
                if (passwordVal.length < 8) errors.push("Minimal 8 karakter");
                if (!/[A-Z]/.test(passwordVal)) errors.push("Harus ada huruf besar");
                // if (!/[a-z]/.test(passwordVal)) errors.push("Harus ada huruf kecil"); // Optional
                if (!/[0-9]/.test(passwordVal)) errors.push("Harus ada angka");
                // if (!/[^A-Za-z0-9]/.test(passwordVal)) errors.push("Harus ada simbol"); // Optional

                if (errors.length > 0) {
                    e.preventDefault();
                    errorDiv.html(errors.join('<br>'));
                    passwordInput.addClass('is-invalid');
                } else {
                    passwordInput.removeClass('is-invalid');
                }
            }
        });
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus User?',
            text: "User ini akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Implementasi AJAX Delete
                // Pastikan route delete user sudah ada
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
    
    .fw-mono { font-family: monospace; }
</style>
@endsection