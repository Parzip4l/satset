@php
    $fmtDate = function ($date) {
        if (blank($date)) {
            return '-';
        }

        try {
            $value = \Carbon\Carbon::parse($date);
        } catch (\Throwable $e) {
            return $date;
        }

        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $days[$value->dayOfWeek].' '.$value->format('d').' '.$months[(int) $value->format('n')].' '.$value->format('Y');
    };

    $fmtTime = function ($time) {
        if (blank($time)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($time)->format('H.i');
        } catch (\Throwable $e) {
            return $time;
        }
    };

    $text = fn ($value, $fallback = '-') => filled($value) ? $value : $fallback;
    $requesterDivision = $ticket->requester?->division?->name;
    $departmentName = $ticket->department?->name ?: $ticket->assignedDepartment?->name;
    $unitName = $text(data_get($payload, 'organizer_unit'), $requesterDivision ?: $departmentName);
    $consumptionType = strtolower((string) data_get($payload, 'consumption_type', ''));
    $needsOther = ! \Illuminate\Support\Str::contains($consumptionType, ['snack', 'makan siang', 'makan malam']);
    $bumOfficer = $closedHistory?->user?->name ?: data_get($payload, 'bum_officer_name', 'Bagian Umum');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Form Permintaan Kebutuhan Konsumsi {{ $ticket->ticket_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 11mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f5;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.28;
        }

        .toolbar {
            max-width: 210mm;
            margin: 18px auto;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .toolbar a,
        .toolbar button {
            border: 1px solid #b8c2cc;
            background: #fff;
            color: #1f2937;
            border-radius: 6px;
            padding: 9px 14px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar .primary {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 18px;
            background: #fff;
            padding: 11mm 10mm 12mm;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .15);
        }

        .doc-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .doc-table th,
        .doc-table td {
            border: 1px solid #111;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .header-table {
            color: #7b7b7b;
        }

        .header-table td {
            border: 1px dotted #777;
            padding: 3px 8px;
        }

        .header-top-cell {
            height: 72px;
        }

        .logo-cell {
            text-align: center;
        }

        .logo-cell img {
            max-width: 230px;
            max-height: 60px;
        }

        .title-cell {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
        }

        .meta-label {
            font-size: 13px;
            font-weight: 700;
        }

        .meta-value {
            font-size: 13px;
            font-weight: 500;
        }

        .header-meta {
            margin-bottom: 24mm;
        }

        .main-title {
            height: 40px;
            background: #e5e5e5;
            text-align: center;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .label-col {
            width: 24%;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .value-col {
            width: 76%;
            font-size: 14px;
            font-weight: 600;
        }

        .request-table td {
            height: 30px;
        }

        .needs-row td {
            height: 76px;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 56px;
            row-gap: 12px;
            padding: 6px 18px;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .checkline {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 20px;
            white-space: nowrap;
        }

        .box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 16px;
            width: 16px;
            height: 16px;
            border: 1.5px solid #111;
            font-size: 13px;
            line-height: 1;
            font-weight: 700;
        }

        .evidence {
            margin: 3px 0 25px 2px;
            font-size: 14px;
        }

        .evidence-title {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .evidence ol {
            margin: 0;
            padding-left: 26px;
        }

        .evidence li {
            padding-left: 4px;
        }

        .sign-table th {
            height: 30px;
            background: #e5e5e5;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
        }

        .sign-role {
            height: 30px;
            background: #e5e5e5;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
        }

        .sign-space {
            height: 78px;
        }

        .sign-name {
            height: 30px;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('ticket.show', $ticket) }}">Kembali</a>
        <a href="{{ route('ticket.consumption.form', [$ticket, 'download' => 1]) }}">Download HTML</a>
        <button type="button" class="primary" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="page">
        <table class="doc-table header-table header-meta">
            <colgroup>
                <col style="width:19%">
                <col style="width:29%">
                <col style="width:24%">
                <col style="width:28%">
            </colgroup>
            <tr>
                <td colspan="2" class="logo-cell header-top-cell">
                    <img src="{{ asset('logo-lrtj.png') }}" alt="LRT Jakarta">
                </td>
                <td colspan="2" class="title-cell header-top-cell">Permintaan Kebutuhan Konsumsi</td>
            </tr>
            <tr>
                <td class="meta-label">Nomor Dokumen</td>
                <td class="meta-value">LRTJ-FR-BUM-003</td>
                <td class="meta-label">Dokumen Departemen</td>
                <td class="meta-value">BUM</td>
            </tr>
            <tr>
                <td class="meta-label">Tipe Dokumen</td>
                <td class="meta-value">Formulir</td>
                <td class="meta-label">Dokumen Divisi</td>
                <td class="meta-value">HCGA</td>
            </tr>
            <tr>
                <td class="meta-label">Nomor Revisi</td>
                <td class="meta-value">02</td>
                <td class="meta-label">Dokumen Direktorat</td>
                <td class="meta-value">DKB</td>
            </tr>
            <tr>
                <td class="meta-label">Tanggal Efektif</td>
                <td class="meta-value">14-Februari-2025</td>
                <td class="meta-label">Halaman</td>
                <td class="meta-value">Page 1 of 1</td>
            </tr>
        </table>

        <table class="doc-table request-table">
            <tr>
                <td colspan="2" class="main-title">PERMINTAAN KEBUTUHAN KONSUMSI</td>
            </tr>
            <tr>
                <td class="label-col">Nama</td>
                <td class="value-col">{{ $text($ticket->requester?->name) }}</td>
            </tr>
            <tr>
                <td class="label-col">Divisi / Departemen</td>
                <td class="value-col">{{ $text($unitName) }}</td>
            </tr>
            <tr>
                <td class="label-col">Hari / Tanggal</td>
                <td class="value-col">{{ $fmtDate(data_get($payload, 'event_date')) }}</td>
            </tr>
            <tr>
                <td class="label-col">Waktu</td>
                <td class="value-col">
                    {{ $fmtTime(data_get($payload, 'start_time')) }}
                    @if(filled(data_get($payload, 'end_time')))
                        - {{ $fmtTime(data_get($payload, 'end_time')) }}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-col">Agenda</td>
                <td class="value-col">{{ $text(data_get($payload, 'activity_name')) }}</td>
            </tr>
            <tr>
                <td class="label-col">Jumlah Peserta</td>
                <td class="value-col">{{ $text(data_get($payload, 'participant_count')) }}</td>
            </tr>
            <tr class="needs-row">
                <td class="label-col">Kebutuhan<br>Konsumsi</td>
                <td class="value-col">
                    <div class="checkbox-grid">
                        <span class="checkline"><span class="box">{{ \Illuminate\Support\Str::contains($consumptionType, 'snack') ? 'V' : '' }}</span> Snack</span>
                        <span class="checkline"><span class="box">{{ \Illuminate\Support\Str::contains($consumptionType, 'makan malam') ? 'V' : '' }}</span> Makan Malam</span>
                        <span class="checkline"><span class="box">{{ \Illuminate\Support\Str::contains($consumptionType, 'makan siang') ? 'V' : '' }}</span> Makan Siang</span>
                        <span class="checkline"><span class="box">{{ $needsOther ? 'V' : '' }}</span> Lainnya: {{ $needsOther ? $text(data_get($payload, 'consumption_type')) : '' }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <div class="evidence">
            <div class="evidence-title">Bukti yang harus dilampirkan setelah pelaksanaan :</div>
            <ol>
                <li>MoM / Materi acara</li>
                <li>Daftar hadir (absensi)</li>
                <li>Foto acara</li>
            </ol>
        </div>

        <table class="doc-table sign-table">
            <tr>
                <th>DIBUAT</th>
                <th>DISETUJUI</th>
                <th>DIPERIKSA</th>
            </tr>
            <tr>
                <td class="sign-role">PEMOHON</td>
                <td class="sign-role">KADIV PEMOHON</td>
                <td class="sign-role">BAGIAN UMUM</td>
            </tr>
            <tr>
                <td class="sign-space"></td>
                <td class="sign-space"></td>
                <td class="sign-space"></td>
            </tr>
            <tr>
                <td class="sign-name">{{ $text(data_get($payload, 'reporter_name'), $ticket->requester?->name ?: '-') }}</td>
                <td class="sign-name">{{ $text(data_get($payload, 'supervisor_name')) }}</td>
                <td class="sign-name">{{ $text($bumOfficer) }}</td>
            </tr>
        </table>
    </main>
</body>
</html>
