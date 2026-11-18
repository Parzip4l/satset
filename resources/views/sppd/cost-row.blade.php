@php
    $cost = $sppd['costs'][$row] ?? [];
    $proposal = data_get($sppd, "budget_revision.costs.{$row}");
    $usd = $proposal && $proposal['currency'] === 'USD' ? $proposal['to'] : ($cost['usd_cents'] ?? null);
    $idr = $proposal && $proposal['currency'] === 'IDR' ? $proposal['to'] : ($cost['cents'] ?? null);
@endphp
<div class="sppd-expense" @if($row === \App\Support\Sppd::WINTER_COST) data-sppd-international hidden @endif>
    <span class="sppd-expense-number">{{ $number }}</span>
    <label class="sppd-expense-name" for="amount-{{ $row }}">{{ $label }}</label>
    <div class="sppd-cost-fields">
        <div class="sppd-money">
            <span aria-hidden="true">Rp</span>
            <input class="form-control" id="amount-{{ $row }}" name="costs[{{ $row }}][amount]" type="number"
                aria-label="Biaya rupiah {{ $label }}" inputmode="decimal" min="0" max="9999999999.99" step="0.01" data-cost="{{ $row }}" data-budget-amount
                value="{{ old("costs.{$row}.amount", $usd === null && $idr !== null ? number_format($idr / 100, 2, '.', '') : '') }}">
        </div>
        <div class="sppd-money" data-sppd-international hidden>
            <span aria-hidden="true">USD</span>
            <input class="form-control" name="costs[{{ $row }}][usd_amount]" type="number"
                aria-label="Biaya USD {{ $label }}" inputmode="decimal" min="0" max="999999.99" step="0.01" data-budget-usd
                value="{{ old("costs.{$row}.usd_amount", $usd === null ? '' : number_format($usd / 100, 2, '.', '')) }}">
        </div>
    </div>
    <div class="sppd-expense-note sppd-cost-fields">
        @if(in_array($row, \App\Support\Sppd::PROVIDER_ROWS, true))
            <select class="form-select" aria-label="Penanggung biaya {{ $label }}" name="costs[{{ $row }}][provider]" data-cost-provider>
                <option value="">Pilih penanggung biaya</option>
                @foreach(\App\Support\Sppd::PROVIDERS as $key => $provider)
                    <option value="{{ $key }}" @selected(old("costs.{$row}.provider", $cost['provider'] ?? '') === $key)>{{ $provider }}</option>
                @endforeach
            </select>
        @endif
        <input class="form-control" aria-label="Keterangan {{ $label }} nomor {{ $number }}" name="costs[{{ $row }}][note]" maxlength="90"
            value="{{ old("costs.{$row}.note", $cost['note'] ?? '') }}">
    </div>
</div>
