<?php

namespace App\Jobs;

use App\Models\Master\Ticket;
use App\Services\LrtjSpaceMobileNotificationService;
use App\Support\GaSubmissionDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyAtkRtkTicketCreated implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $ticketId, public readonly string $ticketNo)
    {
        $this->onConnection(GaSubmissionDelivery::CONNECTION)
            ->onQueue(GaSubmissionDelivery::QUEUE)
            ->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 60];
    }

    public function handle(LrtjSpaceMobileNotificationService $notifications): void
    {
        $ticket = Ticket::where('ticket_no', $this->ticketNo)->find($this->ticketId);
        if ($ticket && data_get($ticket->payload, 'request_type') === 'atk_rtk') {
            $notifications->notifyTicketCreated($ticket, background: true);
        }
    }
}
