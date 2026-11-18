@php
    $international = $sppd['category'] === 'international';
    $columns = $international ? 5 : 4;
@endphp
@if(filled($sppd['exchange_rate'] ?? null))
    <p class="sppd-rate">
        Kurs acuan <a href="{{ \App\Services\SppdExchangeRate::SOURCE }}">ECB</a>
        {{ $sppd['exchange_rate']['date'] }} · 1 USD = {{ \App\Support\Sppd::rupiah((int) round($sppd['exchange_rate']['idr_per_usd'] * 100)) }}
    </p>
@endif
<table class="{{ $tableClass }}">
    <thead>
        <tr>
            <th style="width: 6%">No.</th>
            <th style="width: 30%">Pengeluaran</th>
            @if($international)<th style="width: 14%">Biaya (USD)</th>@endif
            <th style="width: 22%">Biaya (IDR)</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach(\App\Support\Sppd::COSTS as $row => [$number, $label])
            @include('sppd.budget-row', ['expense' => $sppd['costs'][$row] ?? []])
        @endforeach
        <tr>
            <td>{{ \App\Support\Sppd::OTHER_COST_NUMBER }}</td>
            <th scope="row" colspan="{{ $columns - 1 }}">Lain-lain</th>
        </tr>
        @foreach(\App\Support\Sppd::otherCosts($sppd) as $expense)
            @include('sppd.budget-row', ['number' => \App\Support\Sppd::OTHER_COST_NUMBER.'.'.$loop->iteration, 'label' => $expense['label']])
        @endforeach
        @if($international && isset($sppd['costs'][\App\Support\Sppd::WINTER_COST]))
            @include('sppd.budget-row', ['number' => '7', 'label' => 'Pakaian Musim Dingin', 'expense' => $sppd['costs'][\App\Support\Sppd::WINTER_COST]])
        @endif
        <tr><th colspan="{{ $columns - 2 }}">Jumlah Keseluruhan</th><td class="amount"><strong>{{ \App\Support\Sppd::rupiah($sppd['total_cents']) }}</strong></td><td></td></tr>
        <tr><th colspan="{{ $columns - 2 }}">Jumlah yang dibayarkan ke karyawan</th><td class="amount"><strong>{{ \App\Support\Sppd::rupiah($sppd['employee_cents']) }}</strong></td><td></td></tr>
    </tbody>
</table>
