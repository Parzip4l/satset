@php
    $text = fn ($value, $fallback = '-') => filled($value) ? $value : $fallback;
    $requesterName = $text(data_get($payload, 'reporter_name'), $ticket->requester?->name);
    $departmentName = $text(data_get($payload, 'goods_issue.department'), $ticket->requester?->division?->name);
    $reservationNo = $text(data_get($payload, 'goods_issue.reservation_no'));
    $goodsIssueNo = $text(data_get($payload, 'goods_issue.number'));
    $requestFor = $text(data_get($payload, 'goods_issue.request_for'), data_get($payload, 'request_subject'));
    $approvedBy = $ticket->approvals
        ->filter(fn ($approval) => strtolower((string) $approval->status) === 'approved')
        ->sortByDesc('level')
        ->first()?->approver?->name;
    $managerApproval = $ticket->approvals
        ->filter(fn ($approval) => strtolower((string) $approval->status) === 'approved')
        ->sortBy('level')
        ->first();
    $issuedBy = $issueHistory?->user?->name ?: data_get($payload, 'bum_officer_name');
    $receivedBy = data_get($payload, 'received_by') ?: $requesterName;
    $requesterSignature = data_get($payload, 'portal_signatures.requester', []);
    $issueSignature = data_get($payload, 'portal_signatures.issue', []);
    $receiveSignature = data_get($payload, 'portal_signatures.receive', []);
    $signatureQrUrl = fn ($signature) => $signature ? data_get($signature, 'portal_qr_url') : null;
    $signatureQrInline = function ($signature) {
        if (! $signature) {
            return null;
        }

        $value = data_get($signature, 'portal_signature_url') ?: data_get($signature, 'portal_qr_payload');
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(132)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($value);
        } catch (\Throwable $e) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    };
    $signatureQrImage = fn ($signature) => $signatureQrInline($signature) ?: $signatureQrUrl($signature);
    $issueDate = data_get($payload, 'handover_date') ?: $issueHistory?->created_at ?: $ticket->closed_at;
    $requestDate = data_get($payload, 'goods_issue.request_date') ?: data_get($payload, 'needed_date');
    try {
        $formattedIssueDate = $issueDate ? \Carbon\Carbon::parse($issueDate)->format('d-m-Y') : '-';
    } catch (\Throwable $e) {
        $formattedIssueDate = $issueDate ?: '-';
    }
    try {
        $formattedRequestDate = $requestDate ? \Carbon\Carbon::parse($requestDate)->format('d-m-Y') : '-';
    } catch (\Throwable $e) {
        $formattedRequestDate = $requestDate ?: '-';
    }
    $minimumRows = 18;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Goods Issue {{ $ticket->ticket_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f5; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
        .toolbar { max-width: 210mm; margin: 18px auto; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar a, .toolbar button { border: 1px solid #b8c2cc; background: #fff; color: #1f2937; border-radius: 6px; padding: 9px 14px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #111827; border-color: #111827; color: #fff; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto 18px; background: #fff; padding: 8mm; box-shadow: 0 10px 30px rgba(15, 23, 42, .15); }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { border: 1px solid #111; padding: 4px 6px; vertical-align: middle; }
        .document-meta td { border-style: dotted; }
        .document-meta .logo { height: 64px; text-align: center; }
        .document-meta .logo img { max-height: 46px; max-width: 210px; }
        .document-meta .title { text-align: center; font-size: 18px; font-weight: 800; font-style: italic; }
        .label { font-weight: 700; }
        .form { margin-top: 8px; border: 2px solid #111; padding: 10px; }
        .form-head td { height: 24px; }
        .brand-cell { text-align: center; }
        .brand-cell img { width: 122px; max-height: 74px; object-fit: contain; }
        .brand-name { margin-top: 3px; font-size: 16px; font-weight: 700; font-style: italic; color: #555; }
        .gi-title { text-align: center; font-size: 27px; font-weight: 900; font-style: italic; letter-spacing: 1px; color: #1f4f68; }
        .gi-subtitle { text-align: center; margin-top: 8px; font-size: 16px; font-weight: 800; font-style: italic; color: #ed2b49; }
        .items { margin-top: 18px; }
        .items th { background: #075779; color: #fff; font-size: 12px; font-style: italic; padding: 6px 4px; }
        .items tbody td { height: 24px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .remarks { font-size: 10px; color: #333; }
        .location { font-size: 13px; font-weight: 700; font-style: italic; }
        .signatures { margin-top: 16px; }
        .signatures th { border-bottom: 0; height: 27px; font-size: 13px; font-style: italic; }
        .signatures .space td { border-top: 0; border-bottom: 0; height: 102px; text-align: center; }
        .signatures .names td { border-top: 0; height: 31px; text-align: center; font-weight: 700; font-style: italic; }
        .signatures .roles td { border-top: 0; text-align: center; font-size: 10px; color: #555; }
        .signature-qr-frame { position: relative; display: inline-flex; width: 84px; height: 84px; align-items: center; justify-content: center; background: #fff; border: 1px solid #e4c693; border-radius: 6px; padding: 4px; }
        .signature-qr { width: 74px; height: 74px; display: block; object-fit: contain; }
        .signature-qr-logo { position: absolute; left: 50%; top: 50%; width: 19px; height: 19px; transform: translate(-50%, -50%); display: inline-flex; align-items: center; justify-content: center; background: #fff; border-radius: 999px; box-shadow: 0 0 0 3px #fff; }
        .signature-qr-logo img { max-width: 14px; max-height: 11px; display: block; }
        .signature-placeholder { color: #777; font-size: 10px; line-height: 1.2; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('ticket.show', $ticket) }}">Kembali</a>
        <a href="{{ route('ticket.atk-rtk.goods-issue', [$ticket, 'download' => 1]) }}">Download HTML</a>
        <button type="button" class="primary" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="page">
        <table class="document-meta">
            <colgroup><col style="width:20%"><col style="width:30%"><col style="width:22%"><col style="width:28%"></colgroup>
            <tr>
                <td colspan="2" class="logo"><img src="{{ asset('logo-lrtj.png') }}" alt="LRT Jakarta"></td>
                <td colspan="2" class="title">Goods Issue</td>
            </tr>
            <tr>
                <td class="label">Nomor Dokumen</td><td>LRTJ-FR-BUM-022</td>
                <td class="label">Nomor Revisi</td><td>00</td>
            </tr>
            <tr>
                <td class="label">Dokumen Referensi</td><td>{{ $ticket->ticket_no }}</td>
                <td class="label">Halaman</td><td>Page 1 of 1</td>
            </tr>
        </table>

        <section class="form">
            <table class="form-head">
                <colgroup><col style="width:27%"><col style="width:38%"><col style="width:35%"></colgroup>
                <tr>
                    <td rowspan="5" class="brand-cell">
                        <img src="{{ asset('logo-lrtj.png') }}" alt="LRT Jakarta">
                        <div class="brand-name">LRT JAKARTA</div>
                    </td>
                    <td colspan="2" rowspan="2">
                        <div class="gi-title">GOODS ISSUE</div>
                        <div class="gi-subtitle">GENERAL AFFAIR DEPARTMENT</div>
                    </td>
                </tr>
                <tr></tr>
                <tr><td><span class="label">No. Reservation:</span> {{ $reservationNo }}</td><td><span class="label">No GI:</span> {{ $goodsIssueNo }}</td></tr>
                <tr><td><span class="label">Department:</span> {{ $departmentName }}</td><td><span class="label">Date:</span> {{ $formattedRequestDate }}</td></tr>
                <tr><td colspan="2"><span class="label">Request for:</span> {{ $requestFor }}</td></tr>
            </table>

            <table class="items">
                <colgroup><col style="width:8%"><col style="width:17%"><col style="width:37%"><col style="width:10%"><col style="width:11%"><col style="width:17%"></colgroup>
                <thead><tr><th>NO</th><th>ITEM CODE</th><th>DESCRIPTION</th><th>QTY</th><th>UOM</th><th>REMARKS</th></tr></thead>
                <tbody>
                    @foreach($items as $index => $item)
                        @php
                            $isLarge = $item['quantity'] >= $item['conversion_qty'];
                            $largeEquivalent = $item['quantity'] / $item['conversion_qty'];
                        @endphp
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td>{{ $text(data_get($item, 'item_code')) }}</td>
                            <td>{{ $text(data_get($item, 'item_name')) }}</td>
                            <td class="right">{{ number_format($item['quantity']) }}</td>
                            <td class="center">{{ $text(data_get($item, 'small_uom')) }}</td>
                            <td class="remarks">
                                @if($isLarge)
                                    Setara {{ rtrim(rtrim(number_format($largeEquivalent, 2, ',', '.'), '0'), ',') }} {{ $text(data_get($item, 'large_uom')) }}
                                @else
                                    Di bawah 1 {{ $text(data_get($item, 'large_uom')) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @for($row = $items->count(); $row < $minimumRows; $row++)
                        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
                    @endfor
                    <tr><td colspan="6" class="location">GI Location and Date Issue: {{ $text(data_get($payload, 'delivery_location')) }}, {{ $formattedIssueDate }}</td></tr>
                </tbody>
            </table>

            <table class="signatures">
                <tr><th>Reserve</th><th>Approve</th><th>Issue</th><th>Receive</th></tr>
                <tr class="space">
                    @foreach([
                        [$requesterSignature, 'Pemohon'],
                        [$managerApproval, 'Approver'],
                        [$issueSignature, 'Petugas GA'],
                        [$receiveSignature, 'Penerima'],
                    ] as [$signature, $signatureLabel])
                        <td>
                            @if($qr = $signatureQrImage($signature))
                                <span class="signature-qr-frame">
                                    <img class="signature-qr" src="{{ $qr }}" alt="QR Portal {{ $signatureLabel }}">
                                    <span class="signature-qr-logo"><img src="{{ asset('assets/images/logo-esign.png') }}" alt=""></span>
                                </span>
                            @else
                                <span class="signature-placeholder">QR Portal<br>belum tersedia</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr class="names"><td>{{ $requesterName }}</td><td>{{ $text($approvedBy) }}</td><td>{{ $text($issuedBy) }}</td><td>{{ $receivedBy }}</td></tr>
                <tr class="roles"><td>User</td><td>GA SPV / Manager</td><td>GA Team</td><td>User</td></tr>
            </table>
        </section>
    </main>
</body>
</html>
