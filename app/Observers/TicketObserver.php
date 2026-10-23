<?php

namespace App\Observers;

use App\Models\Master\Status;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\LrtjSpaceMobileNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class TicketObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Ticket $ticket): void
    {
        app(LrtjSpaceMobileNotificationService::class)
            ->notifyTicketCreated($ticket->fresh(['requester', 'status']));
    }

    public function updated(Ticket $ticket): void
    {
        $notifications = app(LrtjSpaceMobileNotificationService::class);

        if ($ticket->wasChanged(['status_id', 'payload'])) {
            $notifications->notifyTicketStatusChanged(
                $ticket->fresh(['requester', 'status']),
                auth()->user() instanceof User ? auth()->user() : null,
                $this->previousStatus($ticket),
            );
        }

        if ($ticket->wasChanged('assigned_user_id') && $ticket->assigned_user_id) {
            $notifications->notifyTicketAssigned(
                $ticket->fresh(['status']),
                $ticket->assignedUser()->first(),
            );
        }
    }

    private function previousStatus(Ticket $ticket): ?string
    {
        $payload = $ticket->getRawOriginal('payload');
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        $workflowStatus = is_array($payload) ? data_get($payload, 'workflow_status') : null;
        if (filled($workflowStatus)) {
            return (string) $workflowStatus;
        }

        $statusId = $ticket->getRawOriginal('status_id');

        return $statusId ? Status::query()->find($statusId)?->name : null;
    }
}
