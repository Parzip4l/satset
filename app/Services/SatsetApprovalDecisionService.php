<?php

namespace App\Services;

use App\Models\Master\Approval;
use App\Models\Master\ApprovalAudit;
use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SatsetApprovalDecisionService
{
    public function __construct(private readonly LrtjSpaceMobileNotificationService $notifications) {}

    public function decide(
        Ticket $ticket,
        Approval $approval,
        User $actor,
        string $status,
        ?string $comment = null,
        string $source = 'satset',
        ?string $externalReferenceId = null,
    ): Ticket {
        $normalizedStatus = strtolower($status);

        return DB::transaction(function () use ($ticket, $approval, $actor, $normalizedStatus, $comment, $source, $externalReferenceId) {
            $lockedApproval = Approval::query()
                ->whereKey($approval->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedApproval->request_id !== (int) $ticket->id) {
                abort(404, 'Approval tidak ditemukan untuk ticket ini.');
            }

            if (strtolower((string) $lockedApproval->status) !== 'pending') {
                throw new ConflictHttpException('Approval sudah diproses. Status terbaru: '.$lockedApproval->status.'.');
            }

            $freshTicket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $previousStatus = data_get($freshTicket->payload, 'workflow_status') ?: ($freshTicket->status->name ?? null);

            $lockedApproval->update([
                'status' => $normalizedStatus,
                'notes' => $comment,
                'decided_at' => now(),
                'last_action_source' => $source,
                'portal_reference_id' => $source === 'portal_intranet' ? $externalReferenceId : $lockedApproval->portal_reference_id,
            ]);

            $payload = $freshTicket->payload ?? [];
            $requestType = data_get($payload, 'request_type');
            if ($requestType === 'atk_rtk') {
                $payload['workflow_status'] = $normalizedStatus === 'approved' ? 'WAITING_BUM_REVIEW' : 'REJECTED_BY_MANAGER';
            } elseif ($requestType === 'consumption') {
                $payload['workflow_status'] = $normalizedStatus === 'approved' ? 'WAITING_BUM_VERIFICATION' : 'REJECTED_BY_MANAGER';
            }
            $freshTicket->update(['payload' => $payload]);

            ApprovalAudit::create([
                'approval_id' => $lockedApproval->id,
                'ticket_id' => $freshTicket->id,
                'approver_id' => $actor->id,
                'source' => $source,
                'status' => $normalizedStatus,
                'comment' => $comment,
                'satset_reference_id' => (string) $lockedApproval->id,
                'external_reference_id' => $externalReferenceId,
                'approver_email' => $actor->email,
                'approver_name' => $actor->name,
                'acted_at' => now(),
            ]);

            $freshTicket->histories()->create([
                'user_id' => $actor->id,
                'status_id' => $freshTicket->status_id,
                'action' => ucfirst($normalizedStatus).' approval level '.$lockedApproval->level.' via '.$source.' (Satset approval #'.$lockedApproval->id.')',
            ]);

            $updated = $freshTicket->fresh(['requester', 'status']);
            $this->notifications->notifyTicketStatusChanged($updated, $actor, $previousStatus);

            return $updated;
        });
    }
}
