@extends('partials.layouts.master')

@section('title', 'Create User')
@section('title-sub', 'System Management')
@section('pagetitle', 'Add New User')

@section('css')
    {{-- Select2 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

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

        .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
            border-color: var(--border-color);
            border-radius: 8px;
            min-height: 44px;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
        }

        .form-control:focus, .form-select:focus, .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: var(--lrt-red);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        /* Fix Select2 Vertical Align */
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 30px; padding-left: 0; color: var(--text-dark);
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
            <h4 class="fw-bold mb-1 text-dark">Create User</h4>
            <p class="text-muted mb-0 small">Tambahkan pengguna baru untuk akses sistem.</p>
        </div>
        <a href="{{ route('user.index') }}" class="btn-back text-decoration-none shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    {{-- Alert Messages --}}
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
        <div class="col-12">
            <form action="{{ route('user.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <div class="card-clean">
                    <div class="card-header-clean">
                        <span class="fw-bold text-uppercase text-primary small"><i class="bi bi-person-plus-fill me-2"></i> Form Detail User</span>
                    </div>
                    
                    <div class="card-body p-4">
                        
                        {{-- Section 1: Profile Info --}}
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <h6 class="fw-bold text-muted border-bottom pb-2 mb-3 small">INFORMASI PROFIL</h6>
                            </div>

                            {{-- Photo Profile --}}
                            <div class="col-12 mb-2">
                                <label class="form-label">Photo Profile</label>
                                <input class="form-control" name="images[]" type="file" accept="image/*">
                                <div class="form-text small">Format: JPG, PNG, JPEG. Maksimal 2MB.</div>
                            </div>

                            {{-- Full Name --}}
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="Nama Lengkap" value="{{ old('name') }}" required>
                            </div>

                            {{-- Username --}}
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" placeholder="Username Unik" value="{{ old('username') }}" required>
                            </div>

                            {{-- Email --}}
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-color text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="email@lrtjakarta.co.id" value="{{ old('email') }}" required>
                                </div>
                            </div>

                            {{-- Role --}}
                            <div class="col-md-6">
                                <label class="form-label">Role Access <span class="text-danger">*</span></label>
                                <select name="role" class="form-select select2-single" data-placeholder="Pilih Role...">
                                    <option value=""></option>
                                    @foreach($role as $data)
                                        <option value="{{$data->name}}" {{ old('role') == $data->name ? 'selected' : '' }}>{{$data->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Section 2: Security --}}
                        <div class="row g-4">
                            <div class="col-12">
                                <h6 class="fw-bold text-muted border-bottom pb-2 mb-3 small mt-2">KEAMANAN AKUN</h6>
                            </div>

                            {{-- Password --}}
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control border-end-0" placeholder="Minimal 8 karakter" required>
                                    <button class="btn btn-outline-light border border-start-0 text-muted toggle-password" type="button" data-target="password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Retype Password --}}
                            <div class="col-md-6">
                                <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="retype-password" id="retype-password" class="form-control border-end-0" placeholder="Ulangi Password" required>
                                    <button class="btn btn-outline-light border border-start-0 text-muted toggle-password" type="button" data-target="retype-password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div id="password-match-msg" class="form-text mt-1 fw-bold text-danger" style="display:none;">
                                    <i class="bi bi-x-circle me-1"></i> Password tidak cocok!
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Footer Actions --}}
                    <div class="card-footer bg-white border-top p-4">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-light border px-4 fw-bold text-muted">Reset</button>
                            <button type="submit" id="btnSubmit" class="btn btn-lrt-primary px-5 shadow-sm">
                                <i class="bi bi-person-check me-2"></i> Buat User
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        // Init Select2
        $('.select2-single').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: function() { return $(this).data('placeholder'); }
        });

        // Toggle Password Visibility
        $('.toggle-password').on('click', function() {
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

        // Real-time Password Validation
        $('#retype-password, #password').on('input', function() {
            const pass = $('#password').val();
            const confirm = $('#retype-password').val();
            const msg = $('#password-match-msg');
            const submitBtn = $('#btnSubmit');

            if (confirm.length > 0) {
                if (pass !== confirm) {
                    msg.show();
                    $('#retype-password').addClass('is-invalid');
                    submitBtn.prop('disabled', true);
                } else {
                    msg.hide();
                    $('#retype-password').removeClass('is-invalid').addClass('is-valid');
                    submitBtn.prop('disabled', false);
                }
            } else {
                msg.hide();
                $('#retype-password').removeClass('is-invalid is-valid');
            }
        });

        // Focus styling trigger
        $('.select2-container').on('select2:open', function (e) {
            $(this).find('.select2-selection').css('border-color', 'var(--lrt-red)');
        });
        $('.select2-container').on('select2:close', function (e) {
            $(this).find('.select2-selection').css('border-color', '#e2e8f0');
        });
    });
</script>
@endsection