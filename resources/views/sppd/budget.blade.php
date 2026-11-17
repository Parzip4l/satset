<table class="{{ $tableClass }}">
    <thead>
        <tr><th rowspan="2">No.</th><th rowspan="2">Pengeluaran</th><th colspan="2">Rincian Biaya</th><th rowspan="2">Keterangan</th></tr>
        <tr><th>Mata Uang</th><th>Biaya</th></tr>
    </thead>
    <tbody>
    @foreach(\App\Support\Sppd::COSTS as $row => [$number, $label])
        <tr>
            <td style="width: 6%">{{ $number }}</td>
            <td style="width: 39%">{{ $label }}</td>
            <td style="width: 12%">{{ $row === 31 && empty($sppd['costs'][$row]['cents']) ? '' : 'Rp' }}</td>
            <td style="width: 19%" class="amount">{{ number_format($sppd['costs'][$row]['cents'] / 100, $sppd['costs'][$row]['cents'] % 100 === 0 ? 0 : 2, ',', '.') }}</td>
            <td style="width: 24%">{{ $sppd['costs'][$row]['note'] }}</td>
        </tr>
    @endforeach
        <tr>
            <td>{{ \App\Support\Sppd::OTHER_COST_NUMBER }}</td>
            <th scope="row" colspan="4">Lain-lain</th>
        </tr>
        @foreach(\App\Support\Sppd::otherCosts($sppd) as $expense)
            <tr>
                <td>{{ \App\Support\Sppd::OTHER_COST_NUMBER }}.{{ $loop->iteration }}</td>
                <td>{{ $expense['label'] }}</td>
                <td>Rp</td>
                <td class="amount">{{ number_format($expense['cents'] / 100, $expense['cents'] % 100 === 0 ? 0 : 2, ',', '.') }}</td>
                <td>{{ $expense['note'] }}</td>
            </tr>
        @endforeach
        <tr><th colspan="3">Jumlah Keseluruhan</th><td class="amount"><strong>{{ \App\Support\Sppd::rupiah($sppd['total_cents']) }}</strong></td><td></td></tr>
        <tr><th colspan="3">Jumlah yang dibayarkan ke karyawan</th><td class="amount"><strong>{{ \App\Support\Sppd::rupiah($sppd['employee_cents']) }}</strong></td><td></td></tr>
    </tbody>
</table>
