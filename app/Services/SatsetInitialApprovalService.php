<?php

namespace App\Services;

use App\Jobs\ResolveAtkRtkManagerApproval;
use App\Models\Master\Approval;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Support\GaSubmissionDelivery;
use App\Support\GaSubmissionTiming;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SatsetInitialApprovalService
{
    public const QUEUED = 'queued';

    public const FAILED = 'failed';

    public function __construct(
        private readonly LrtjSpaceApprovalResolverService $approvalResolver,
        private readonly LrtjSpaceMobileNotificationService $notifications,
    ) {}

    public function start(Ticket $ticket): void
    {
        if (data_get($ticket->payload, 'workflow_status') !== 'WAITING_MANAGER_APPROVAL') {
            return;
        }

        if (data_get($ticket->payload, 'request_type') === 'atk_rtk' && $this->canQueue($ticket)) {
            GaSubmissionTiming::measure('approval_enqueue', fn () => $this->enqueue($ticket));

            return;
        }

        $this->create($ticket);
    }

    public function create(Ticket $ticket): ?Approval
    {
        if (data_get($ticket->payload, 'workflow_status') !== 'WAITING_MANAGER_APPROVAL') {
            return null;
        }

        return $this->persistApproval($ticket, $this->resolveApprover($ticket)->id, 1);
    }

    public function resolveQueued(Ticket $ticket): void
    {
        if (! $this->needsResolution($ticket)) {
            return;
        }

        $existing = $ticket->approvals()->where('level', 1)->first();
        $approver = $existing ? null : $this->resolveApprover($ticket);

        DB::transaction(function () use ($ticket, $approver): void {
            $current = Ticket::lockForUpdate()->find($ticket->id);
            if (! $current || $current->ticket_no !== $ticket->ticket_no || ! $this->needsResolution($current)) {
                return;
            }

            $approval = $current->approvals()->where('level', 1)->first();
            if (! $approval) {
                abort_unless($approver, 409, 'Approval berubah. Muat ulang detail tiket.');
                $this->persistApproval($current, $approver->id, 1);
            }
            $payload = $current->payload;
            unset($payload['manager_approval_setup']);
            $current->updateQuietly(['payload' => $payload]);
        });
    }

    public function markResolutionFailed(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket): void {
            $current = Ticket::lockForUpdate()->find($ticket->id);
            if ($current && $current->ticket_no === $ticket->ticket_no && $this->needsResolution($current)) {
                $current->updateQuietly(['payload' => array_replace($current->payload, ['manager_approval_setup' => self::FAILED])]);
            }
        });
    }

    public function retryFailedResolution(Ticket $ticket): bool
    {
        return DB::transaction(function () use ($ticket): bool {
            $current = Ticket::lockForUpdate()->findOrFail($ticket->id);
            if (! $this->needsResolution($current) || data_get($current->payload, 'manager_approval_setup') !== self::FAILED) {
                return false;
            }

            if (! $this->canQueue($current)) {
                throw ValidationException::withMessages(['approval_sync' => 'Antrean pencarian atasan belum tersedia. Silakan coba lagi.']);
            }

            $this->enqueue($current);

            return true;
        });
    }

    private function needsResolution(Ticket $ticket): bool
    {
        return data_get($ticket->payload, 'request_type') === 'atk_rtk'
            && data_get($ticket->payload, 'workflow_status') === 'WAITING_MANAGER_APPROVAL'
            && in_array(data_get($ticket->payload, 'manager_approval_setup'), [self::QUEUED, self::FAILED], true);
    }

    private function enqueue(Ticket $ticket): void
    {
        $ticket->updateQuietly(['payload' => array_replace($ticket->payload, ['manager_approval_setup' => self::QUEUED])]);
        Bus::dispatch(new ResolveAtkRtkManagerApproval($ticket->id, $ticket->ticket_no));
    }

    private function canQueue(Ticket $ticket): bool
    {
        // The ticket and its approval job must commit together.
        return DB::connection(config('queue.connections.database.connection')) === $ticket->getConnection()
            && GaSubmissionDelivery::usesQueue($ticket);
    }

    private function resolveApprover(Ticket $ticket): User
    {
        $ticket->loadMissing('requester');
        $approver = $this->approvalResolver->resolveFirstApprover($ticket);

        if ((string) $approver->getKey() === (string) $ticket->requester_id) {
            throw ValidationException::withMessages([
                'approval_resolver' => 'Portal mengembalikan approver yang sama dengan pemohon untuk '.$ticket->ticket_no.'. Periksa reporting line atau Authority Matrix SatSet di Portal.',
            ]);
        }

        return $approver;
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
            $this->notifications->notifyApprovalRequested($ticket, $approval, GaSubmissionDelivery::usesQueue($ticket));
        }

        return $approval;
    }
}
