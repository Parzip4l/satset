<tr>
    <td>{{ $number }}</td>
    <td>{{ $label }}</td>
    @if($international)
        <td class="amount">{{ isset($expense['usd_cents']) ? number_format($expense['usd_cents'] / 100, 2, ',', '.') : '-' }}</td>
    @endif
    <td class="amount">{{ \App\Support\Sppd::rupiah((int) ($expense['cents'] ?? 0)) }}</td>
    <td>
        @if(isset(\App\Support\Sppd::PROVIDERS[$expense['provider'] ?? null]))
            {{ \App\Support\Sppd::PROVIDERS[$expense['provider']] }}
            @if(filled($expense['note'] ?? null))<br>@endif
        @endif
        {{ $expense['note'] ?? '' }}
    </td>
</tr>
