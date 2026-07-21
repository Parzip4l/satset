<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SystemTestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $sentAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->sentAt = now()->format('d M Y H:i:s');
    }

    public function build()
    {
        return $this->subject('Test Email SatSet')
            ->view('emails.system_test_email')
            ->with([
                'user' => $this->user,
                'sentAt' => $this->sentAt,
            ]);
    }
}
