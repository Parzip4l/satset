@extends('partials.layouts.master')

@section('title', 'Create Menu')
@section('title-sub', 'System Management')
@section('pagetitle', 'Add New Menu')

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
            <h4 class="fw-bold mb-1 text-dark">Create Menu</h4>
            <p class="text-muted mb-0 small">Tambahkan menu baru untuk navigasi sistem.</p>
        </div>
        <a href="{{ route('menu.index') }}" class="btn-back text-decoration-none shadow-sm">
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
            <form action="{{ route('menu.store') }}" method="POST">
                @csrf
                
                <div class="card-clean">
                    <div class="card-header-clean">
                        <span class="fw-bold text-uppercase text-primary small"><i class="bi bi-layers me-2"></i> Form Detail Menu</span>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row g-4">
                            
                            {{-- Menu Name --}}
                            <div class="col-md-6">
                                <label class="form-label">Menu Name <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="Contoh: Dashboard" required>
                            </div>

                            {{-- URL --}}
                            <div class="col-md-6">
                                <label class="form-label">URL / Route</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-color text-muted">/</span>
                                    <input type="text" name="url" class="form-control" placeholder="Contoh: dashboard/index">
                                </div>
                            </div>

                            {{-- Icon --}}
                            <div class="col-md-6">
                                <label class="form-label">Icon Class</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-color text-muted"><i class="bi bi-star"></i></span>
                                    <input type="text" name="icon" class="form-control" placeholder="Contoh: solar:home-smile">
                                </div>
                                <div class="form-text small mt-1">
                                    Lihat referensi icon di <a href="https://icon-sets.iconify.design/solar/" target="_blank" class="text-primary text-decoration-none fw-bold">Iconify Solar <i class="bi bi-box-arrow-up-right small"></i></a>
                                </div>
                            </div>

                            {{-- Menu Order --}}
                            <div class="col-md-6">
                                <label class="form-label">Order Menu <span class="text-danger">*</span></label>
                                <input type="number" name="order" class="form-control" placeholder="Urutan (Contoh: 1)" required>
                            </div>

                            {{-- Parent Menu --}}
                            <div class="col-md-6">
                                <label class="form-label">Parent Menu</label>
                                <select class="form-select select2-single" name="parent_id" data-placeholder="Pilih Parent (Jika Sub-menu)">
                                    <option value=""></option>
                                    @foreach($menuData as $parent)
                                        <option value="{{$parent->id}}">{{$parent->title}}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Is Active --}}
                            <div class="col-md-6">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select select2-single" name="is_active" data-placeholder="Pilih Status">
                                    <option value="1" selected>Active</option>
                                    <option value="0">Non Active</option>
                                </select>
                            </div>

                            {{-- Roles (Multi Select) --}}
                            <div class="col-12">
                                <div class="p-3 bg-light rounded-3 border border-dashed">
                                    <label class="form-label mb-2">Assign Roles Access</label>
                                    <select class="form-control select2-multiple" name="role_ids[]" multiple="multiple" data-placeholder="Pilih Role yang diizinkan...">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text small mt-1 text-muted">
                                        Biarkan kosong jika menu ini bersifat publik atau diatur global.
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Footer Actions --}}
                    <div class="card-footer bg-white border-top p-4">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-light border px-4 fw-bold text-muted">Reset</button>
                            <button type="submit" class="btn btn-lrt-primary px-5 shadow-sm">
                                <i class="bi bi-save me-2"></i> Simpan Menu
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
        // Init Select2 Single
        $('.select2-single').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: function() { return $(this).data('placeholder'); }
        });

        // Init Select2 Multiple (For Roles)
        $('.select2-multiple').select2({
            theme: 'bootstrap-5',
            width: '100%',
            closeOnSelect: false,
            placeholder: function() { return $(this).data('placeholder'); },
            allowClear: true
        });

        // Styling fix for Select2 focus state
        $('.select2-container').on('select2:open', function (e) {
            $(this).find('.select2-selection').css('border-color', 'var(--lrt-red)');
        });
        $('.select2-container').on('select2:close', function (e) {
            $(this).find('.select2-selection').css('border-color', '#e2e8f0');
        });
    });
</script>
@endsection