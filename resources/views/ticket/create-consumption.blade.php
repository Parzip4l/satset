@extends(($isPublic ?? false) ? 'partials.layouts.master_auth' : 'partials.layouts.master')

@section('title', 'Permintaan Konsumsi')
@section('pagetitle', 'Permintaan Konsumsi')
@section('title-sub', 'Request Form')

@section('css')
<style>
    .consumption-page {
        --consumption-ink: #11324d;
        --consumption-muted: #64748b;
        --consumption-line: #d9e2ec;
        --consumption-soft: #f5f8fb;
        --consumption-brand: #0e7490;
        --consumption-brand-soft: #e6f7fb;
        --consumption-gold: #e21a1a;
    }

    .consumption-shell {
        background: linear-gradient(135deg, #f6fbfd 0%, #ffffff 52%, #fef7ec 100%);
        border: 1px solid rgba(17, 50, 77, 0.08);
        border-radius: 28px;
        box-shadow: 0 24px 70px rgba(17, 50, 77, 0.08);
    }

    .consumption-form-card,
    .consumption-flow-card {
        border: 1px solid var(--consumption-line);
        border-radius: 24px;
        background: #fff;
        box-shadow: 0 18px 46px rgba(17, 50, 77, 0.06);
    }

    .consumption-chip {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        background: var(--consumption-brand-soft);
        color: var(--consumption-brand);
        border-radius: 999px;
        padding: .55rem .95rem;
        font-size: .8rem;
        font-weight: 700;
    }

    .consumption-section-title {
        display: flex;
        align-items: center;
        gap: .85rem;
        font-weight: 800;
        color: var(--consumption-ink);
        margin-bottom: 1.25rem;
    }

    .consumption-section-title .step {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--consumption-brand), #39b6c8);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .consumption-page .form-label {
        color: var(--consumption-ink);
        font-weight: 700;
        font-size: .9rem;
    }

    .consumption-page .form-control,
    .consumption-page .form-select {
        min-height: 50px;
        border-radius: 16px;
        border: 1px solid var(--consumption-line);
        background: #fbfdff;
        padding-inline: 1rem;
    }

    .consumption-page textarea.form-control {
        min-height: 120px;
    }

    .consumption-page .form-control:focus,
    .consumption-page .form-select:focus {
        border-color: rgba(14, 116, 144, 0.45);
        box-shadow: 0 0 0 4px rgba(14, 116, 144, 0.12);
    }

    .flow-step {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        padding: 1rem 0;
        position: relative;
    }

    .flow-step:not(:last-child) {
        border-bottom: 1px dashed #dbe5ee;
    }

    .flow-step .bullet {
        width: 38px;
        height: 38px;
        border-radius: 14px;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: #fff;
        background: linear-gradient(135deg, var(--consumption-brand), #39b6c8);
    }

    .docs-checklist li {
        list-style: none;
        padding: .8rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fafcfe;
        margin-bottom: .7rem;
        color: #475569;
        font-weight: 600;
    }

    .sticky-flow {
        position: sticky;
        top: 110px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid consumption-page mb-6">
    <div class="consumption-shell p-4 p-xl-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <span class="consumption-chip mb-3"><i class="bi bi-cup-hot"></i> Permintaan Konsumsi</span>
                <h3 class="fw-bold text-dark mb-2">Form pengajuan konsumsi dengan alur approval yang jelas.</h3>    
            </div>
            @unless($isPublic ?? false)
                <div class="d-flex gap-2">
                    <a href="{{ route('ticket.index') }}" class="btn btn-light border rounded-pill px-4">Menu Requests</a>
                    <a href="{{ route('ticket.general') }}" class="btn btn-outline-secondary rounded-pill px-4">Lihat General</a>
                </div>
            @endunless
        </div>

        <form id="consumptionForm" action="{{ ($isPublic ?? false) ? route('public.ticket.konsumsi.store') : route('ticket.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="request_type" value="consumption">
            <input type="hidden" name="category_id" value="{{ old('category_id', $consumptionCategoryId ?? '') }}">
            <input type="hidden" name="ticket_category_id" value="{{ $serviceRequestId ?? '' }}">
            <input type="hidden" name="priority_id" value="{{ $mediumPriorityId ?? '' }}">
            <input type="hidden" name="impact_id" value="{{ $mediumImpactId ?? '' }}">
            <input type="hidden" name="urgency_id" value="{{ $mediumUrgencyId ?? '' }}">

            <div class="row g-4">
                <div class="col-xl-8">
                    @if($isPublic ?? false)
                        <div class="consumption-form-card p-4 p-xl-5 mb-4">
                            <div class="consumption-section-title">
                                <span class="step">0</span>
                                <div>
                                    <div class="fw-bold">Data Pelapor</div>
                                    <div class="text-muted fw-normal">Informasi kontak agar tim Bagian Umum dapat melakukan konfirmasi.</div>
                                </div>
                            </div>

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
                    @endif

                    <div class="consumption-form-card p-4 p-xl-5 mb-4">
                        <div class="consumption-section-title">
                            <span class="step">1</span>
                            <div>
                                <div class="fw-bold">Informasi Pengajuan</div>
                                <div class="text-muted fw-normal">Data dasar kegiatan dan approval awal.</div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Nama Kegiatan</label>
                                <input type="text" name="payload[activity_name]" class="form-control" value="{{ old('payload.activity_name') }}" placeholder="Contoh: Pelatihan Frontliner Batch 2" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jenis Kegiatan</label>
                                <select name="payload[event_type]" class="form-select" required>
                                    <option value="">Pilih jenis kegiatan</option>
                                    @foreach (['Meeting', 'Pelatihan', 'Sosialisasi', 'Workshop', 'Kunjungan', 'Lainnya'] as $type)
                                        <option value="{{ $type }}" {{ old('payload.event_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Kegiatan</label>
                                <input type="date" name="payload[event_date]" class="form-control" value="{{ old('payload.event_date') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Waktu Mulai</label>
                                <input type="time" name="payload[start_time]" class="form-control" value="{{ old('payload.start_time') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Waktu Selesai</label>
                                <input type="time" name="payload[end_time]" class="form-control" value="{{ old('payload.end_time') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lokasi</label>
                                <input type="text" name="payload[location]" class="form-control" value="{{ old('payload.location') }}" placeholder="Ruang rapat / lokasi acara" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jumlah Peserta</label>
                                <input type="number" min="1" name="payload[participant_count]" class="form-control" value="{{ old('payload.participant_count') }}" placeholder="0" required>
                            </div>
                            @unless($isPublic ?? false)
                                <div class="col-md-6">
                                    <label class="form-label">Atasan yang Menyetujui</label>
                                    <select name="payload[supervisor_id]" class="form-select" required>
                                        <option value="">Pilih atasan</option>
                                        @foreach(($approvers ?? collect()) as $approver)
                                            <option value="{{ $approver->id }}" @selected(old('payload.supervisor_id') == $approver->id)>
                                                {{ $approver->name }}{{ $approver->role ? ' - ' . ucfirst($approver->role) : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endunless
                            <div class="col-md-6">
                                <label class="form-label">Unit / Penyelenggara</label>
                                <input type="text" name="payload[organizer_unit]" class="form-control" value="{{ old('payload.organizer_unit') }}" placeholder="Unit penyelenggara kegiatan">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Alasan / Tujuan Permintaan</label>
                                <textarea name="payload[request_reason]" class="form-control" placeholder="Jelaskan kenapa konsumsi dibutuhkan untuk kegiatan ini." required>{{ old('payload.request_reason') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="consumption-form-card p-4 p-xl-5 mb-4">
                        <div class="consumption-section-title">
                            <span class="step">2</span>
                            <div>
                                <div class="fw-bold">Detail Kebutuhan Konsumsi</div>
                                <div class="text-muted fw-normal">Informasi yang akan diverifikasi Bagian Umum sebelum proses vendor.</div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Jenis Konsumsi</label>
                                <select name="payload[consumption_type]" class="form-select" required>
                                    <option value="">Pilih jenis konsumsi</option>
                                    @foreach (['Snack', 'Makan Siang', 'Coffee Break', 'Snack + Makan', 'Paket Lengkap'] as $type)
                                        <option value="{{ $type }}" {{ old('payload.consumption_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                                <div class="text-muted small mt-2">Pilihan ini diisi oleh pengaju sesuai kebutuhan kegiatan.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kontak PIC Kegiatan</label>
                                <input type="text" name="payload[pic_contact]" class="form-control" value="{{ old('payload.pic_contact') }}" placeholder="Nama / nomor PIC di lapangan">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Rincian Kebutuhan</label>
                                <textarea name="payload[consumption_notes]" class="form-control" placeholder="Contoh: 25 pax snack box, 5 pax vegetarian, air mineral, jadwal drop pukul 08.30.">{{ old('payload.consumption_notes') }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="alert border-0 mb-0" style="background:#eef7fb; color:#0e5f74;">
                                    Data vendor, preferensi pengadaan, dan catatan verifikasi akan diisi oleh Bagian Umum / tim GA setelah permintaan ini di-approve.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="consumption-form-card p-4 p-xl-5">
                        <div class="consumption-section-title">
                            <span class="step">3</span>
                            <div>
                                <div class="fw-bold">Pertanggungjawaban Setelah Kegiatan</div>
                                <div class="text-muted fw-normal">Belum wajib diisi saat pengajuan, tapi sudah kami siapkan mengikuti flow di gambar.</div>
                            </div>
                        </div>

                        <div class="alert border-0" style="background:#fff8ec; color:#8a5a10;">
                            Bagian ini menjadi pengingat bahwa setelah kegiatan selesai, karyawan perlu menyerahkan dokumen pertanggungjawaban ke Bagian Umum.
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Upload Daftar Hadir</label>
                                <input type="file" name="attendance_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <div class="text-muted small mt-2">Format: PDF, gambar, DOC, XLS. Maksimal 5 MB.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Upload Dokumentasi</label>
                                <input type="file" name="documentation_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.zip,.rar">
                                <div class="text-muted small mt-2">Format: PDF, gambar, atau arsip ZIP/RAR. Maksimal 5 MB.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Upload Laporan Kegiatan</label>
                                <input type="file" name="activity_report_file" class="form-control" accept=".pdf,.doc,.docx">
                                <div class="text-muted small mt-2">Format: PDF atau DOC. Maksimal 5 MB.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Upload Materi Pelatihan</label>
                                <input type="file" name="training_material_file" class="form-control" accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx">
                                <div class="text-muted small mt-2">Format: PDF, PPT, DOC, XLS. Maksimal 5 MB.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
                        <p class="text-muted small mb-0">Request akan disimpan sebagai <strong>Permintaan Konsumsi</strong> dengan default klasifikasi service request.</p>
                        <div class="d-flex gap-2">
                            @unless($isPublic ?? false)
                                <a href="{{ route('ticket.index') }}" class="btn btn-light border rounded-pill px-4">Batal</a>
                            @endunless
                            <button type="submit" class="btn rounded-pill px-4 text-white" style="background:#0e7490;">
                                <i class="bi bi-send me-1"></i> Kirim Permintaan
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="sticky-flow">
                        <div class="consumption-flow-card p-4 mb-4">
                            <h5 class="fw-bold mb-3 text-dark">Flow Permintaan Konsumsi</h5>
                            <div class="flow-step">
                                <span class="bullet">1</span>
                                <div>
                                    <div class="fw-semibold">Karyawan</div>
                                    <div class="text-muted small">Mengajukan form permintaan konsumsi.</div>
                                </div>
                            </div>
                            <div class="flow-step">
                                <span class="bullet">2</span>
                                <div>
                                    <div class="fw-semibold">Atasan</div>
                                    <div class="text-muted small">Memberikan approval awal sebelum diverifikasi Bagian Umum.</div>
                                </div>
                            </div>
                            <div class="flow-step">
                                <span class="bullet">3</span>
                                <div>
                                    <div class="fw-semibold">Bagian Umum</div>
                                    <div class="text-muted small">Melakukan verifikasi kebutuhan dan kelengkapan.</div>
                                </div>
                            </div>
                            <div class="flow-step">
                                <span class="bullet">4</span>
                                <div>
                                    <div class="fw-semibold">Approval Bagian Umum</div>
                                    <div class="text-muted small">Jika disetujui lanjut ke vendor, jika tidak request kembali untuk revisi.</div>
                                </div>
                            </div>
                            <div class="flow-step">
                                <span class="bullet">5</span>
                                <div>
                                    <div class="fw-semibold">Vendor</div>
                                    <div class="text-muted small">Melakukan pemesanan sampai pesanan diterima.</div>
                                </div>
                            </div>
                            <div class="flow-step">
                                <span class="bullet">6</span>
                                <div>
                                    <div class="fw-semibold">Selesai</div>
                                    <div class="text-muted small">Setelah pesanan diterima dan dokumen pertanggungjawaban diproses.</div>
                                </div>
                            </div>
                        </div>

                        <div class="consumption-flow-card p-4">
                            <h5 class="fw-bold mb-3 text-dark">Checklist Pertanggungjawaban</h5>
                            <ul class="docs-checklist p-0 mb-0">
                                <li>Daftar Hadir</li>
                                <li>Dokumentasi Kegiatan</li>
                                <li>Laporan Kegiatan</li>
                                <li>Materi Pelatihan</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    @if ($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Form belum lengkap',
            text: '{{ $errors->first() }}',
            confirmButtonColor: '#0e7490'
        });
    @endif

    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: '{{ session('success') }}',
            confirmButtonColor: '#0e7490'
        });
    @endif
</script>
@endsection
