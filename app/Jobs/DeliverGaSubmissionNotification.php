<?php

namespace App\Jobs;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\LrtjSpaceMobileNotificationService;
use App\Support\GaSubmissionDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverGaSubmissionNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $ticketId,
        public readonly int $recipientId,
        public readonly array $payload,
    ) {
        $this->onConnection(GaSubmissionDelivery::CONNECTION)
            ->onQueue(GaSubmissionDelivery::QUEUE)
            ->afterCommit();
    }

    public function handle(LrtjSpaceMobileNotificationService $notifications): void
    {
        $ticket = Ticket::find($this->ticketId);
        $recipient = User::find($this->recipientId);
        if (! $ticket || $ticket->ticket_no !== $this->payload['ticket_no'] || ! $recipient?->email) {
            return;
        }

        if (($approvalId = $this->payload['approval_id'] ?? null) !== null) {
            $approval = $ticket->approvals()->where('status', 'Pending')->find($approvalId);
            if (! $approval || (! isset($this->payload['recipient_scope']) && (int) $approval->approver_id !== $this->recipientId)) {
                return;
            }
        }

        $notifications->deliverQueued($ticket, $recipient, array_replace($this->payload, [
            'recipient_email' => $recipient->email,
        ]));
    }
}
