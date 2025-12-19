@extends('partials.layouts.master')

@section('title', 'Create Role')
@section('title-sub', 'System Management')
@section('pagetitle', 'Add New Role')

@section('css')
    <style>
        :root {
            --lrt-red: #dc2626;
            --lrt-orange: #f97316;
            --primary-color: var(--lrt-red);
            --bg-body: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-radius: 12px;
        }

        body { background-color: var(--bg-body); color: var(--text-dark); }

        /* --- Card System --- */
        .card-clean {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--card-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header-clean {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* --- Form Elements --- */
        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .form-control {
            border-color: var(--border-color);
            border-radius: 8px;
            min-height: 44px;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
        }

        .form-control:focus {
            border-color: var(--lrt-red);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        /* Buttons */
        .btn-lrt-primary {
            background-color: var(--lrt-red); border-color: var(--lrt-red); color: white;
            font-weight: 600; padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.2s;
        }
        .btn-lrt-primary:hover { background-color: #b91c1c; border-color: #b91c1c; color: white; }

        .btn-back {
            background: #fff; border: 1px solid var(--border-color); color: var(--text-dark);
            padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem;
        }
        .btn-back:hover { background: #f8fafc; border-color: #cbd5e1; color: var(--lrt-red); }
    </style>
@endsection

@section('content')

<div class="container-fluid">
    
    {{-- Header Section --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Create Role</h4>
            <p class="text-muted mb-0 small">Tambahkan role baru untuk manajemen hak akses.</p>
        </div>
        <a href="{{ route('role.index') }}" class="btn-back text-decoration-none shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    {{-- Alert Messages (Fallback if SweetAlert doesn't run) --}}
    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-5 me-2"></i> {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <div class="col-12 col-lg-6"> {{-- Gunakan col-lg-6 agar tidak terlalu lebar --}}
            <form action="{{ route('role.store') }}" method="POST">
                @csrf
                
                <div class="card-clean">
                    <div class="card-header-clean">
                        <span class="fw-bold text-uppercase text-primary small"><i class="bi bi-shield-plus me-2"></i> Form Role</span>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Superadmin, Manager, Staff" required autofocus>
                            <div class="form-text text-muted small mt-2">
                                Nama role harus unik dan merepresentasikan grup pengguna.
                            </div>
                        </div>
                    </div>

                    {{-- Footer Actions --}}
                    <div class="card-footer bg-white border-top p-4">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-light border px-4 fw-bold text-muted">Reset</button>
                            <button type="submit" class="btn btn-lrt-primary px-5 shadow-sm">
                                <i class="bi bi-save me-2"></i> Simpan Role
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Flash Message Success via SweetAlert
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
    });
</script>
@endsection