<?php 

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Master\Ticket;

class TicketCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public $recipientType;

    public function __construct(Ticket $ticket, string $recipientType = 'requester', string $actionType = 'created')
    {
        $this->ticket = $ticket;
        $this->recipientType = $recipientType;
        $this->actionType = $actionType;
    }

    public function build()
    {
        $subject = $this->actionType === 'status_updated'
            ? "Update Status Ticket #{$this->ticket->ticket_no}"
            : ($this->recipientType === 'requester' 
                ? "Konfirmasi Ticket #{$this->ticket->ticket_no} Anda" 
                : "Ticket Baru #{$this->ticket->ticket_no} Masuk ke Departemen Anda");

        return $this->subject($subject)
            ->view('emails.ticket_created')
            ->with([
                'ticket' => $this->ticket,
                'recipientType' => $this->recipientType,
                'actionType' => $this->actionType,
            ]);
    }
}
