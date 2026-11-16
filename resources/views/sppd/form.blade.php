@extends('partials.layouts.master')

@section('title', ($ticket ? 'Revisi SPPD' : 'Ajukan SPPD').' | Satset')
@section('page-heading', '')

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/sppd-form.css') }}">
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

    <form class="sppd-form-layout" id="sppd-form" method="POST" action="{{ $ticket ? route('sppd.update', $ticket) : route('sppd.store') }}">
        @csrf
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
                    <div>
                        <label class="form-label" for="employee-name">Nama</label>
                        <input class="form-control" id="employee-name" value="{{ $profile['name'] }}" readonly>
                    </div>
                    @foreach(['nik' => 'NIK', 'position' => 'Jabatan', 'division' => 'Divisi', 'directorate' => 'Direktorat'] as $key => $label)
                        <div @class(['sppd-wide' => $key === 'directorate'])>
                            <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                            <input class="form-control" id="{{ $key }}" name="{{ $key }}" maxlength="{{ $key === 'nik' ? 40 : 100 }}"
                                value="{{ $profile[$key] ?: old($key, data_get($sppd, "profile.{$key}")) }}" @readonly(filled($profile[$key]))>
                        </div>
                    @endforeach
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
                    <div class="sppd-wide">
                        <label class="form-label" for="purpose">Keperluan Perjalanan Dinas <span class="sppd-required" aria-hidden="true">*</span></label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3" maxlength="700" required>{{ old('purpose', $sppd['purpose'] ?? '') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="sppd-panel" aria-labelledby="sppd-budget-title">
                <header class="sppd-panel-header">
                    <span class="sppd-section-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                    <h2 id="sppd-budget-title">Rencana Anggaran</h2>
                </header>
                <div class="sppd-expenses" id="sppd-costs">
                    <div class="sppd-expense-head" aria-hidden="true">
                        <span>Pengeluaran</span><span>Biaya</span><span>Keterangan</span>
                    </div>
                    @foreach(\App\Support\Sppd::COSTS as $row => [, $label, $note])
                        <div class="sppd-expense">
                            <label class="sppd-expense-name" for="amount-{{ $row }}">
                                {{ $label }}
                                @if(in_array($row, [34, 36], true))
                                    <span class="sppd-row-reference">Baris {{ $row }}</span>
                                @endif
                                <span class="visually-hidden">, biaya dalam rupiah</span>
                            </label>
                            <div class="sppd-money">
                                <span aria-hidden="true">Rp</span>
                                <input class="form-control" id="amount-{{ $row }}" name="costs[{{ $row }}][amount]" type="number" inputmode="decimal"
                                    min="0" max="9999999999.99" step="0.01" data-cost="{{ $row }}" placeholder="0"
                                    value="{{ old("costs.{$row}.amount", isset($sppd['costs'][$row]) ? number_format($sppd['costs'][$row]['cents'] / 100, 2, '.', '') : '') }}">
                            </div>
                            <input class="form-control" aria-label="Keterangan {{ $label }} baris {{ $row }}" name="costs[{{ $row }}][note]" maxlength="90"
                                placeholder="Opsional" value="{{ old("costs.{$row}.note", data_get($sppd, "costs.{$row}.note", $note)) }}">
                        </div>
                    @endforeach
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
                    <i class="bi bi-send" aria-hidden="true"></i> {{ $ticket ? 'Ajukan Ulang' : 'Kirim Pengajuan' }}
                </button>
            </div>
        </aside>
    </form>
</div>
@endsection

@section('js')
<script>
    (() => {
        const form = document.getElementById('sppd-form');
        const amounts = form.querySelectorAll('[data-cost]');
        const employeeRows = @json(\App\Support\Sppd::EMPLOYEE_COST_ROWS);
        const currency = new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2
        });
        const costTotal = form.querySelector('#cost-total');
        const costPaid = form.querySelector('#cost-paid');
        const departure = form.elements.depart_on;
        const returnDate = form.elements.return_on;
        const duration = form.querySelector('#sppd-days');

        function updateTotals() {
            let total = 0;
            let paid = 0;
            for (const input of amounts) {
                const cents = Math.round((Number(input.value) || 0) * 100);
                total += cents;
                if (employeeRows.includes(Number(input.dataset.cost))) paid += cents;
            }
            costTotal.value = currency.format(total / 100);
            costPaid.value = currency.format(paid / 100);
        }

        function updateDuration() {
            const daysBetween = (returnDate.valueAsNumber - departure.valueAsNumber) / 86400000;
            duration.value = daysBetween >= 0 ? `${daysBetween + 1} hari` : '—';
        }

        amounts.forEach(input => input.addEventListener('input', updateTotals));
        [departure, returnDate].forEach(input => input.addEventListener('input', updateDuration));
        updateTotals();
        updateDuration();
    })();
</script>
@endsection
