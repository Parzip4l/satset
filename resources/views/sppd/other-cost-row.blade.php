@php($number = \App\Support\Sppd::OTHER_COST_NUMBER.'.'.($index + 1))
<div class="sppd-expense sppd-other-expense" role="group" aria-label="Pengeluaran {{ $number }}">
    <span class="sppd-expense-number">{{ $number }}</span>
    <input class="form-control sppd-other-name" name="other_costs[{{ $index }}][label]" data-other-cost-field="label"
        aria-label="Nama pengeluaran" placeholder="Nama pengeluaran" maxlength="120" value="{{ $expense['label'] ?? '' }}" required>
    <div class="sppd-cost-fields">
    <div class="sppd-money">
        <span aria-hidden="true">Rp</span>
        <input class="form-control" name="other_costs[{{ $index }}][amount]" data-other-cost-field="amount" data-budget-amount
            aria-label="Biaya dalam rupiah" type="number" inputmode="decimal" min="0" max="9999999999.99" step="0.01"
            value="{{ $expense['amount'] ?? '' }}">
    </div>
    <div class="sppd-money" data-sppd-international hidden>
        <span aria-hidden="true">USD</span>
        <input class="form-control" name="other_costs[{{ $index }}][usd_amount]" data-other-cost-field="usd_amount" data-budget-usd
            aria-label="Biaya USD" type="number" inputmode="decimal" min="0" max="999999.99" step="0.01" value="{{ $expense['usd_amount'] ?? '' }}">
    </div>
    </div>
    <input class="form-control sppd-expense-note" name="other_costs[{{ $index }}][note]" data-other-cost-field="note"
        aria-label="Keterangan" maxlength="90" value="{{ $expense['note'] ?? '' }}">
    <button class="btn btn-outline-danger sppd-remove-expense" type="button" data-remove-other-cost aria-label="Hapus baris pengeluaran">
        <i class="bi bi-trash" aria-hidden="true"></i>
    </button>
</div>
