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
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f5;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.35;
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
            padding: 0;
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

        .header-table td {
            padding: 4px 6px;
            height: 26px;
        }

        .logo-cell {
            width: 22%;
            text-align: center;
        }

        .logo-cell img {
            max-width: 128px;
            max-height: 44px;
        }

        .title-cell {
            width: 28%;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
        }

        .meta-label {
            width: 14%;
            font-size: 10px;
            font-weight: 700;
        }

        .meta-value {
            width: 11%;
            font-size: 10px;
        }

        .form-title {
            margin: 24px 0 16px;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .content {
            padding: 0 12mm 12mm;
        }

        .label-col {
            width: 30%;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sep-col {
            width: 3%;
            text-align: center;
            font-weight: 700;
        }

        .value-col {
            width: 67%;
            min-height: 28px;
        }

        .request-table td {
            height: 32px;
        }

        .needs-row td {
            height: 58px;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px 18px;
        }

        .checkline {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 20px;
            white-space: nowrap;
        }

        .box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            border: 1.5px solid #111;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
        }

        .evidence {
            margin: 14px 0 20px;
        }

        .evidence-title {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .evidence ol {
            margin: 0;
            padding-left: 19px;
        }

        .sign-table th {
            text-align: center;
            font-size: 12px;
            height: 28px;
        }

        .sign-role {
            height: 26px;
            text-align: center;
            font-weight: 700;
        }

        .sign-space {
            height: 78px;
        }

        .sign-name {
            height: 30px;
            text-align: center;
            font-weight: 700;
        }

        .ticket-note {
            margin-top: 12px;
            color: #4b5563;
            font-size: 10px;
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
                box-shadow: none;
            }

            .content {
                padding-bottom: 0;
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
        <table class="doc-table header-table">
            <tr>
                <td rowspan="4" class="logo-cell">
                    <img src="{{ asset('logo-lrtj.png') }}" alt="LRT Jakarta">
                </td>
                <td rowspan="4" class="title-cell">Permintaan Kebutuhan Konsumsi</td>
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
                <td class="meta-value">1 dari 1</td>
            </tr>
        </table>

        <section class="content">
            <h1 class="form-title">PERMINTAAN KEBUTUHAN KONSUMSI</h1>

            <table class="doc-table request-table">
                <tr>
                    <td class="label-col">Nama</td>
                    <td class="sep-col">:</td>
                    <td class="value-col">{{ $text($ticket->requester?->name) }}</td>
                </tr>
                <tr>
                    <td class="label-col">Divisi / Departemen</td>
                    <td class="sep-col">:</td>
                    <td class="value-col">{{ $text($unitName) }}</td>
                </tr>
                <tr>
                    <td class="label-col">Hari / Tanggal</td>
                    <td class="sep-col">:</td>
                    <td class="value-col">{{ $fmtDate(data_get($payload, 'event_date')) }}</td>
                </tr>
                <tr>
                    <td class="label-col">Waktu</td>
                    <td class="sep-col">:</td>
                    <td class="value-col">
                        {{ $fmtTime(data_get($payload, 'start_time')) }}
                        @if(filled(data_get($payload, 'end_time')))
                            - {{ $fmtTime(data_get($payload, 'end_time')) }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label-col">Agenda</td>
                    <td class="sep-col">:</td>
                    <td class="value-col">{{ $text(data_get($payload, 'activity_name')) }}</td>
                </tr>
                <tr>
                    <td class="label-col">Jumlah Peserta</td>
                    <td class="sep-col">:</td>
                    <td class="value-col">{{ $text(data_get($payload, 'participant_count')) }}</td>
                </tr>
                <tr class="needs-row">
                    <td class="label-col">Kebutuhan Konsumsi</td>
                    <td class="sep-col">:</td>
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

            <div class="ticket-note">
                Dibuat otomatis dari SatSet untuk ticket {{ $ticket->ticket_no }}.
                Lokasi kegiatan: {{ $text(data_get($payload, 'location')) }}.
                Dicetak pada {{ now()->format('d/m/Y H:i') }}.
            </div>
        </section>
    </main>
</body>
</html>
