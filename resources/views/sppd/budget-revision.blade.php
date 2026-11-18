<details class="card-body border-top">
    <summary class="fw-semibold">Revisi Nominal Anggaran</summary>
    <form action="{{ route('sppd.propose-budget', $ticket) }}" method="POST" class="mt-3">
        @csrf
        @method('PUT')
        <input type="hidden" name="version" value="{{ $sppd['version'] }}">
        <p class="text-muted">Pemohon akan menerima usulan ini dan mengajukan ulang dari tahap persetujuan pertama.</p>
        <div class="row g-3">
            @foreach(['costs' => $sppd['costs'], 'other_costs' => \App\Support\Sppd::otherCosts($sppd)] as $group => $rows)
                @foreach($rows as $key => $expense)
                    @php
                        $label = $expense['label'] ?? (\App\Support\Sppd::costRows(true)[$key][1] ?? null);
                        $usd = isset($expense['usd_cents']);
                    @endphp
                    @if($label)
                        <div class="col-sm-6 col-lg-4">
                            <label class="form-label" for="sppd-revision-{{ $group }}-{{ $key }}">{{ $label }} ({{ $usd ? 'USD' : 'IDR' }})</label>
                            <input class="form-control" type="number" min="0" max="{{ $usd ? '999999.99' : '9999999999.99' }}" step="0.01" required
                                id="sppd-revision-{{ $group }}-{{ $key }}" name="{{ $group }}[{{ $key }}]"
                                value="{{ old("{$group}.{$key}", number_format(($expense['usd_cents'] ?? $expense['cents']) / 100, 2, '.', '')) }}">
                        </div>
                    @endif
                @endforeach
            @endforeach
            <div class="col-12">
                <label class="form-label" for="sppd-revision-reason">Alasan perubahan</label>
                <textarea class="form-control" id="sppd-revision-reason" name="reason" maxlength="500" required>{{ old('reason') }}</textarea>
            </div>
        </div>
        <button class="btn btn-outline-primary mt-3" type="submit">Kirim Usulan Nominal</button>
    </form>
</details>
