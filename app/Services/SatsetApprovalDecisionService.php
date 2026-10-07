<?php

namespace App\Services;

use App\Models\Master\Approval;
use App\Models\Master\ApprovalAudit;
use App\Models\Master\Status;
use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SatsetApprovalDecisionService
{
    public function __construct(
        private readonly LrtjSpaceMobileNotificationService $notifications,
        private readonly LrtjSpaceApprovalResolverService $approvalResolver,
        private readonly LrtjSpacePortalSignatureService $portalSignatures,
    ) {}

    public function decide(
        Ticket $ticket,
        Approval $approval,
        User $actor,
        string $status,
        ?string $comment = null,
        string $source = 'satset',
        ?string $externalReferenceId = null,
        ?array $portalSignature = null,
    ): Ticket {
        $normalizedStatus = strtolower($status);
        $bumApprover = null;

        if (strtolower((string) $approval->status) !== 'pending') {
            throw new ConflictHttpException('Approval sudah diproses. Status terbaru: '.$approval->status.'.');
        }

        if (
            $normalizedStatus === 'approved'
            && (int) $approval->level === 1
            && data_get($ticket->payload, 'request_type') === 'consumption'
        ) {
            $bumApprover = $this->approvalResolver->resolveConsumptionBumApprover($ticket);
            if (
                (string) $bumApprover->id === (string) $approval->approver_id
                || ($bumApprover->email && strcasecmp((string) $bumApprover->email, (string) $approval->approver?->email) === 0)
            ) {
                abort(422, 'Approver Bagian Umum dari Portal masih sama dengan Kadiv Pemohon. Periksa user group BUM atau jabatan General Affair Department Head di Portal.');
            }
        }

        if (
            $normalizedStatus === 'approved'
            && $source !== 'portal_intranet'
            && ! $portalSignature
            && in_array(data_get($ticket->payload, 'request_type'), ['atk_rtk', 'consumption'], true)
        ) {
            $portalSignature = $this->portalSignatures->createApprovalSignature(
                $ticket,
                $approval,
                $actor,
                $normalizedStatus,
                $comment,
            );
        }

        $bumApproverReassigned = false;

        return DB::transaction(function () use ($ticket, $approval, $actor, $normalizedStatus, $comment, $source, $externalReferenceId, $portalSignature, $bumApprover, &$bumApproverReassigned) {
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

            $approvalUpdates = [
                'status' => $normalizedStatus,
                'notes' => $comment,
                'decided_at' => now(),
                'last_action_source' => $source,
                'portal_reference_id' => $source === 'portal_intranet' ? $externalReferenceId : $lockedApproval->portal_reference_id,
            ];

            if ($portalSignature) {
                $approvalUpdates = array_merge($approvalUpdates, array_intersect_key($portalSignature, array_flip([
                    'portal_signature_id',
                    'portal_signature_url',
                    'portal_qr_url',
                    'portal_qr_payload',
                ])));
            }

            $lockedApproval->update($approvalUpdates);

            $payload = $freshTicket->payload ?? [];
            $requestType = data_get($payload, 'request_type');
            $ticketStatusName = 'In Progress';
            if ($requestType === 'atk_rtk') {
                $payload['workflow_status'] = $normalizedStatus === 'approved' ? 'WAITING_BUM_REVIEW' : 'REJECTED_BY_MANAGER';
                $ticketStatusName = $normalizedStatus === 'approved' ? 'In Progress' : 'Closed';
            } elseif ($requestType === 'consumption') {
                if ((int) $lockedApproval->level === 1) {
                    $payload['workflow_status'] = $normalizedStatus === 'approved' ? 'WAITING_BUM_VERIFICATION' : 'REJECTED_BY_MANAGER';
                    $ticketStatusName = $normalizedStatus === 'approved' ? 'In Progress' : 'Closed';
                } else {
                    $payload['workflow_status'] = $normalizedStatus === 'approved' ? 'APPROVED_BY_BUM' : 'REJECTED_BY_BUM';
                    $ticketStatusName = $normalizedStatus === 'approved' ? 'In Progress' : 'Closed';
                }
            }
            $ticketStatusId = Status::where('name', $ticketStatusName)->value('id');
            $freshTicket->update(array_filter([
                'payload' => $payload,
                'status_id' => $ticketStatusId,
            ], fn ($value) => $value !== null));

            if ($requestType === 'consumption' && $normalizedStatus === 'approved' && (int) $lockedApproval->level === 1 && $bumApprover) {
                $bumApproval = Approval::firstOrCreate([
                    'request_id' => $freshTicket->id,
                    'level' => 2,
                ], [
                    'approver_id' => $bumApprover->id,
                    'status' => 'Pending',
                ]);

                if (! $bumApproval->wasRecentlyCreated && trim(strtolower((string) $bumApproval->status)) === 'pending' && (string) $bumApproval->approver_id !== (string) $bumApprover->id) {
                    $bumApproval->update(['approver_id' => $bumApprover->id]);
                    $bumApproverReassigned = true;
                }

                if ($bumApproval->wasRecentlyCreated || $bumApproverReassigned) {
                    $this->notifications->notifyApprovalRequested($freshTicket, $bumApproval);
                }
            }

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
            $this->notifications->notifyApprovalDecided($updated, $lockedApproval, $actor, $normalizedStatus, $comment);

            return $updated;
        });
    }
}
