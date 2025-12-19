@extends('partials.layouts.master')

@section('title', 'Buat Tiket Baru')

@section('css')
    {{-- 1. Plugins CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
        :root {
            --primary-color: #3b71fe;
            --primary-hover: #295ecc;
            --border-color: #e2e8f0;
            --bg-body: #f1f5f9;
            --input-bg: #f8fafc;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        body { background-color: var(--bg-body); }

        /* --- Card Styling --- */
        .card-modern {
            border: none;
            border-radius: var(--radius-lg);
            background: #fff;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }

        /* --- Typography & Labels --- */
        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .step-badge {
            width: 28px; height: 28px;
            background: var(--primary-color);
            color: #fff;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem;
            font-weight: 800;
            margin-right: 12px;
            box-shadow: 0 4px 6px -1px rgba(59, 113, 254, 0.3);
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        /* --- Input & Select2 Uniformity --- */
        .form-control, 
        .form-select,
        .select2-container--bootstrap-5 .select2-selection {
            height: 50px !important;
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md) !important;
            font-size: 0.95rem;
            color: #334155;
            padding-left: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus,
        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            background-color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(59, 113, 254, 0.1);
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 35px;
            padding-left: 0.5rem;
            color: #334155;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 48px;
            right: 12px;
        }

        /* --- Matrix Cards --- */
        .matrix-card {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            height: 100%;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .matrix-card:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }
        
        .matrix-card::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
        }
        .matrix-card.priority::before { background-color: #f59e0b; }
        .matrix-card.impact::before { background-color: #ef4444; }
        .matrix-card.urgency::before { background-color: #06b6d4; }

        /* --- Footer & Buttons --- */
        .footer-action {
            background: #fff;
            border-top: 1px dashed var(--border-color);
            padding-top: 2rem;
            margin-top: 2rem;
        }

        .btn-submit {
            background-color: var(--primary-color);
            border: none;
            padding: 12px 32px;
            border-radius: 50rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(59, 113, 254, 0.25);
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .card-body { padding: 1.5rem !important; }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center mb-8">
        <div class="col-xl-9 col-lg-10">
            
            {{-- Form dengan ID agar mudah diseleksi JS --}}
            <form id="ticketForm" action="{{ route('ticket.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                {{-- HEADER SECTION --}}
                <div class="card-modern mb-4">
                    <div class="card-body p-4 d-flex flex-column flex-md-row align-items-center gap-4 text-center text-md-start">
                        <div class="flex-shrink-0">
                            <div class="header-icon bg-primary-subtle text-primary rounded-4 d-flex align-items-center justify-content-center" style="width: 72px; height: 72px; font-size: 2rem;">
                                <i class="bi bi-ticket-perforated-fill"></i>
                            </div>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1 text-dark">Buat Tiket Baru</h4>
                            <p class="text-muted mb-0">Isi formulir di bawah ini dengan detail permasalahan yang Anda alami.</p>
                        </div>
                    </div>
                </div>

                {{-- MAIN FORM --}}
                <div class="card-modern">
                    <div class="card-body p-4 p-lg-5">
                        
                        {{-- 1. INFORMASI UTAMA --}}
                        <div class="mb-5">
                            <div class="section-title border-bottom pb-3">
                                <span class="step-badge">1</span> Informasi Dasar
                            </div>

                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label">Judul Tiket <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control fw-semibold" placeholder="Contoh: WiFi di Ruang Rapat Lt. 3 Mati" value="{{ old('title') }}" required>
                                    @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label">Pemohon</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: var(--radius-md) 0 0 var(--radius-md); border: 1px solid var(--border-color);"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control bg-light text-muted border-start-0 ps-2" value="{{ auth()->user()->name ?? 'Guest' }}" readonly style="border-radius: 0 var(--radius-md) var(--radius-md) 0 !important;">
                                    </div>
                                    <input type="hidden" name="requester_id" value="{{ auth()->id() }}">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label">Tipe Layanan <span class="text-danger">*</span></label>
                                    <select name="ticket_category_id" class="form-select select2-init" data-placeholder="Pilih Tipe Layanan..." required>
                                        <option></option>
                                        @foreach($categoryticket as $cat)
                                            <option value="{{ $cat->id }}" {{ old('ticket_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Kategori Masalah <span class="text-danger">*</span></label>
                                    <select name="category_id" class="form-select select2-init" data-placeholder="Pilih Kategori (Contoh: Hardware, Network, Software...)" required>
                                        <option></option>
                                        @foreach($categories as $c)
                                            <option value="{{ $c->id }}" {{ old('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- 2. KLASIFIKASI (MATRIX) --}}
                        <div class="mb-5">
                            <div class="section-title border-bottom pb-3">
                                <span class="step-badge">2</span> Klasifikasi & Prioritas
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <div class="matrix-card priority">
                                        <label class="form-label text-warning small text-uppercase mb-2">Priority</label>
                                        <select name="priority_id" class="form-select select2-init" data-placeholder="Pilih..." required>
                                            <option></option>
                                            @foreach($priorities as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="matrix-card impact">
                                        <label class="form-label text-danger small text-uppercase mb-2">Impact</label>
                                        <select name="impact_id" class="form-select select2-init" data-placeholder="Pilih..." required>
                                            <option></option>
                                            @foreach($impacts as $i)
                                                <option value="{{ $i->id }}">{{ $i->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="matrix-card urgency">
                                        <label class="form-label text-info small text-uppercase mb-2">Urgency</label>
                                        <select name="urgency_id" class="form-select select2-init" data-placeholder="Pilih..." required>
                                            <option></option>
                                            @foreach($urgencies as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 3. DETAIL MASALAH --}}
                        <div class="mb-0">
                            <div class="section-title border-bottom pb-3">
                                <span class="step-badge">3</span> Detail Permasalahan
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label">Deskripsi Lengkap <span class="text-danger">*</span></label>
                                    <textarea name="description" class="form-control" rows="6" 
                                        style="resize: none; line-height: 1.6; height: auto !important; min-height: 150px;"
                                        placeholder="Jelaskan secara rinci apa yang terjadi, pesan error yang muncul, dan langkah apa yang sudah Anda coba..." required>{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>

                        {{-- FOOTER / SUBMIT --}}
                        <div class="footer-action d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2 text-muted small">
                                <i class="bi bi-info-circle-fill text-primary"></i>
                                <span>Status awal tiket adalah <strong>Open</strong></span>
                                <input type="hidden" name="status_id" value="{{ $statuses->firstWhere('name', 'Open')->id ?? 1 }}">
                            </div>
                            
                            <div class="d-flex gap-2 w-100 w-md-auto">
                                <a href="{{ route('ticket.index') }}" class="btn btn-light border w-50 w-md-auto py-2 fw-semibold text-muted">Batal</a>
                                {{-- Tambahkan ID pada tombol submit --}}
                                <button type="submit" id="btnSubmit" class="btn btn-primary btn-submit w-50 w-md-auto text-white">
                                    <i class="bi bi-send-fill me-2"></i> Kirim Tiket
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
{{-- SweetAlert2 JS --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        
        // --- 1. Init Select2 ---
        $('.select2-init').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: function() { return $(this).data('placeholder'); }
        });

        // Efek Fokus Select2
        $('.select2-init').on('select2:open', function (e) {
            $(this).next('.select2-container').find('.select2-selection').css({
                'border-color': 'var(--primary-color)',
                'box-shadow': '0 0 0 4px rgba(59, 113, 254, 0.1)'
            });
        });
        $('.select2-init').on('select2:close', function (e) {
            $(this).next('.select2-container').find('.select2-selection').css({
                'border-color': 'var(--border-color)',
                'box-shadow': 'none'
            });
        });

        // --- 2. SweetAlert Toast Logic ---
        
        // A. Cek Session Flash Message (Jika Redirect kembali ke halaman ini)
        @if(session('success'))
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            })

            Toast.fire({
                icon: 'success',
                title: '{{ session('success') }}'
            })
        @endif

        // B. Konfirmasi Sebelum Submit (Opsional tapi Bagus)
        $('#btnSubmit').on('click', function(e) {
            e.preventDefault(); // Cegah submit langsung
            
            let form = $('#ticketForm');
            
            // Cek validasi HTML5 dulu (required fields)
            if (form[0].checkValidity() === false) {
                form[0].reportValidity();
                return;
            }

            Swal.fire({
                title: 'Kirim Tiket?',
                text: "Pastikan data yang Anda masukkan sudah benar.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b71fe',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Kirim!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Tampilkan Loading State
                    Swal.fire({
                        title: 'Sedang Mengirim...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading()
                        }
                    });
                    
                    form.submit(); // Submit form secara manual
                }
            });
        });

    });
</script>
@endsection