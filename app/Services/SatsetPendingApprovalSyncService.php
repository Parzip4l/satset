<?php

namespace App\Services;

use App\Models\Master\Approval;
use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SatsetPendingApprovalSyncService
{
    public function __construct(
        private readonly LrtjSpaceApprovalResolverService $approvalResolver,
        private readonly LrtjSpaceMobileNotificationService $notifications,
    ) {}

    /**
     * Re-resolve and reassign a pending level-one approval from Portal.
     *
     * @return array{changed: bool, approval: Approval, previous_approver: ?User, approver: User}
     */
    public function syncManagerApprover(Ticket $ticket, User $actor): array
    {
        $requestType = (string) data_get($ticket->payload, 'request_type');
        if (! in_array($requestType, ['consumption', 'atk_rtk'], true)) {
            throw ValidationException::withMessages([
                'approval_sync' => 'Sinkronisasi atasan hanya tersedia untuk permintaan konsumsi dan ATK/RTK.',
            ]);
        }

        $resolvedApprover = $this->approvalResolver->resolveFirstApprover($ticket);
        if ((string) $resolvedApprover->getKey() === (string) $ticket->requester_id) {
            throw ValidationException::withMessages([
                'approval_sync' => 'Portal mengembalikan approver yang sama dengan pemohon. Periksa reporting line atau Authority Matrix SatSet di Portal.',
            ]);
        }

        $result = DB::transaction(function () use ($ticket, $actor, $resolvedApprover): array {
            $lockedTicket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $approval = Approval::query()
                ->where('request_id', $lockedTicket->id)
                ->where('level', 1)
                ->whereRaw('LOWER(status) = ?', ['pending'])
                ->lockForUpdate()
                ->first();

            if (! $approval) {
                throw ValidationException::withMessages([
                    'approval_sync' => 'Tidak ada approval atasan berstatus Pending yang dapat disinkronkan.',
                ]);
            }

            $previousApprover = $approval->approver()->first();
            if ((string) $approval->approver_id === (string) $resolvedApprover->id) {
                return [
                    'changed' => false,
                    'approval' => $approval,
                    'previous_approver' => $previousApprover,
                    'approver' => $resolvedApprover,
                ];
            }

            $approval->update(['approver_id' => $resolvedApprover->id]);

            $payload = $lockedTicket->payload ?? [];
            $payload['supervisor_id'] = $resolvedApprover->id;
            $payload['supervisor_name'] = $resolvedApprover->name;
            $payload['manager_approval_synced_at'] = now()->toDateTimeString();
            $payload['manager_approval_synced_by'] = $actor->id;
            $lockedTicket->update(['payload' => $payload]);

            $oldLabel = $previousApprover?->name ?: $previousApprover?->email ?: 'approver sebelumnya';
            $newLabel = $resolvedApprover->name ?: $resolvedApprover->email;
            $lockedTicket->histories()->create([
                'user_id' => $actor->id,
                'status_id' => $lockedTicket->status_id,
                'action' => "Approval atasan disinkronkan dari Portal: {$oldLabel} menjadi {$newLabel}.",
            ]);

            return [
                'changed' => true,
                'approval' => $approval->fresh('approver'),
                'previous_approver' => $previousApprover,
                'approver' => $resolvedApprover,
            ];
        });

        if ($result['changed']) {
            $this->notifications->notifyApprovalRequested($ticket->fresh(), $result['approval']);
        }

        return $result;
    }
}
