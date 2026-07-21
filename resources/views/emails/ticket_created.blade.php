<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ data_get($ticket->payload, 'request_label', 'Ticket') }}</title>
</head>
<body style="margin:0; padding:24px; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    @php
        $requestLabel = data_get($ticket->payload, 'request_label', 'Ticket');
        $statusName = $ticket->status->name ?? 'Open';
        $requestType = data_get($ticket->payload, 'request_type', 'general');
        $detailPayload = collect($ticket->payload ?? [])
            ->except(['request_type', 'request_label', 'submitted_at', 'workflow'])
            ->filter(fn ($value) => filled($value) && !is_array($value))
            ->take(6);
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px; margin:0 auto; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #e5e7eb;">
        <tr>
            <td style="background:linear-gradient(135deg, #0e7490, #0f5cc0); padding:28px 32px; color:#ffffff;">
                <div style="font-size:13px; letter-spacing:.08em; text-transform:uppercase; opacity:.9;">SatSet Notification</div>
                <h1 style="margin:10px 0 6px; font-size:24px; line-height:1.3;">{{ $requestLabel }} #{{ $ticket->ticket_no }}</h1>
                <p style="margin:0; font-size:14px; opacity:.95;">
                    @if($actionType === 'status_updated')
                        Status request Anda telah diperbarui.
                    @elseif($recipientType === 'requester')
                        Pengajuan Anda berhasil kami terima dan sudah tercatat di sistem.
                    @else
                        Ada request baru yang masuk ke unit Anda dan memerlukan tindak lanjut.
                    @endif
                </p>
            </td>
        </tr>

        <tr>
            <td style="padding:28px 32px;">
                <p style="margin:0 0 18px; font-size:15px;">
                    Halo
                    <strong>
                        @if($recipientType === 'requester')
                            {{ $ticket->requester->name ?? 'Pengguna' }}
                        @else
                            Tim Terkait
                        @endif
                    </strong>,
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom:22px;">
                    <tr>
                        <td style="padding:12px 14px; background:#f8fafc; border:1px solid #e5e7eb; width:40%; font-weight:700;">Nomor</td>
                        <td style="padding:12px 14px; background:#ffffff; border:1px solid #e5e7eb;">{{ $ticket->ticket_no }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px; background:#f8fafc; border:1px solid #e5e7eb; font-weight:700;">Jenis</td>
                        <td style="padding:12px 14px; background:#ffffff; border:1px solid #e5e7eb;">{{ $requestLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px; background:#f8fafc; border:1px solid #e5e7eb; font-weight:700;">Judul</td>
                        <td style="padding:12px 14px; background:#ffffff; border:1px solid #e5e7eb;">{{ $ticket->title }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px; background:#f8fafc; border:1px solid #e5e7eb; font-weight:700;">Status</td>
                        <td style="padding:12px 14px; background:#ffffff; border:1px solid #e5e7eb;">{{ $statusName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px; background:#f8fafc; border:1px solid #e5e7eb; font-weight:700;">Pemohon</td>
                        <td style="padding:12px 14px; background:#ffffff; border:1px solid #e5e7eb;">{{ $ticket->requester->name ?? '-' }}</td>
                    </tr>
                </table>

                @if($ticket->description)
                    <div style="margin-bottom:22px;">
                        <div style="font-weight:700; margin-bottom:8px;">Deskripsi</div>
                        <div style="padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; line-height:1.6;">
                            {!! nl2br(e($ticket->description)) !!}
                        </div>
                    </div>
                @endif

                @if($requestType !== 'general' && $detailPayload->isNotEmpty())
                    <div style="margin-bottom:22px;">
                        <div style="font-weight:700; margin-bottom:8px;">Ringkasan Detail</div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                            @foreach($detailPayload as $key => $value)
                                <tr>
                                    <td style="padding:10px 12px; background:#f8fafc; border:1px solid #e5e7eb; width:40%; font-weight:700; text-transform:capitalize;">{{ str_replace('_', ' ', $key) }}</td>
                                    <td style="padding:10px 12px; background:#ffffff; border:1px solid #e5e7eb;">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif

                <p style="margin:0; font-size:14px; color:#6b7280;">
                    Email ini dikirim otomatis oleh SatSet System.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
