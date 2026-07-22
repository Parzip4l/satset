@extends(($isPublic ?? false) ? 'partials.layouts.master_auth' : 'partials.layouts.master')

@section('title', 'GA Permintaan & Temuan')

@section('css')
<style>
    .ga-form {
        --ga-ink: #111827;
        --ga-muted: #64748b;
        --ga-line: #e2e8f0;
        --ga-primary: #e21a1a;
        --ga-soft: #f5f3ff;
    }

    body { background: #f8fafc; }

    .ga-public-shell {
        min-height: 100vh;
        padding: 32px 0;
    }

    .ga-panel {
        background: #fff;
        border: 1px solid var(--ga-line);
        border-radius: 16px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .06);
    }

    .ga-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--ga-soft);
        color: var(--ga-primary);
        font-size: 1.6rem;
    }

    .section-label {
        color: var(--ga-ink);
        font-size: .85rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .form-label {
        color: #475569;
        font-size: .86rem;
        font-weight: 700;
    }

    .form-control,
    .form-select {
        border-color: var(--ga-line);
        border-radius: 10px;
        min-height: 46px;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--ga-primary);
        box-shadow: 0 0 0 4px rgba(226, 26, 26, .10);
    }

    .btn-ga {
        background: var(--ga-primary);
        border-color: var(--ga-primary);
        color: #fff;
        border-radius: 999px;
        font-weight: 700;
        padding: .72rem 1.4rem;
    }

    .btn-ga:hover {
        background: #b91c1c;
        border-color: #b91c1c;
        color: #fff;
    }

    .ga-choice {
        width: 100%;
        text-align: left;
        background: #fff;
        border: 1px solid var(--ga-line);
        border-radius: 14px;
        padding: 18px;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .ga-choice:hover,
    .ga-choice.active {
        border-color: var(--ga-primary);
        box-shadow: 0 14px 30px rgba(226, 26, 26, .12);
        transform: translateY(-1px);
    }

    .ga-choice-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fee2e2;
        color: var(--ga-primary);
        font-size: 1.35rem;
        flex: 0 0 auto;
    }
</style>
@endsection

@section('content')
<div class="{{ ($isPublic ?? false) ? 'ga-public-shell' : '' }}">
<div class="container-fluid ga-form {{ ($isPublic ?? false) ? '' : 'mt-8' }}">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            @if(session('success'))
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
                    <div class="fw-bold mb-1">Data belum lengkap</div>
                    <div class="small">Periksa kembali field yang wajib diisi.</div>
                </div>
            @endif

            <div class="ga-panel mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                        <div class="ga-icon">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-dark mb-1">GA Permintaan & Temuan</h4>
                            <p class="text-muted mb-0">Laporan QR Code Bagian Umum untuk permintaan dukungan dan temuan kerusakan area kerja.</p>
                        </div>
                    </div>
                </div>
            </div>

            @if($isPublic ?? false)
                <div class="ga-panel mb-4" id="gaChoicePanel">
                    <div class="card-body p-4 p-lg-5">
                        <div class="section-label mb-3">Pilih Jenis Laporan</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <button type="button" class="ga-choice" data-ga-report-choice="Permintaan">
                                    <span class="d-flex gap-3 align-items-start">
                                        <span class="ga-choice-icon"><i class="bi bi-clipboard-plus"></i></span>
                                        <span>
                                            <span class="d-block fw-bold text-dark mb-1">Permintaan</span>
                                            <span class="d-block text-muted small">Ajukan bantuan, dukungan fasilitas, kebutuhan area, atau layanan Bagian Umum.</span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="ga-choice" data-ga-report-choice="Temuan">
                                    <span class="d-flex gap-3 align-items-start">
                                        <span class="ga-choice-icon"><i class="bi bi-exclamation-triangle"></i></span>
                                        <span>
                                            <span class="d-block fw-bold text-dark mb-1">Temuan</span>
                                            <span class="d-block text-muted small">Laporkan kerusakan, kondisi tidak sesuai, kebersihan, atau hal yang perlu ditindaklanjuti.</span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <form action="{{ ($isPublic ?? false) ? route('public.ticket.ga-permintaan-temuan.store') : route('ticket.store') }}" method="POST" enctype="multipart/form-data" class="ga-panel {{ ($isPublic ?? false) && !old('payload.report_type') && !$errors->any() ? 'd-none' : '' }}" id="gaReportForm">
                @csrf
                <input type="hidden" name="request_type" value="ga_request_finding">
                <input type="hidden" name="ticket_category_id" value="{{ old('ticket_category_id', $serviceRequestId) }}">
                <input type="hidden" name="category_id" value="{{ old('category_id', $gaRequestFindingCategoryId) }}">
                <input type="hidden" name="priority_id" value="{{ old('priority_id', $mediumPriorityId) }}">
                <input type="hidden" name="impact_id" value="{{ old('impact_id', $mediumImpactId) }}">
                <input type="hidden" name="urgency_id" value="{{ old('urgency_id', $mediumUrgencyId) }}">

                <div class="card-body p-4 p-lg-5">
                    @if($isPublic ?? false)
                        <div class="section-label mb-3" id="gaContactSectionLabel">Data Pemohon</div>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label"><span id="gaNameLabel">Nama Pemohon</span> <span class="text-danger">*</span></label>
                                <input type="text" name="reporter_name" class="form-control" value="{{ old('reporter_name') }}" placeholder="Nama lengkap" required>
                                @error('reporter_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><span id="gaEmailLabel">Email Pemohon</span> <span class="text-danger">*</span></label>
                                <input type="email" name="reporter_email" class="form-control" value="{{ old('reporter_email') }}" placeholder="nama@email.com" required>
                                @error('reporter_email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endif

                    <div class="section-label mb-3" id="gaInfoSectionLabel">Informasi Permintaan</div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Laporan <span class="text-danger">*</span></label>
                            <select name="payload[report_type]" class="form-select" id="gaReportType" required>
                                <option value="">Pilih jenis laporan</option>
                                <option value="Permintaan" @selected(old('payload.report_type') === 'Permintaan')>Permintaan</option>
                                <option value="Temuan" @selected(old('payload.report_type') === 'Temuan')>Temuan</option>
                            </select>
                            @error('payload.report_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" id="gaPhoneLabel">Kontak Pemohon</label>
                            <input type="text" name="payload[reporter_phone]" class="form-control" value="{{ old('payload.reporter_phone') }}" placeholder="Nomor HP/extension">
                            @error('payload.reporter_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Lokasi <span class="text-danger">*</span></label>
                            <input type="text" name="payload[location]" class="form-control" value="{{ old('payload.location') }}" placeholder="Contoh: Stasiun Velodrome, Lt. 2" required>
                            @error('payload.location') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Detail Lokasi</label>
                            <input type="text" name="payload[detail_location]" class="form-control" value="{{ old('payload.detail_location') }}" placeholder="Contoh: dekat pantry / ruang rapat">
                            @error('payload.detail_location') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Uraian Permintaan / Temuan <span class="text-danger">*</span></label>
                            <textarea name="payload[description]" class="form-control" rows="5" style="height:auto; min-height:140px;" placeholder="Jelaskan kebutuhan, kerusakan, atau kondisi yang ditemukan" required>{{ old('payload.description') }}</textarea>
                            @error('payload.description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Ekspektasi Tindak Lanjut</label>
                            <input type="text" name="payload[expected_action]" class="form-control" value="{{ old('payload.expected_action') }}" placeholder="Contoh: perbaikan, pembersihan, penggantian, penyediaan item">
                            @error('payload.expected_action') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Bukti Foto / Dokumen</label>
                            <input type="file" name="evidence_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <div class="text-muted small mt-2">Format JPG, PNG, atau PDF. Maksimal 5 MB.</div>
                            @error('evidence_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="border-top mt-5 pt-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            Laporan akan masuk sebagai ticket BUM dengan status awal Open.
                        </div>
                        <div class="d-flex gap-2">
                            @unless($isPublic ?? false)
                                <a href="{{ route('ticket.index') }}" class="btn btn-light border rounded-pill px-4">Batal</a>
                            @endunless
                            <button type="submit" class="btn btn-ga">
                                <i class="bi bi-send-fill me-1"></i> Kirim Laporan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
@endsection

@if($isPublic ?? false)
@section('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('gaReportForm');
        const reportType = document.getElementById('gaReportType');
        const choices = document.querySelectorAll('[data-ga-report-choice]');
        const contactSectionLabel = document.getElementById('gaContactSectionLabel');
        const nameLabel = document.getElementById('gaNameLabel');
        const emailLabel = document.getElementById('gaEmailLabel');
        const infoSectionLabel = document.getElementById('gaInfoSectionLabel');
        const phoneLabel = document.getElementById('gaPhoneLabel');

        function updateReportCopy(value) {
            const isFinding = value === 'Temuan';
            if (contactSectionLabel) {
                contactSectionLabel.textContent = isFinding ? 'Data Pelapor' : 'Data Pemohon';
            }
            if (nameLabel) {
                nameLabel.textContent = isFinding ? 'Nama Pelapor' : 'Nama Pemohon';
            }
            if (emailLabel) {
                emailLabel.textContent = isFinding ? 'Email Pelapor' : 'Email Pemohon';
            }
            if (infoSectionLabel) {
                infoSectionLabel.textContent = isFinding ? 'Informasi Laporan' : 'Informasi Permintaan';
            }
            if (phoneLabel) {
                phoneLabel.textContent = isFinding ? 'Kontak Pelapor' : 'Kontak Pemohon';
            }
        }

        function selectReportType(value) {
            reportType.value = value;
            form.classList.remove('d-none');
            updateReportCopy(value);

            choices.forEach((choice) => {
                choice.classList.toggle('active', choice.dataset.gaReportChoice === value);
            });

            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        choices.forEach((choice) => {
            choice.addEventListener('click', function () {
                selectReportType(choice.dataset.gaReportChoice);
            });
        });

        reportType.addEventListener('change', function () {
            updateReportCopy(reportType.value);
            choices.forEach((choice) => {
                choice.classList.toggle('active', choice.dataset.gaReportChoice === reportType.value);
            });
        });

        if (reportType.value) {
            updateReportCopy(reportType.value);
            choices.forEach((choice) => {
                choice.classList.toggle('active', choice.dataset.gaReportChoice === reportType.value);
            });
        } else {
            updateReportCopy('Permintaan');
        }
    });
</script>
@endsection
@endif
