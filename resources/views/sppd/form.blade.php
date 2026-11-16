@extends('partials.layouts.master')

@section('title', ($ticket ? 'Revisi SPPD' : 'Ajukan SPPD').' | Satset')
@section('pagetitle', $ticket ? 'Revisi SPPD' : 'Ajukan SPPD')

@section('content')
<div class="container-fluid">
    <a class="btn btn-light border mb-3" href="{{ $ticket ? route('sppd.show', $ticket) : route('sppd.index') }}">Kembali</a>
    @include('sppd.messages')

    <form method="POST" action="{{ $ticket ? route('sppd.update', $ticket) : route('sppd.store') }}">
        @csrf
        @if($ticket)
            @method('PUT')
            <input type="hidden" name="version" value="{{ $sppd['version'] }}">
        @else
            <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
        @endif

        <section class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Pelaksana Perjalanan Dinas</h5></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="employee-name">Nama</label>
                    <input class="form-control" id="employee-name" value="{{ $profile['name'] }}" readonly>
                </div>
                @foreach(['nik' => 'NIK', 'position' => 'Jabatan', 'division' => 'Divisi', 'directorate' => 'Direktorat'] as $key => $label)
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                        <input class="form-control" id="{{ $key }}" name="{{ $key }}" maxlength="{{ $key === 'nik' ? 40 : 100 }}"
                            value="{{ $profile[$key] ?: old($key, data_get($sppd, "profile.{$key}")) }}" @readonly(filled($profile[$key]))>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Detail Perjalanan Dinas</h5></div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label" for="category">Kategori Perjalanan Dinas</label>
                    <select class="form-select" name="category" id="category" required>
                        <option value="">Pilih kategori</option>
                        @foreach(\App\Support\Sppd::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category', $sppd['category'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach(['origin' => 'Dari', 'destination' => 'Ke', 'depart_on' => 'Tanggal Berangkat', 'return_on' => 'Tanggal Kembali'] as $key => $label)
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                        <input class="form-control" id="{{ $key }}" name="{{ $key }}" type="{{ str_ends_with($key, '_on') ? 'date' : 'text' }}"
                            value="{{ old($key, $sppd[$key] ?? '') }}" maxlength="120" required>
                    </div>
                @endforeach
                <div class="col-12">
                    <label class="form-label" for="purpose">Keperluan Perjalanan Dinas</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3" maxlength="700" required>{{ old('purpose', $sppd['purpose'] ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Rencana Anggaran Biaya Perjalanan Dinas</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="sppd-costs">
                    <thead><tr><th>Pengeluaran</th><th>Biaya (Rp)</th><th>Keterangan</th></tr></thead>
                    <tbody>
                    @foreach(\App\Support\Sppd::COSTS as $row => [$number, $label, $note])
                        <tr>
                            <th scope="row">
                                <label class="mb-0" for="amount-{{ $row }}">{{ $label }}{{ $row === 34 || $row === 36 ? ' (baris '.$row.')' : '' }}</label>
                            </th>
                            <td>
                                <input class="form-control" id="amount-{{ $row }}" name="costs[{{ $row }}][amount]" type="number" inputmode="decimal"
                                    min="0" max="9999999999.99" step="0.01" data-cost="{{ $row }}"
                                    value="{{ old("costs.{$row}.amount", isset($sppd['costs'][$row]) ? number_format($sppd['costs'][$row]['cents'] / 100, 2, '.', '') : '') }}">
                            </td>
                            <td>
                                <input class="form-control" aria-label="Keterangan {{ $label }} baris {{ $row }}" name="costs[{{ $row }}][note]" maxlength="90"
                                    value="{{ old("costs.{$row}.note", data_get($sppd, "costs.{$row}.note", $note)) }}">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="2">Jumlah Keseluruhan</th><td><output id="cost-total">Rp0</output></td></tr>
                        <tr><th colspan="2">Jumlah yang Dibayarkan ke Karyawan</th><td><output id="cost-paid">Rp0</output></td></tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Catatan Tambahan</h5></div>
            <div class="card-body row g-3">
                @foreach(['name' => ['Bank', 50], 'account' => ['Nomor Rekening', 30], 'holder' => ['Nama Pemilik Rekening', 100]] as $key => [$label, $length])
                    <div class="col-md-4">
                        <label class="form-label" for="bank-{{ $key }}">{{ $label }}</label>
                        <input class="form-control" id="bank-{{ $key }}" name="bank[{{ $key }}]" maxlength="{{ $length }}" @if($key === 'account') inputmode="numeric" pattern="[0-9]{5,30}" @endif
                            value="{{ old("bank.{$key}", data_get($sppd, "bank.{$key}")) }}">
                    </div>
                @endforeach
                <div class="col-12">
                    <label class="form-label" for="notes">Catatan</label>
                    <textarea class="form-control" id="notes" name="notes" maxlength="500" rows="3">{{ old('notes', $sppd['notes'] ?? '') }}</textarea>
                </div>
            </div>
        </section>
        <button class="btn btn-primary mb-4" type="submit">{{ $ticket ? 'Ajukan Ulang' : 'Kirim Pengajuan' }}</button>
    </form>
</div>
<script>
    const costs = Array.from(document.querySelectorAll('#sppd-costs [data-cost]'));
    const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const employeeRows = @json(\App\Support\Sppd::EMPLOYEE_COST_ROWS);
    function updateSppdTotals() {
        const cents = input => Math.round((Number(input.value) || 0) * 100);
        const all = costs.reduce((total, input) => total + cents(input), 0);
        const employee = costs.filter(input => employeeRows.includes(Number(input.dataset.cost)))
            .reduce((total, input) => total + cents(input), 0);
        document.getElementById('cost-total').value = rupiah.format(all / 100);
        document.getElementById('cost-paid').value = rupiah.format(employee / 100);
    }
    costs.forEach(input => input.addEventListener('input', updateSppdTotals));
    updateSppdTotals();
</script>
@endsection
