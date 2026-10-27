<?php

namespace App\Services;

use App\Models\Master\Approval;
use App\Models\Master\Ticket;
use App\Support\SatsetRequesterPolicy;
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

        if (SatsetRequesterPolicy::routesDirectlyToGa(
            $ticket->requester?->email ?: data_get($ticket->payload, 'reporter_email')
        )) {
            return $this->routeDirectlyToGa($ticket);
        }

        $approver = $this->approvalResolver->resolveFirstApprover($ticket);

        if ((string) $approver->getKey() === (string) $ticket->requester_id) {
            throw ValidationException::withMessages([
                'approval_resolver' => 'Portal mengembalikan approver yang sama dengan pemohon untuk '.$ticket->ticket_no.'. Periksa reporting line atau Authority Matrix SatSet di Portal.',
            ]);
        }

        return $this->persistApproval($ticket, $approver->id, 1);
    }

    private function routeDirectlyToGa(Ticket $ticket): ?Approval
    {
        $payload = $ticket->payload ?? [];
        $requestType = (string) data_get($payload, 'request_type');

        if ($requestType === 'consumption') {
            $approver = $this->approvalResolver->resolveConsumptionBumApprover($ticket);

            if ((string) $approver->getKey() === (string) $ticket->requester_id) {
                throw ValidationException::withMessages([
                    'approval_resolver' => 'Approver Team GA tidak boleh sama dengan pemohon '.$ticket->ticket_no.'.',
                ]);
            }

            $payload['workflow_status'] = 'WAITING_BUM_VERIFICATION';
            $payload['manager_approval_skipped'] = true;
            $ticket->update(['payload' => $payload]);
            $this->recordDirectGaRouting($ticket);

            return $this->persistApproval($ticket, $approver->id, 2);
        }

        if ($requestType === 'atk_rtk') {
            $payload['workflow_status'] = 'WAITING_BUM_REVIEW';
            $payload['manager_approval_skipped'] = true;
            $ticket->update(['payload' => $payload]);
            $this->recordDirectGaRouting($ticket);
        }

        return null;
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

    private function recordDirectGaRouting(Ticket $ticket): void
    {
        $ticket->histories()->create([
            'user_id' => $ticket->requester_id,
            'status_id' => $ticket->status_id,
            'action' => 'Approval atasan dilewati untuk akun khusus dan permintaan diteruskan langsung ke Team GA.',
        ]);
    }
}
