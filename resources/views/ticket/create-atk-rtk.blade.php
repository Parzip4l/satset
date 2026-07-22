@extends(($isPublic ?? false) ? 'partials.layouts.master_auth' : 'partials.layouts.master')

@section('title', 'Permintaan ATK / RTK')
@section('pagetitle', 'ATK / RTK')
@section('title-sub', 'Request Form')

@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .atk-page {
        --atk-ink: #2d2416;
        --atk-muted: #6f6a5c;
        --atk-line: #e7dcc7;
        --atk-brand: #e21a1a;
    }

    .atk-shell {
        background: linear-gradient(135deg, #fff8ee 0%, #ffffff 52%, #f7fbff 100%);
        border: 1px solid rgba(45, 36, 22, 0.08);
        border-radius: 28px;
        box-shadow: 0 22px 60px rgba(45, 36, 22, 0.08);
    }

    .atk-card {
        background: #fff;
        border: 1px solid var(--atk-line);
        border-radius: 24px;
        box-shadow: 0 16px 40px rgba(45, 36, 22, 0.05);
    }

    .atk-page .form-label {
        font-weight: 700;
        color: var(--atk-ink);
    }

    .atk-page .form-control,
    .atk-page .form-select {
        min-height: 50px;
        border-radius: 16px;
        border: 1px solid var(--atk-line);
        background: #fffdf9;
        padding-inline: 1rem;
    }

    .atk-page textarea.form-control {
        min-height: 120px;
    }

    .atk-page .select2-container--bootstrap-5 {
        width: 100% !important;
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection {
        align-items: center;
        background: #fffdf9;
        border-color: var(--atk-line);
        border-radius: 16px;
        display: flex;
        min-height: 50px;
        padding: 0 2.75rem 0 1rem;
    }

    .atk-page .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    .atk-page .select2-container--bootstrap-5.select2-container--open .select2-selection {
        border-color: var(--atk-brand);
        box-shadow: 0 0 0 .2rem rgba(201, 131, 31, .12);
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #212529;
        font-size: 1rem;
        line-height: 1.35;
        padding: 0;
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
        color: #9aa4b2;
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__clear {
        color: #6b7280;
        font-size: 1.15rem;
        height: 28px;
        line-height: 28px;
        margin: 0;
        position: absolute;
        right: 2.25rem;
        top: 50%;
        transform: translateY(-50%);
        width: 28px;
    }

    .atk-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        height: 50px;
        right: .9rem;
        top: 0;
        width: 20px;
    }

    .atk-page .select2-dropdown {
        border-color: var(--atk-line);
        border-radius: 14px;
        overflow: hidden;
    }

    .atk-page .select2-search--dropdown {
        padding: .75rem;
    }

    .atk-page .select2-search--dropdown .select2-search__field {
        border: 1px solid var(--atk-line);
        border-radius: 12px;
        min-height: 42px;
        padding: .5rem .8rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid atk-page">
    <div class="atk-shell p-4 p-xl-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(226,26,26,.12); color:#b91c1c;">ATK / RTK Request</span>
                <h3 class="fw-bold text-dark mb-2">Form kebutuhan ATK dan RTK.</h3>
                <p class="text-muted mb-0">Menu ini disiapkan supaya kebutuhan operasional kantor punya jalur sendiri dan tidak bercampur dengan request general.</p>
            </div>
            @unless($isPublic ?? false)
                <div class="d-flex gap-2">
                    <a href="{{ route('ticket.index') }}" class="btn btn-light border rounded-pill px-4">Menu Requests</a>
                    <a href="{{ route('ticket.general') }}" class="btn btn-outline-secondary rounded-pill px-4">Lihat General</a>
                </div>
            @endunless
        </div>

        <form action="{{ ($isPublic ?? false) ? route('public.ticket.atk-rtk.store') : route('ticket.store') }}" method="POST">
            @csrf
            <input type="hidden" name="request_type" value="atk_rtk">
            <input type="hidden" name="ticket_category_id" value="{{ $serviceRequestId ?? '' }}">
            <input type="hidden" name="priority_id" value="{{ $mediumPriorityId ?? '' }}">
            <input type="hidden" name="impact_id" value="{{ $mediumImpactId ?? '' }}">
            <input type="hidden" name="urgency_id" value="{{ $mediumUrgencyId ?? '' }}">
            <input type="hidden" name="category_id" value="{{ old('category_id', $atkRtkCategoryId ?? ($categories[0]['id'] ?? '')) }}">

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="atk-card p-4 p-xl-5">
                        @if($isPublic ?? false)
                            <div class="mb-4">
                                <h5 class="fw-bold text-dark mb-3">Data Pelapor</h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Nama Pelapor</label>
                                        <input type="text" name="reporter_name" class="form-control" value="{{ old('reporter_name') }}" placeholder="Nama lengkap" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Pelapor</label>
                                        <input type="email" name="reporter_email" class="form-control" value="{{ old('reporter_email') }}" placeholder="nama@email.com" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Kontak Pelapor</label>
                                        <input type="text" name="payload[reporter_phone]" class="form-control" value="{{ old('payload.reporter_phone') }}" placeholder="Nomor HP/extension">
                                    </div>
                                </div>
                            </div>
                            <hr class="my-4">
                        @endif

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Judul Permintaan</label>
                                <input type="text" name="payload[request_subject]" class="form-control" value="{{ old('payload.request_subject') }}" placeholder="Contoh: Pengadaan ATK untuk onboarding Mei" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jenis Barang</label>
                                <select name="payload[item_type]" class="form-select" required>
                                    <option value="">Pilih jenis barang</option>
                                    @foreach (['ATK', 'RTK', 'ATK + RTK', 'Lainnya'] as $type)
                                        <option value="{{ $type }}" {{ old('payload.item_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jumlah</label>
                                <input type="number" min="1" name="payload[quantity]" class="form-control" value="{{ old('payload.quantity') }}" placeholder="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Item Master</label>
                                <select name="payload[item_id]" class="form-select atk-select" data-placeholder="Cari kode atau nama barang">
                                    <option value="">Belum ditentukan</option>
                                    @foreach(($consumableItems ?? collect()) as $item)
                                        <option value="{{ $item->id }}" {{ old('payload.item_id') == $item->id ? 'selected' : '' }}>{{ $item->code }} - {{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Harga Estimasi/Unit</label>
                                <input type="number" min="0" name="payload[unit_price]" class="form-control" value="{{ old('payload.unit_price', 0) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Dibutuhkan</label>
                                <input type="date" name="payload[needed_date]" class="form-control" value="{{ old('payload.needed_date') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lokasi Pengiriman</label>
                                <input type="text" name="payload[delivery_location]" class="form-control" value="{{ old('payload.delivery_location') }}" placeholder="Lokasi / ruangan penerima" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PIC Penerima</label>
                                <input type="text" name="payload[recipient_pic]" class="form-control" value="{{ old('payload.recipient_pic') }}" placeholder="Nama PIC penerima barang">
                            </div>
                            @unless($isPublic ?? false)
                                <div class="col-md-6">
                                    <label class="form-label">Atasan yang Menyetujui</label>
                                    <select name="payload[supervisor_id]" class="form-select atk-select" data-placeholder="Cari nama approver">
                                        <option value="">Tidak perlu approval atasan</option>
                                        @foreach(($approvers ?? collect()) as $approver)
                                            <option value="{{ $approver->id }}" @selected(old('payload.supervisor_id') == $approver->id)>
                                                {{ $approver->name }}{{ $approver->role ? ' - ' . ucfirst($approver->role) : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="text-muted small mt-2">Wajib dipilih jika estimasi permintaan mencapai threshold approval.</div>
                                </div>
                            @endunless
                            <div class="col-12">
                                <label class="form-label">Daftar Barang / Spesifikasi</label>
                                <textarea name="payload[item_details]" class="form-control" placeholder="Tuliskan item, merek, ukuran, atau catatan barang yang dibutuhkan.">{{ old('payload.item_details') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Justifikasi Kebutuhan</label>
                                <textarea name="payload[justification]" class="form-control" placeholder="Jelaskan alasan pengadaan atau kebutuhan operasionalnya." required>{{ old('payload.justification') }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
                            <p class="text-muted small mb-0">Threshold approval atasan: <strong>Rp{{ number_format($approvalThreshold ?? 100000, 0, ',', '.') }}</strong>.</p>
                            <div class="d-flex gap-2">
                                @unless($isPublic ?? false)
                                    <a href="{{ route('ticket.index') }}" class="btn btn-light border rounded-pill px-4">Batal</a>
                                @endunless
                                <button type="submit" class="btn rounded-pill px-4 text-white" style="background:#e21a1a;">
                                    <i class="bi bi-send me-1"></i> Kirim Permintaan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="atk-card p-4">
                        <h5 class="fw-bold text-dark mb-3">Ringkasan Alur</h5>
                        <div class="small text-muted mb-3">ATK/RTK sekarang punya entry point sendiri dari menu Requests.</div>
                        <div class="border rounded-4 p-3 mb-3" style="background:#fffaf1;">
                            <div class="fw-semibold text-dark mb-1">1. Pengajuan</div>
                            <div class="text-muted small">Pemohon mengisi kebutuhan barang dan lokasi pengiriman.</div>
                        </div>
                        <div class="border rounded-4 p-3 mb-3" style="background:#fffaf1;">
                            <div class="fw-semibold text-dark mb-1">2. Verifikasi</div>
                            <div class="text-muted small">Unit terkait meninjau item dan justifikasi yang dibutuhkan.</div>
                        </div>
                        <div class="border rounded-4 p-3" style="background:#fffaf1;">
                            <div class="fw-semibold text-dark mb-1">3. Distribusi</div>
                            <div class="text-muted small">Barang diproses lalu diserahkan ke PIC penerima.</div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $('.atk-select').select2({
        theme: 'bootstrap-5',
        width: '100%',
        allowClear: true,
        placeholder: function () {
            return $(this).data('placeholder') || 'Pilih data';
        }
    });

    @if ($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Form belum lengkap',
            text: '{{ $errors->first() }}',
            confirmButtonColor: '#e21a1a'
        });
    @endif

    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: '{{ session('success') }}',
            confirmButtonColor: '#e21a1a'
        });
    @endif
</script>
@endsection
