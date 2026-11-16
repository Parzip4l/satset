@if(filled(data_get($sppd, 'bank.account')))
    <div>Jumlah yang ditransfer sebesar {{ \App\Support\Sppd::rupiah($sppd['employee_cents']) }}
        dikirim ke rekening {{ $sppd['bank']['name'] }} {{ $sppd['bank']['account'] }} a/n {{ $sppd['bank']['holder'] }}.</div>
@endif
@if(filled($sppd['notes']))
    <div @unless($forPrint ?? false) style="white-space: pre-line" @endunless>{{ $sppd['notes'] }}</div>
@endif
