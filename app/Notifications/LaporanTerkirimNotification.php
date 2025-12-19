<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LaporanTerkirimNotification extends Notification
{
    use Queueable;

    protected $nomorLaporan;

    public function __construct($nomorLaporan)
    {
        $this->nomorLaporan = $nomorLaporan;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('📨 Terima Kasih atas Laporan Anda')
            ->markdown('emails.laporan.submit', [
                'nama' => $notifiable->name,
                'nomorLaporan' => $this->nomorLaporan,
            ]);
    }

}
