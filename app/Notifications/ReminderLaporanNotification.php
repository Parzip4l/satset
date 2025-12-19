<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Reports; // pastikan model report di-import kalau mau type hint

class ReminderLaporanNotification extends Notification
{
    use Queueable;

    protected $report; // 🔹 property untuk menampung data laporan

    /**
     * Create a new notification instance.
     */
    public function __construct($report)
    {
        $this->report = $report; // 🔹 simpan report ke property
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reminder Tindak Lanjut Laporan')
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Laporan berikut masih belum ditindaklanjuti:')
            ->line('**Judul Laporan:** ' . $this->report->judul)
            ->line('**Tanggal Laporan:** ' . $this->report->created_at->format('d-m-Y'))
            ->action('Lihat Laporan', route('laporan.show', hashid_encode($this->report->id)))
            ->line('Mohon segera melakukan tindak lanjut atas laporan tersebut.')
            ->line('')
            ->line('Terima kasih,')
            ->salutation('Tim SHE LRT Jakarta'); // ✅ Regards Tim SHE LRT Jakarta
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'judul'     => $this->report->judul,
        ];
    }
}
