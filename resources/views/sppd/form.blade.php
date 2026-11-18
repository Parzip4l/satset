@extends('partials.layouts.master')

@section('title', ($ticket ? 'Revisi SPPD' : 'Ajukan SPPD').' | Satset')
@section('page-heading', '')

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/sppd-form.css') }}?v={{ filemtime(public_path('assets/css/sppd-form.css')) }}">
@endsection

@section('content')
<div class="sppd-form-page">
    <header class="sppd-heading">
        <a class="sppd-back" href="{{ $ticket ? route('sppd.show', $ticket) : route('sppd.index') }}">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            {{ $ticket ? 'Detail SPPD' : 'Perjalanan Dinas' }}
        </a>
        <h1>{{ $ticket ? 'Revisi SPPD' : 'Ajukan SPPD' }}</h1>
    </header>

    @include('sppd.messages')
    @if($policyUrl = \App\Support\Sppd::policyUrl())
        <p><a href="{{ $policyUrl }}" target="_blank" rel="noopener noreferrer">Buka SK Perjalanan Dinas</a></p>
    @endif
    @if($reason = data_get($sppd, 'budget_revision.reason'))
        <p class="alert alert-info">Usulan nominal admin sudah dimasukkan ke formulir. Periksa sebelum mengajukan ulang.<br><strong>Alasan</strong> {{ $reason }}</p>
    @endif

    <form class="sppd-form-layout" id="sppd-form" method="POST" enctype="multipart/form-data" action="{{ $ticket ? route('sppd.update', $ticket) : route('sppd.store') }}"
        data-preview-url="{{ $ticket ? route('sppd.preview-revision', $ticket) : route('sppd.preview') }}"
        data-profile-url="{{ route('sppd.profile') }}" data-exchange-url="{{ route('sppd.exchange-rate') }}">
        @csrf
        <input type="hidden" name="preview_token" value="">
        @if($ticket)
            @method('PUT')
            <input type="hidden" name="version" value="{{ $sppd['version'] }}">
        @else
            <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
        @endif

        <div class="sppd-fields">
            <section class="sppd-panel" aria-labelledby="sppd-employee-title">
                <header class="sppd-panel-header">
                    <span class="sppd-section-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                    <h2 id="sppd-employee-title">Pelaksana Perjalanan Dinas</h2>
                </header>
                <div class="sppd-panel-body sppd-field-grid">
                    <p class="sppd-wide sppd-help mb-0" id="sppd-profile-status" role="status" hidden></p>
                    <div>
                        <label class="form-label" for="employee-name">Nama</label>
                        <input class="form-control" id="employee-name" data-profile-field="name" value="{{ $profile['name'] }}" readonly>
                    </div>
                    @foreach(['nik' => 'NIK', 'position' => 'Jabatan', 'division' => 'Divisi', 'directorate' => 'Direktorat'] as $key => $label)
                        <div @class(['sppd-wide' => $key === 'directorate'])>
                            <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                            <input class="form-control" id="{{ $key }}" name="{{ $key }}" data-profile-field="{{ $key }}" maxlength="{{ $key === 'nik' ? 40 : 100 }}"
                                value="{{ $profile[$key] ?: old($key, data_get($sppd, "profile.{$key}")) }}" @readonly(filled($profile[$key]))>
                        </div>
                    @endforeach
                    <div>
                        <label class="form-label" for="job-level">Job Level</label>
                        <input class="form-control" id="job-level" data-profile-field="job_level"
                            value="{{ $profile['job_level'] ?? data_get($sppd, 'profile.job_level') }}" placeholder="Belum tersedia" readonly>
                    </div>
                </div>
            </section>

            <section class="sppd-panel" aria-labelledby="sppd-trip-title">
                <header class="sppd-panel-header">
                    <span class="sppd-section-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                    <h2 id="sppd-trip-title">Detail Perjalanan Dinas</h2>
                </header>
                <div class="sppd-panel-body sppd-field-grid">
                    <div class="sppd-wide">
                        <label class="form-label" for="category">Kategori Perjalanan Dinas <span class="sppd-required" aria-hidden="true">*</span></label>
                        <select class="form-select" name="category" id="category" required>
                            <option value="">Pilih kategori</option>
                            @foreach(\App\Support\Sppd::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', $sppd['category'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach(['origin' => 'Asal', 'destination' => 'Tujuan', 'depart_on' => 'Tanggal Berangkat', 'return_on' => 'Tanggal Kembali'] as $key => $label)
                        <div>
                            <label class="form-label" for="{{ $key }}">{{ $label }} <span class="sppd-required" aria-hidden="true">*</span></label>
                            <input class="form-control" id="{{ $key }}" name="{{ $key }}" type="{{ str_ends_with($key, '_on') ? 'date' : 'text' }}"
                                value="{{ old($key, $sppd[$key] ?? '') }}" maxlength="120" required>
                        </div>
                    @endforeach
                    <div>
                        <label class="form-label" for="sppd-trip-days">Total Hari Perjalanan Dinas</label>
                        <output class="form-control" id="sppd-trip-days" for="depart_on return_on">{{ isset($sppd['days']) ? $sppd['days'].' hari' : '-' }}</output>
                    </div>
                    <div class="sppd-wide" data-sppd-international hidden>
                        <label class="form-label" for="international-purpose">Jenis Perjalanan Dinas Luar Negeri</label>
                        <select class="form-select" id="international-purpose" name="international_purpose" required>
                            <option value="">Pilih jenis perjalanan</option>
                            @foreach(\App\Support\Sppd::INTERNATIONAL_PURPOSES as $key => $label)
                                <option value="{{ $key }}" @selected(old('international_purpose', $sppd['international_purpose'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sppd-wide">
                        <label class="form-label" for="purpose">Keperluan Perjalanan Dinas <span class="sppd-required" aria-hidden="true">*</span></label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3" maxlength="700" required>{{ old('purpose', $sppd['purpose'] ?? '') }}</textarea>
                    </div>
                    @foreach(array_diff_key(\App\Support\Sppd::ATTACHMENTS, ['forecast' => true]) as $key => $label)
                        @include('sppd.upload', ['key' => $key, 'label' => $label, 'accept' => '.pdf,.jpg,.jpeg,.png,.webp'])
                    @endforeach
                </div>
            </section>

            <section class="sppd-panel" aria-labelledby="sppd-budget-title">
                <header class="sppd-panel-header">
                    <span class="sppd-section-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                    <h2 id="sppd-budget-title">Rencana Anggaran</h2>
                </header>
                <div class="sppd-panel-body pb-0">
                    <button class="btn btn-sm btn-outline-primary" type="button" id="sppd-apply-benefits" hidden>Terapkan benefit grade</button>
                    <div data-sppd-international hidden>
                        <p class="sppd-help mb-1">Isi setiap pengeluaran dalam rupiah atau USD. Estimasi kurs tampil di bawah.</p>
                        <p class="sppd-help" id="sppd-exchange-status" role="status"></p>
                    </div>
                </div>
                <div class="sppd-expenses" id="sppd-costs">
                    <div class="sppd-expense-head" aria-hidden="true">
                        <span>No.</span><span>Pengeluaran</span><span>Biaya</span><span>Keterangan</span>
                    </div>
                    @foreach(\App\Support\Sppd::COSTS as $row => [$number, $label])
                        @include('sppd.cost-row')
                    @endforeach
                    <div class="sppd-expense sppd-other-heading">
                        <span class="sppd-expense-number">{{ \App\Support\Sppd::OTHER_COST_NUMBER }}</span>
                        <h3 id="sppd-other-title">Lain-lain</h3>
                        <button class="btn btn-outline-primary sppd-add-expense" id="sppd-add-cost" type="button" aria-controls="sppd-other-costs">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Baris
                        </button>
                    </div>
                    @php
                        $otherCosts = session()->hasOldInput('_token')
                            ? old('other_costs', [])
                            : array_map(function ($expense, $key) use ($sppd) {
                                $proposal = data_get($sppd, "budget_revision.other_costs.{$key}.to");
                                $usd = isset($expense['usd_cents']);
                                $amount = $proposal ?? ($usd ? $expense['usd_cents'] : $expense['cents']);
                                return [($usd ? 'usd_amount' : 'amount') => number_format($amount / 100, 2, '.', '')] + $expense;
                            }, \App\Support\Sppd::otherCosts($sppd), array_keys(\App\Support\Sppd::otherCosts($sppd)));
                    @endphp
                    <div id="sppd-other-costs" data-group-number="{{ \App\Support\Sppd::OTHER_COST_NUMBER }}" aria-labelledby="sppd-other-title" role="group">
                        @foreach(array_values(is_array($otherCosts) ? $otherCosts : []) as $index => $expense)
                            @include('sppd.other-cost-row', ['index' => $index, 'expense' => is_array($expense) ? array_filter($expense, 'is_scalar') : []])
                        @endforeach
                    </div>
                    <template id="sppd-other-cost-template">
                        @include('sppd.other-cost-row', ['index' => 0, 'expense' => []])
                    </template>
                    @include('sppd.cost-row', ['row' => \App\Support\Sppd::WINTER_COST, 'number' => '7', 'label' => 'Pakaian Musim Dingin'])
                    <div class="sppd-field-grid mt-3" data-sppd-international hidden>
                        <div>
                            <label class="form-label" for="forecast-temperature">Prakiraan Suhu Terendah (°C)</label>
                            <input class="form-control" id="forecast-temperature" name="forecast_temperature" type="number" min="-100" max="100" step="any"
                                value="{{ old('forecast_temperature', $sppd['forecast_temperature'] ?? '') }}">
                            <p class="sppd-help mt-1">Untuk suhu di bawah 15°C, lampirkan prakiraan pada tanggal perjalanan.</p>
                        </div>
                        @include('sppd.upload', ['key' => 'forecast', 'label' => \App\Support\Sppd::ATTACHMENTS['forecast'], 'accept' => '.jpg,.jpeg,.png,.webp'])
                    </div>
                </div>
            </section>

            <section class="sppd-panel" aria-labelledby="sppd-notes-title">
                <header class="sppd-panel-header">
                    <span class="sppd-section-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
                    <h2 id="sppd-notes-title">Catatan Tambahan</h2>
                </header>
                <div class="sppd-panel-body">
                    <p class="sppd-help" id="sppd-bank-help">Isi ketiga kolom rekening jika pembayaran melalui transfer.</p>
                    <div class="sppd-field-grid sppd-bank-grid">
                        @foreach(['name' => ['Bank', 50], 'account' => ['Nomor Rekening', 30], 'holder' => ['Nama Pemilik Rekening', 100]] as $key => [$label, $length])
                            <div>
                                <label class="form-label" for="bank-{{ $key }}">{{ $label }}</label>
                                <input class="form-control" id="bank-{{ $key }}" name="bank[{{ $key }}]" maxlength="{{ $length }}"
                                    aria-describedby="sppd-bank-help" @if($key === 'account') inputmode="numeric" pattern="[0-9]{5,30}" @endif
                                    value="{{ old("bank.{$key}", data_get($sppd, "bank.{$key}")) }}">
                            </div>
                        @endforeach
                        <div class="sppd-wide">
                            <label class="form-label" for="notes">Catatan</label>
                            <textarea class="form-control" id="notes" name="notes" maxlength="500" rows="3">{{ old('notes', $sppd['notes'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="sppd-panel sppd-summary" aria-labelledby="sppd-summary-title">
            <header class="sppd-panel-header">
                <h2 id="sppd-summary-title">Ringkasan Pengajuan</h2>
            </header>
            <div class="sppd-panel-body">
                <div class="sppd-duration">
                    <span class="sppd-duration-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                    <div>
                        <label for="sppd-days">Durasi perjalanan</label>
                        <output id="sppd-days" for="depart_on return_on">—</output>
                    </div>
                </div>
                <div class="sppd-summary-costs">
                    <div>
                        <label for="cost-total">Total anggaran</label>
                        <output class="sppd-total" id="cost-total">Rp0</output>
                    </div>
                    <div>
                        <label for="cost-paid">Dibayarkan ke karyawan</label>
                        <output id="cost-paid">Rp0</output>
                    </div>
                </div>
                <button class="btn btn-primary sppd-submit" type="submit">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Pratinjau PDF
                </button>
            </div>
        </aside>
    </form>

    <dialog class="sppd-preview" id="sppd-preview" aria-labelledby="sppd-preview-title">
        <header class="sppd-preview-header">
            <h2 id="sppd-preview-title">Pratinjau PDF SPPD</h2>
            <a class="btn btn-outline-primary" data-open-pdf target="_blank" rel="noopener" hidden>
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Buka PDF
            </a>
        </header>
        <div class="sppd-preview-body">
            <p data-preview-status role="status"></p>
            <iframe data-preview-frame title="Draf PDF SPPD" hidden></iframe>
        </div>
        <footer class="sppd-preview-actions">
            <button class="btn btn-light border" data-close-preview type="button">Kembali Edit</button>
            <button class="btn btn-primary" data-confirm-preview form="sppd-form" type="submit" disabled>
                <i class="bi bi-send me-1" aria-hidden="true"></i> {{ $ticket ? 'Ajukan Ulang' : 'Kirim Pengajuan' }}
            </button>
        </footer>
    </dialog>
</div>
@endsection

@section('js')
<script>
    (() => {
        const form = document.getElementById('sppd-form');
        const preview = document.getElementById('sppd-preview');
        const previewStatus = preview.querySelector('[data-preview-status]');
        const previewFrame = preview.querySelector('[data-preview-frame]');
        const openPdf = preview.querySelector('[data-open-pdf]');
        const confirmPreview = preview.querySelector('[data-confirm-preview]');
        const previewToken = form.elements.preview_token;
        let previewRequest;
        let pdfUrl;
        let pendingProfile;
        const budget = form.querySelector('#sppd-costs');
        const otherCosts = form.querySelector('#sppd-other-costs');
        const otherCostTemplate = form.querySelector('#sppd-other-cost-template');
        const addCost = form.querySelector('#sppd-add-cost');
        const employeeRows = [...@json(\App\Support\Sppd::EMPLOYEE_COST_ROWS), {{ \App\Support\Sppd::WINTER_COST }}];
        const currency = new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2
        });
        const costTotal = form.querySelector('#cost-total');
        const costPaid = form.querySelector('#cost-paid');
        const departure = form.elements.depart_on;
        const returnDate = form.elements.return_on;
        const duration = form.querySelector('#sppd-days');
        const tripDays = form.querySelector('#sppd-trip-days');
        const category = form.elements.category;
        const exchangeStatus = form.querySelector('#sppd-exchange-status');
        const applyBenefits = form.querySelector('#sppd-apply-benefits');
        let benefits = @json(\App\Support\Sppd::benefits($profile['job_level']));
        let exchangeRate;
        let exchangeRequest;

        async function loadRequesterProfile() {
            const status = form.querySelector('#sppd-profile-status');
            const fields = Array.from(form.querySelectorAll('[data-profile-field]'));
            const initialValues = fields.map(field => field.value);
            status.textContent = 'Mengambil profil dari Portal LRTJ Space…';
            status.hidden = false;

            try {
                const response = await fetch(form.dataset.profileUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error();
                const profile = await response.json();

                const applyProfile = () => {
                    fields.forEach((field, index) => {
                        const value = profile[field.dataset.profileField];
                        if (value != null && String(value).trim() && field.value === initialValues[index]) {
                            field.value = value;
                            field.readOnly = true;
                        }
                    });
                    benefits = profile.benefits;
                    updateBenefitsButton();
                    status.textContent = fields.some(field => field.name && !field.value.trim())
                        ? 'Lengkapi kolom profil yang masih kosong.'
                        : '';
                    status.hidden = !status.textContent;
                };
                if (preview.open) {
                    pendingProfile = applyProfile;
                } else {
                    applyProfile();
                }
            } catch {
                status.textContent = 'Profil Portal belum bisa dimuat. Lengkapi kolom yang masih kosong.';
            }
        }

        function updateTotals() {
            let total = 0;
            let paid = 0;
            for (const input of budget.querySelectorAll('[data-budget-amount]')) {
                if (input.disabled) continue;
                const row = input.closest('.sppd-expense');
                const usd = row.querySelector('[data-budget-usd]:enabled');
                const usdCents = Math.round((Number(usd?.value) || 0) * 100);
                const cents = usdCents > 0
                    ? Math.round(usdCents * (exchangeRate?.idr_per_usd || 0))
                    : Math.round((Number(input.value) || 0) * 100);
                total += cents;
                const provider = row.querySelector('[data-cost-provider]')?.value;
                if (provider ? provider === 'reimburse' : employeeRows.includes(Number(input.dataset.cost))) paid += cents;
            }
            costTotal.value = currency.format(total / 100);
            costPaid.value = currency.format(paid / 100);
        }

        function updateDuration() {
            const daysBetween = (returnDate.valueAsNumber - departure.valueAsNumber) / 86400000;
            duration.value = daysBetween >= 0 ? `${daysBetween + 1} hari` : '—';
            tripDays.value = duration.value;
            updateBenefitsButton();
        }

        function updateBenefitsButton() {
            const rates = benefits?.[category.value];
            applyBenefits.hidden = !rates || !Object.keys(rates).length;
            applyBenefits.disabled = !(returnDate.valueAsNumber >= departure.valueAsNumber);
        }

        async function updateInternationalFields() {
            const international = category.value === 'international';
            for (const section of form.querySelectorAll('[data-sppd-international]')) {
                section.hidden = !international;
                section.querySelectorAll('input, select').forEach(input => input.disabled = !international);
            }
            updateTotals();
            updateBenefitsButton();
            if (!international || exchangeRate || exchangeRequest) return;

            exchangeStatus.textContent = 'Mengambil kurs acuan ECB…';
            exchangeRequest = fetch(form.dataset.exchangeUrl, { headers: { Accept: 'application/json' } });
            try {
                const response = await exchangeRequest;
                if (!response.ok) throw new Error();
                exchangeRate = await response.json();
                exchangeStatus.textContent = `1 USD ≈ ${currency.format(exchangeRate.idr_per_usd)} · ECB ${exchangeRate.date}`;
                updateTotals();
            } catch {
                exchangeStatus.textContent = 'Estimasi kurs belum tersedia. Kurs akan dicoba lagi saat pratinjau.';
            } finally {
                exchangeRequest = null;
            }
        }

        function updateOtherRows() {
            Array.from(otherCosts.children).forEach((row, index) => {
                const number = `${otherCosts.dataset.groupNumber}.${index + 1}`;
                row.querySelector('.sppd-expense-number').textContent = number;
                row.setAttribute('aria-label', `Pengeluaran ${number}`);
                row.querySelectorAll('[data-other-cost-field]').forEach(input => {
                    input.name = `other_costs[${index}][${input.dataset.otherCostField}]`;
                });
            });
            updateInternationalFields();
        }

        async function showPreview() {
            if (preview.open) return;

            const data = new FormData(form);
            data.delete('_method');
            data.delete('preview_token');
            previewStatus.textContent = 'Menyiapkan pratinjau PDF…';
            previewStatus.hidden = false;
            previewRequest = new AbortController();
            const { signal } = previewRequest;
            preview.showModal();

            try {
                const response = await fetch(form.dataset.previewUrl, {
                    method: 'POST', body: data, signal, headers: { Accept: 'application/json' }
                });
                if (!response.ok) {
                    const failure = await response.json().catch(() => ({}));
                    throw new Error(Object.values(failure.errors || {}).flat()[0]
                        || failure.message || 'Pratinjau gagal dimuat. Coba lagi.');
                }
                const token = response.headers.get('X-Sppd-Preview-Token');
                if (!token || !response.headers.get('Content-Type')?.startsWith('application/pdf')) {
                    throw new Error('Pratinjau gagal dimuat. Coba lagi.');
                }
                const blob = await response.blob();
                if (signal.aborted) return;

                pdfUrl = URL.createObjectURL(blob);
                previewFrame.src = pdfUrl;
                previewFrame.hidden = false;
                openPdf.href = pdfUrl;
                openPdf.hidden = false;
                previewStatus.hidden = true;
                previewToken.value = token;
                confirmPreview.disabled = false;
            } catch (error) {
                if (!signal.aborted) previewStatus.textContent = error.message || 'Pratinjau gagal dimuat. Coba lagi.';
            }
        }

        form.addEventListener('submit', event => {
            if (event.submitter === confirmPreview && preview.open && previewToken.value) {
                confirmPreview.disabled = true;
                return;
            }

            event.preventDefault();
            showPreview();
        });
        function closePreview() {
            previewRequest?.abort();
            previewFrame.hidden = true;
            previewFrame.removeAttribute('src');
            openPdf.hidden = true;
            openPdf.removeAttribute('href');
            if (pdfUrl) URL.revokeObjectURL(pdfUrl);
            pdfUrl = null;
            previewToken.value = '';
            confirmPreview.disabled = true;
            preview.close();
            pendingProfile?.();
            pendingProfile = null;
        }

        preview.querySelector('[data-close-preview]').addEventListener('click', closePreview);
        preview.addEventListener('cancel', event => {
            event.preventDefault();
            closePreview();
        });

        addCost.addEventListener('click', () => {
            const row = otherCostTemplate.content.firstElementChild.cloneNode(true);
            otherCosts.append(row);
            updateOtherRows();
            row.querySelector('[data-other-cost-field="label"]').focus();
        });
        otherCosts.addEventListener('click', event => {
            const remove = event.target.closest('[data-remove-other-cost]');
            if (!remove) return;

            const row = remove.closest('.sppd-other-expense');
            const neighbor = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            updateOtherRows();
            (neighbor?.querySelector('[data-other-cost-field="label"]') || addCost).focus();
        });
        budget.addEventListener('input', event => {
            if (event.target.matches('[data-budget-amount], [data-budget-usd], [data-cost-provider]')) updateTotals();
        });
        applyBenefits.addEventListener('click', () => {
            const days = (returnDate.valueAsNumber - departure.valueAsNumber) / 86400000 + 1;
            for (const [row, benefit] of Object.entries(benefits?.[category.value] || {})) {
                const input = budget.querySelector(`[data-cost="${row}"]`);
                const enteredUsd = input?.closest('.sppd-expense').querySelector('[data-budget-usd]:enabled')?.value;
                if (input && !input.disabled && Number.isFinite(Number(benefit.idr)) && !enteredUsd) {
                    input.value = (Number(benefit.idr) * (benefit.per_day ? days : 1)).toFixed(2);
                }
            }
            updateTotals();
        });
        category.addEventListener('change', updateInternationalFields);
        [departure, returnDate].forEach(input => input.addEventListener('input', updateDuration));
        updateDuration();
        updateInternationalFields();
        loadRequesterProfile();
    })();
</script>
@endsection
