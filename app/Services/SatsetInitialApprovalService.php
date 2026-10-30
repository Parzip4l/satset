<?php

namespace App\Services;

use App\Models\Master\Approval;
use App\Models\Master\Ticket;
use Illuminate\Validation\ValidationException;

class SatsetInitialApprovalService
{
    public function __construct(
        private readonly LrtjSpaceApprovalResolverService $approvalResolver,
        private readonly LrtjSpaceMobileNotificationService $notifications,
    ) {}

    public function create(Ticket $ticket): ?Approval
    {
        if (data_get($ticket->payload, 'workflow_status') !== 'WAITING_MANAGER_APPROVAL') {
            return null;
        }

        $ticket->loadMissing('requester');

        $approver = $this->approvalResolver->resolveFirstApprover($ticket);

        if ((string) $approver->getKey() === (string) $ticket->requester_id) {
            throw ValidationException::withMessages([
                'approval_resolver' => 'Portal mengembalikan approver yang sama dengan pemohon untuk '.$ticket->ticket_no.'. Periksa reporting line atau Authority Matrix SatSet di Portal.',
            ]);
        }

        return $this->persistApproval($ticket, $approver->id, 1);
    }

    private function persistApproval(Ticket $ticket, int|string $approverId, int $level): Approval
    {
        $approval = Approval::firstOrCreate([
            'request_id' => $ticket->id,
            'level' => $level,
        ], [
            'approver_id' => $approverId,
            'status' => 'Pending',
        ]);

        if ($approval->wasRecentlyCreated) {
            $this->notifications->notifyApprovalRequested($ticket, $approval);
        }

        return $approval;
    }

}
