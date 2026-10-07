<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan {{ $ticket->ticket_no }}</title>
    <style>
        @page { margin: 105px 42px 58px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #25324a; font-size: 10px; line-height: 1.45; }
        header { position: fixed; top: -78px; left: 0; right: 0; height: 62px; border-bottom: 2px solid #e21b23; }
        footer { position: fixed; bottom: -38px; left: 0; right: 0; height: 26px; border-top: 1px solid #d9dee8; color: #788398; font-size: 8px; padding-top: 8px; }
        .logo { width: 112px; margin-top: 3px; }
        .header-title { float: right; text-align: right; margin-top: 5px; }
        .header-title strong { display: block; color: #172033; font-size: 15px; }
        .header-title span { color: #e21b23; font-size: 9px; font-weight: bold; letter-spacing: .5px; }
        .page-number:after { content: counter(page); }
        h1 { margin: 0 0 4px; font-size: 20px; color: #172033; }
        .subtitle { color: #657086; margin-bottom: 18px; }
        .section { margin-top: 18px; page-break-inside: avoid; }
        .section-title { color: #e21b23; font-size: 11px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #e4e7ed; padding-bottom: 5px; margin-bottom: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { border: 1px solid #dfe3eb; padding: 8px; vertical-align: top; width: 50%; }
        .label { color: #788398; font-size: 8px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .value { color: #172033; font-weight: bold; }
        .text-box { border: 1px solid #dfe3eb; background: #f8f9fb; padding: 10px; border-radius: 4px; margin-bottom: 8px; }
        .follow-up { border-left: 3px solid #e21b23; padding: 8px 10px; background: #f8f9fb; margin-bottom: 9px; page-break-inside: avoid; }
        .follow-up-head { font-weight: bold; color: #172033; margin-bottom: 4px; }
        .follow-up-meta { color: #788398; font-size: 8px; margin-top: 5px; }
        .evidence { margin-bottom: 18px; page-break-inside: avoid; text-align: center; }
        .evidence img { max-width: 100%; max-height: 520px; border: 1px solid #ccd2dc; padding: 4px; }
        .evidence-caption { text-align: left; color: #59657a; font-size: 8px; margin-top: 4px; }
        .pdf-note { background: #fff5f5; border: 1px solid #f2c3c5; padding: 8px; margin-bottom: 6px; }
        .signatures { margin-top: 20px; width: 100%; table-layout: fixed; page-break-inside: avoid; }
        .signatures th, .signatures td { width: 50%; border: 1px solid #687286; text-align: center; vertical-align: middle; }
        .signatures th { height: 27px; padding: 4px 8px; border-bottom: 0; color: #25324a; font-size: 10px; font-weight: normal; }
        .signatures .space td { height: 94px; padding: 5px; border-top: 0; border-bottom: 0; }
        .signatures .names td { height: 28px; padding: 5px 8px; border-top: 0; border-bottom: 0; color: #172033; font-weight: bold; }
        .signatures .roles td { padding: 3px 8px 6px; border-top: 0; color: #16834b; font-size: 7px; font-weight: bold; }
        .signature-qr-frame { position: relative; display: block; width: 84px; height: 84px; margin: 0 auto; overflow: visible; line-height: 0; border: 1px solid #e4c693; border-radius: 6px; background: #fff; }
        .signature-qr { position: absolute; left: 5px; top: 5px; display: block; width: 74px; height: 74px; margin: 0; }
        .signature-qr-logo { position: absolute; left: 50%; top: 50%; display: block; width: 19px; height: 19px; padding: 0; transform: translate(-50%, -50%); border-radius: 50%; background: #fff; line-height: 0; }
        .signature-qr-logo img { position: absolute; left: 3px; top: 5px; display: block; width: 13px; height: auto; margin: 0; }
        .signature-placeholder { color: #777; font-size: 8px; line-height: 1.2; }
    </style>
</head>
<body>
<header>
    @if($logoDataUri)<img src="{{ $logoDataUri }}" class="logo" alt="LRT Jakarta">@endif
    <div class="header-title">
        <strong>Laporan Permintaan / Temuan GA</strong>
        <span>OPERASIONAL GENERAL AFFAIRS</span>
    </div>
</header>
<footer>
    <span>{{ $ticket->ticket_no }} - Dicetak {{ now()->format('d M Y H:i') }}</span>
    <span style="float:right">Halaman <span class="page-number"></span></span>
</footer>

<h1>{{ $ticket->title }}</h1>
<div class="subtitle">Dokumen penyelesaian tiket <strong>#{{ $ticket->ticket_no }}</strong></div>

<div class="section">
    <div class="section-title">Informasi Laporan</div>
    <table class="meta">
        <tr>
            <td><div class="label">Pelapor</div><div class="value">{{ $ticket->requester->name ?? '-' }}</div></td>
            <td><div class="label">Jenis Laporan</div><div class="value">{{ data_get($payload, 'report_type', '-') }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Tanggal Dibuat</div><div class="value">{{ optional($ticket->created_at)->format('d M Y H:i') ?: '-' }}</div></td>
            <td><div class="label">Tanggal Ditutup</div><div class="value">{{ optional($ticket->closed_at)->format('d M Y H:i') ?: '-' }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Lokasi</div><div class="value">{{ data_get($payload, 'location', '-') }}</div></td>
            <td><div class="label">Detail Lokasi</div><div class="value">{{ data_get($payload, 'detail_location', '-') }}</div></td>
        </tr>
        <tr>
            <td colspan="2"><div class="label">Kontak Pelapor</div><div class="value">{{ data_get($payload, 'reporter_phone', '-') }}</div></td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Uraian Permintaan / Temuan</div>
    <div class="text-box"><div class="label">Uraian</div>{{ data_get($payload, 'description', $ticket->description ?: '-') }}</div>
    <div class="text-box"><div class="label">Ekspektasi Tindak Lanjut</div>{{ data_get($payload, 'expected_action', '-') }}</div>
</div>

<div class="section">
    <div class="section-title">Tindak Lanjut Tim GA</div>
    @forelse($followUps as $index => $followUp)
        <div class="follow-up">
            <div class="follow-up-head">Tindak Lanjut {{ $index + 1 }}</div>
            <div>{{ data_get($followUp, 'notes', '-') }}</div>
            <div class="follow-up-meta">
                {{ data_get($followUp, 'followed_up_by_name', 'Tim GA') }} -
                {{ filled(data_get($followUp, 'followed_up_at')) ? \Carbon\Carbon::parse(data_get($followUp, 'followed_up_at'))->format('d M Y H:i') : '-' }}
                @if(data_get($followUp, 'evidence_file_name')) - Evidence: {{ data_get($followUp, 'evidence_file_name') }} @endif
            </div>
        </div>
    @empty
        <div class="text-box">Tindak lanjut penyelesaian tercatat pada riwayat tiket.</div>
    @endforelse
</div>

<table class="signatures">
    <tr><th>Pelapor / Requester</th><th>Tim General Affairs</th></tr>
    <tr class="space">
        <td>
            @if($requesterSignatureQr)
                <div class="signature-qr-frame">
                    <img class="signature-qr" src="{{ $requesterSignatureQr }}" alt="QR Portal Pelapor">
                    @if($esignLogoDataUri)<span class="signature-qr-logo"><img src="{{ $esignLogoDataUri }}" alt=""></span>@endif
                </div>
            @else
                <span class="signature-placeholder">QR Portal<br>belum tersedia</span>
            @endif
        </td>
        <td>
            @if($gaOfficerSignatureQr)
                <div class="signature-qr-frame">
                    <img class="signature-qr" src="{{ $gaOfficerSignatureQr }}" alt="QR Portal Tim GA">
                    @if($esignLogoDataUri)<span class="signature-qr-logo"><img src="{{ $esignLogoDataUri }}" alt=""></span>@endif
                </div>
            @else
                <span class="signature-placeholder">QR Portal<br>belum tersedia</span>
            @endif
        </td>
    </tr>
    <tr class="names"><td>{{ $ticket->requester->name ?? '-' }}</td><td>{{ data_get($followUps->last(), 'followed_up_by_name', 'Tim General Affairs') }}</td></tr>
    <tr class="roles"><td>Terverifikasi melalui LRTJ Portal</td><td>Terverifikasi melalui LRTJ Portal</td></tr>
</table>

<div class="section" style="page-break-before: {{ $imageEvidence->isNotEmpty() || $pdfEvidence->isNotEmpty() ? 'always' : 'auto' }};">
    <div class="section-title">Evidence</div>
    @foreach($imageEvidence as $index => $item)
        <div class="evidence">
            <img src="{{ $item['data_uri'] }}" alt="Evidence {{ $index + 1 }}">
            <div class="evidence-caption">
                Evidence {{ $index + 1 }}: {{ $item['attachment']->file_name }}
                ({{ $item['attachment']->attachment_type === 'ga_follow_up_evidence' ? 'Tindak lanjut GA' : 'Laporan awal' }})
            </div>
        </div>
    @endforeach
    @foreach($pdfEvidence as $attachment)
        <div class="pdf-note">
            Evidence PDF <strong>{{ $attachment->file_name }}</strong> disertakan utuh pada halaman lampiran setelah laporan ini.
        </div>
    @endforeach
    @if($imageEvidence->isEmpty() && $pdfEvidence->isEmpty())
        <div class="text-box">Tidak ada file evidence yang tersedia.</div>
    @endif
</div>
</body>
</html>
