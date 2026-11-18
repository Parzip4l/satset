<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $reference ? 'SPPD '.$reference : 'Pratinjau SPPD' }}</title>
    <style>
        @page { margin: 25pt 32pt; }
        body { color: #141414; font: 9pt/1.2 'DejaVu Sans', sans-serif; }
        .logo { width: 95pt; max-height: 29pt; }
        h1 { font-size: 12pt; margin: 8pt 0 2pt; text-align: center; }
        .reference { text-align: center; margin: 0 0 8pt; }
        .draft { font-weight: bold; color: #616161; }
        h2 { font-size: 9pt; margin: 7pt 0 4pt; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .profile td { padding: 1pt 4pt 1pt 0; vertical-align: top; word-wrap: break-word; }
        .choice { display: inline-block; min-width: 12pt; }
        .categories div { margin: 1pt 0; }
        .grid td, .grid th { padding: 3pt 4pt; border: .5pt solid #555; vertical-align: middle; word-wrap: break-word; }
        .grid thead th { background: #eaeaea; text-align: center; }
        .details td { vertical-align: top; text-align: center; }
        .details .purpose { text-align: left; }
        .budget { font-size: 8pt; table-layout: auto; }
        .budget th, .budget td { padding-top: 1.5pt; padding-bottom: 1.5pt; }
        .budget td:first-child { text-align: center; }
        .budget th[scope="row"] { text-align: left; }
        .amount { text-align: right; }
        .notes { font-size: 8pt; word-wrap: break-word; }
        .sppd-rate, .trip-extra { margin: 3pt 0; font-size: 8pt; word-wrap: break-word; }
        .trip-extra td { padding: 1pt 4pt 1pt 0; vertical-align: top; word-wrap: break-word; }
        .closing { page-break-inside: avoid; }
        .closing-reference { font-weight: normal; }
        .signatures { page-break-inside: avoid; }
        .signatures td { text-align: center; padding: 1pt 4pt; word-wrap: break-word; vertical-align: top; }
        .signature-role { font-size: 8pt; }
        .signatures img { width: 60pt; height: 60pt; }
        .signature-name { font-size: 8pt; font-weight: bold; }
        .signature-meta { font-size: 7pt; }
        .signature-space { height: 45pt; }
    </style>
</head>
<body>
    <img class="logo" src="{{ $logo }}" alt="LRT Jakarta">
    <h1>PENGAJUAN SURAT PERMOHONAN PERJALANAN DINAS (SPPD)</h1>
    <div @class(['reference', 'draft' => $reference === null])>{{ $reference ?? 'DRAF · PRATINJAU' }}</div>

    <h2>A. PELAKSANA PERJALANAN DINAS</h2>
    <table class="profile">
        <tr>
            <td style="width: 12%">Nama</td><td style="width: 44%">: {{ $sppd['profile']['name'] }}</td>
            <td style="width: 15%">Divisi</td><td>: {{ $sppd['profile']['division'] }}</td>
        </tr>
        <tr>
            <td>NIK</td><td>: {{ $sppd['profile']['nik'] }}</td>
            <td>Direktorat</td><td>: {{ $sppd['profile']['directorate'] }}</td>
        </tr>
        <tr>
            <td>Jabatan</td><td>: {{ $sppd['profile']['position'] }}</td>
            <td>Job Level</td><td>: {{ $sppd['profile']['job_level'] ?? '-' }}</td>
        </tr>
    </table>

    <h2>B. KATEGORI PERJALANAN DINAS</h2>
    <div class="categories">
        @foreach(\App\Support\Sppd::CATEGORIES as $key => $label)
            <div><span class="choice">{{ $sppd['category'] === $key ? '[×]' : '[ ]' }}</span> {{ $label }}</div>
        @endforeach
    </div>

    <h2>C. DETAIL PERJALANAN DINAS</h2>
    <table class="grid details">
        <thead>
            <tr><th colspan="2">Tujuan</th><th colspan="2">Periode Perjalanan</th><th rowspan="2" style="width: 9%">Jumlah Hari</th><th rowspan="2" style="width: 31%">Keperluan Perjalanan Dinas</th></tr>
            <tr><th>Dari</th><th>Ke</th><th>Tanggal Berangkat</th><th>Tanggal Kembali</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $sppd['origin'] }}</td><td>{{ $sppd['destination'] }}</td>
                <td>{{ \Carbon\CarbonImmutable::parse($sppd['depart_on'])->format('d/m/Y') }}</td>
                <td>{{ \Carbon\CarbonImmutable::parse($sppd['return_on'])->format('d/m/Y') }}</td>
                <td>{{ $sppd['days'] }}</td><td class="purpose">{{ $sppd['purpose'] }}</td>
            </tr>
        </tbody>
    </table>
    @php
        $tripDetails = array_filter([
            'Jenis Perjalanan Luar Negeri' => \App\Support\Sppd::INTERNATIONAL_PURPOSES[$sppd['international_purpose'] ?? null] ?? null,
            'Prakiraan suhu terendah' => isset($sppd['forecast_temperature']) ? $sppd['forecast_temperature'].'°C' : null,
            ...array_combine(array_values(\App\Support\Sppd::ATTACHMENTS), array_map(
                fn ($key) => data_get($sppd, "attachments.{$key}.name"), array_keys(\App\Support\Sppd::ATTACHMENTS),
            )),
        ]);
    @endphp
    @if($tripDetails)
        <table class="trip-extra">
            @foreach(array_chunk($tripDetails, 2, true) as $details)
                <tr>
                    @foreach($details as $label => $value)
                        <td @if(count($details) === 1) colspan="2" @else style="width: 50%" @endif><strong>{{ $label }}</strong> {{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    <h2>D. RENCANA ANGGARAN BIAYA PERJALANAN DINAS</h2>
    @include('sppd.budget', ['tableClass' => 'grid budget'])

    <div class="closing">
    <h2>E. CATATAN TAMBAHAN</h2>
    <div class="notes">
        @include('sppd.notes', ['forPrint' => true])
        @if(blank($sppd['notes']) && blank(data_get($sppd, 'bank.account')))
            -
        @endif
    </div>

    <h2>F. TANDA TANGAN DAN PERSETUJUAN <span class="closing-reference">{{ $reference ?? '· Draf pratinjau' }}</span></h2>
    <table class="signatures">
        <tr>
            @foreach($signatures as $signature)
                <td>{{ $signature['action'] }}<div class="signature-role">{{ $signature['role'] }}</div></td>
            @endforeach
        </tr>
        <tr>
            @foreach($signatures as $signature)
                @if($reference)
                    <td><a href="{{ $signature['url'] }}"><img src="{{ $signature['qr'] }}" alt="QR verifikasi {{ $signature['role'] }}"></a></td>
                @else
                    <td class="signature-space"></td>
                @endif
            @endforeach
        </tr>
        @if($reference)
            <tr>
                @foreach($signatures as $signature)
                    <td>
                        <div class="signature-name">{{ $signature['name'] }}</div>
                        <div class="signature-meta">{{ $signature['position'] }}</div>
                        <div class="signature-meta">{{ $signature['date'] }} WIB</div>
                    </td>
                @endforeach
            </tr>
        @endif
    </table>
    </div>
</body>
</html>
