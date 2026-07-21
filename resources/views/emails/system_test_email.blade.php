<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email SatSet</title>
</head>
<body style="margin:0; padding:24px; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px; margin:0 auto; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #e5e7eb;">
        <tr>
            <td style="background:linear-gradient(135deg, #0e7490, #0f5cc0); padding:28px 32px; color:#ffffff;">
                <div style="font-size:13px; letter-spacing:.08em; text-transform:uppercase; opacity:.9;">SatSet System</div>
                <h1 style="margin:10px 0 6px; font-size:24px; line-height:1.3;">Test Email Berhasil Dikirim</h1>
                <p style="margin:0; font-size:14px; opacity:.95;">Email ini dikirim sebagai uji koneksi SMTP aplikasi SatSet.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 32px;">
                <p style="margin:0 0 16px; font-size:15px;">Halo <strong>{{ $user->name ?? 'Pengguna' }}</strong>,</p>
                <p style="margin:0 0 18px; font-size:14px; line-height:1.7;">
                    Jika Anda menerima email ini, berarti konfigurasi pengiriman email pada aplikasi SatSet sudah berjalan.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom:20px;">
                    <tr>
                        <td style="padding:12px 14px; background:#f8fafc; border:1px solid #e5e7eb; width:40%; font-weight:700;">Penerima</td>
                        <td style="padding:12px 14px; background:#ffffff; border:1px solid #e5e7eb;">{{ $user->email ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 14px; background:#f8fafc; border:1px solid #e5e7eb; font-weight:700;">Waktu Kirim</td>
                        <td style="padding:12px 14px; background:#ffffff; border:1px solid #e5e7eb;">{{ $sentAt }}</td>
                    </tr>
                </table>

                <p style="margin:0; font-size:14px; color:#6b7280;">
                    Email ini dikirim otomatis dari tombol test email di aplikasi.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
