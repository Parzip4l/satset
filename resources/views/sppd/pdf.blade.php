<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SPPD {{ $reference }}</title>
    <style>
        @page { margin: 28pt 32pt; }
        body { color: #141414; font: 9pt/1.2 'DejaVu Sans', sans-serif; }
        .logo { width: 95pt; max-height: 29pt; }
        h1 { font-size: 12pt; margin: 8pt 0 2pt; text-align: center; }
        .reference { text-align: center; margin: 0 0 8pt; }
        h2 { font-size: 9pt; margin: 7pt 0 4pt; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .profile td { padding: 2pt 4pt 2pt 0; vertical-align: top; word-wrap: break-word; }
        .choice { display: inline-block; min-width: 12pt; }
        .categories div { margin: 1pt 0; }
        .grid td, .grid th { padding: 3pt 4pt; border: .5pt solid #555; vertical-align: middle; word-wrap: break-word; }
        .grid thead th { background: #eaeaea; text-align: center; }
        .details td { vertical-align: top; text-align: center; }
        .details .purpose { text-align: left; }
        .budget { font-size: 8pt; table-layout: auto; }
        .budget th, .budget td { padding-top: 2pt; padding-bottom: 2pt; }
        .budget td:first-child { text-align: center; }
        .amount { text-align: right; }
        .notes, .footnote { font-size: 8pt; }
        .footnote { margin-top: 3pt; }
        .notes { word-wrap: break-word; }
        .signatures { margin-top: 12pt; page-break-inside: avoid; }
        .signatures td { text-align: center; padding: 1pt 4pt; word-wrap: break-word; vertical-align: top; }
        .signature-role { font-size: 8pt; }
        .signatures img { width: 66pt; height: 66pt; }
        .signature-name { font-size: 8pt; font-weight: bold; }
        .signature-meta { font-size: 7pt; }
    </style>
</head>
<body>
    <img class="logo" src="{{ $logo }}" alt="LRT Jakarta">
    <h1>PENGAJUAN SURAT PERMOHONAN PERJALANAN DINAS (SPPD)</h1>
    <div class="reference">{{ $reference }}</div>

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
        <tr><td>Jabatan</td><td colspan="3">: {{ $sppd['profile']['position'] }}</td></tr>
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

    <h2>D. RENCANA ANGGARAN BIAYA PERJALANAN DINAS</h2>
    @include('sppd.budget', ['tableClass' => 'grid budget'])
    <div class="footnote">* Bila Ada</div>

    <h2>E. CATATAN TAMBAHAN</h2>
    <div class="notes">
        @include('sppd.notes', ['forPrint' => true])
        @if(blank($sppd['notes']) && blank(data_get($sppd, 'bank.account')))
            -
        @endif
    </div>

    <table class="signatures">
        <tr>
            @foreach($signatures as $signature)
                <td>{{ $signature['action'] }}<div class="signature-role">{{ $signature['role'] }}</div></td>
            @endforeach
        </tr>
        <tr>
            @foreach($signatures as $signature)
                <td><a href="{{ $signature['url'] }}"><img src="{{ $signature['qr'] }}" alt="QR verifikasi {{ $signature['role'] }}"></a></td>
            @endforeach
        </tr>
        <tr>
            @foreach($signatures as $signature)
                <td>
                    <div class="signature-name">{{ $signature['name'] }}</div>
                    <div class="signature-meta">{{ $signature['position'] }}</div>
                    <div class="signature-meta">{{ $signature['date'] }} WIB</div>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
