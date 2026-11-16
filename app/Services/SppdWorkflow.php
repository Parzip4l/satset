<?php

namespace App\Services;

use App\Models\Master\Approval;
use App\Models\Master\ApprovalAudit;
use App\Models\Master\Employee;
use App\Models\Master\ProblemCategory;
use App\Models\Master\Status;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Support\Sppd;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SppdWorkflow
{
    private const WAITING = [
        1 => 'WAITING_MANAGER_APPROVAL',
        2 => 'WAITING_HR_APPROVAL',
        3 => 'WAITING_DIRECTOR_APPROVAL',
    ];

    public function __construct(
        private readonly LrtjSpaceApprovalResolverService $resolver,
        private readonly LrtjSpacePortalSignatureService $signatures,
        private readonly LrtjSpaceMobileNotificationService $notifications,
    ) {}

    public function profile(User $user): array
    {
        $employee = Employee::with('division')->where('user_id', $user->id)->first();

        return [
            'name' => $employee?->nama_lengkap ?: $user->name,
            'nik' => $employee?->nik ?: $user->nik,
            'position' => $employee?->jabatan,
            'division' => $employee?->division?->name ?: $user->division?->name,
            'directorate' => null,
        ];
    }

    public function submit(User $user, array $data): Ticket
    {
        $existing = $this->submittedTicket($user, $data['submission_token']);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($user, $data) {
                $category = ProblemCategory::firstOrCreate(['code' => 'SPPD'], ['name' => 'Perjalanan Dinas']);
                $ticket = Ticket::create([
                    'ticket_no' => 'SPPD-'.Str::upper(Str::random(16)),
                    'submission_token' => $data['submission_token'],
                    'requester_id' => $user->id,
                    'category_id' => $category->id,
                    'status_id' => $this->statusId('In Progress'),
                    'title' => 'Pengajuan Perjalanan Dinas (SPPD)',
                    'payload' => ['request_type' => Sppd::TYPE],
                ]);
                $ticket->setRelation('requester', $user);

                return $this->startVersion($ticket, $user, $data, 1);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->submittedTicket($user, $data['submission_token']) ?? throw $exception;
        }
    }

    public function resubmit(Ticket $ticket, User $user, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $user, $data) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            abort_unless(Sppd::is($ticket) && (int) $ticket->requester_id === (int) $user->id, 403);
            abort_unless(
                data_get($ticket->payload, 'workflow_status') === 'REVISION_REQUESTED'
                    && (int) data_get($ticket->payload, 'sppd.version') === (int) $data['version'],
                409,
                'Pengajuan sudah berubah. Muat ulang halaman SPPD.',
            );

            return $this->startVersion($ticket, $user, $data, (int) $data['version'] + 1);
        });
    }

    public function decide(Ticket $ticket, Approval $approval, User $actor, string $decision, ?string $note): void
    {
        DB::transaction(function () use ($ticket, $approval, $actor, $decision, $note) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $approval = $ticket->approvals()->lockForUpdate()->findOrFail($approval->id);
            $level = (int) $approval->level;
            $payload = $ticket->payload;
            abort_unless(Sppd::is($ticket) && (int) $approval->approver_id === (int) $actor->id
                && (int) $ticket->requester_id !== (int) $actor->id, 403);
            abort_unless(
                $approval->status === 'Pending'
                    && (int) data_get($payload, "sppd.approvals.{$level}") === (int) $approval->id
                    && ($payload['workflow_status'] ?? null) === (self::WAITING[$level] ?? null),
                409,
                'Persetujuan ini sudah diproses. Muat ulang halaman SPPD.',
            );

            $approved = $decision === 'approved';
            $signature = $approved ? $this->signatures->createSppdSignature($ticket, $actor, $level) : [];
            $approval->update($signature + [
                'status' => $decision,
                'notes' => $note,
                'decided_at' => now(),
                'last_action_source' => 'satset_web',
            ]);

            $nextLevel = $level + 1;
            $payload['workflow_status'] = match ($decision) {
                'revision_requested' => 'REVISION_REQUESTED',
                'rejected' => 'REJECTED',
                'approved' => self::WAITING[$nextLevel] ?? 'ISSUED',
            };
            $next = null;
            if ($approved && isset(self::WAITING[$nextLevel])) {
                $next = $ticket->approvals()->create([
                    'approver_id' => $payload['sppd']['approvers'][$nextLevel],
                    'level' => $nextLevel,
                    'status' => 'Pending',
                ]);
                $payload['sppd']['approvals'][$nextLevel] = $next->id;
            }

            $issued = $payload['workflow_status'] === 'ISSUED';
            $ticket->update([
                'payload' => $payload,
                'status_id' => $this->statusId(match ($payload['workflow_status']) {
                    'ISSUED' => 'Resolved',
                    'REJECTED' => 'Closed',
                    default => 'In Progress',
                }),
                'resolved_at' => $issued ? now() : null,
                'closed_at' => $decision === 'rejected' ? now() : null,
            ]);

            ApprovalAudit::create([
                'approval_id' => $approval->id,
                'ticket_id' => $ticket->id,
                'approver_id' => $actor->id,
                'source' => 'satset_web',
                'status' => $decision,
                'comment' => $note,
                'satset_reference_id' => (string) $approval->id,
                'approver_email' => $actor->email,
                'approver_name' => $actor->name,
                'acted_at' => $approval->decided_at,
            ]);
            $this->history($ticket, $actor, 'SPPD '.Sppd::reference($ticket).' · '.Sppd::label($ticket));
            DB::afterCommit(function () use ($ticket, $approval, $actor, $decision, $note, $next) {
                rescue(fn () => $this->notifications->notifyApprovalDecided($ticket, $approval, $actor, $decision, $note), report: true);
                if ($next) {
                    rescue(fn () => $this->notifications->notifyApprovalRequested($ticket, $next), report: true);
                }
            });
        });
    }

    private function startVersion(Ticket $ticket, User $user, array $data, int $version): Ticket
    {
        $costs = [];
        foreach (Sppd::COSTS as $row => $cost) {
            $costs[$row] = [
                'cents' => (int) round(((float) ($data['costs'][$row]['amount'] ?? 0)) * 100),
                'note' => $data['costs'][$row]['note'] ?? $cost[2],
            ];
        }

        $total = array_sum(array_column($costs, 'cents'));
        $paidToEmployee = array_sum(array_column(array_intersect_key($costs, array_flip(Sppd::EMPLOYEE_COST_ROWS)), 'cents'));
        $payload = [
            'request_type' => Sppd::TYPE,
            'request_label' => 'SPPD',
            'workflow_status' => self::WAITING[1],
            'total_estimated_amount' => $total / 100,
            'sppd' => [
                'version' => $version,
                'category' => $data['category'],
                'origin' => $data['origin'],
                'destination' => $data['destination'],
                'depart_on' => $data['depart_on'],
                'return_on' => $data['return_on'],
                'days' => (int) CarbonImmutable::parse($data['depart_on'])->diffInDays(CarbonImmutable::parse($data['return_on'])) + 1,
                'purpose' => $data['purpose'],
                'costs' => $costs,
                'total_cents' => $total,
                'employee_cents' => $paidToEmployee,
                'notes' => $data['notes'] ?? null,
                'bank' => $data['bank'] ?? [],
            ],
        ];
        $ticket->payload = $payload;
        $participants = $this->resolver->resolveSppdParticipants($ticket);
        $local = $this->profile($user);
        $portal = $participants['requester'];
        $profile = [
            'name' => data_get($portal, 'name') ?: $local['name'],
            'nik' => data_get($portal, 'employee_number') ?: ($local['nik'] ?: ($data['nik'] ?? null)),
            'position' => data_get($portal, 'position') ?: ($local['position'] ?: ($data['position'] ?? null)),
            'division' => data_get($portal, 'organization.division.name') ?: ($local['division'] ?: ($data['division'] ?? null)),
            'directorate' => data_get($portal, 'organization.directorate.name')
                ?: (data_get($portal, 'organization.unit.type') === 'directorate' ? data_get($portal, 'organization.unit.name') : null)
                ?: ($data['directorate'] ?? null),
        ];
        $missing = array_filter($profile, fn ($value) => blank($value));
        if ($missing) {
            $labels = ['name' => 'Nama', 'nik' => 'NIK', 'position' => 'Jabatan', 'division' => 'Divisi', 'directorate' => 'Direktorat'];
            throw ValidationException::withMessages(array_map(
                fn ($label) => "{$label} wajib diisi karena data profil belum tersedia.",
                array_intersect_key($labels, $missing),
            ));
        }

        $payload['sppd']['profile'] = $profile;
        $payload['sppd']['approvers'] = $participants['approvers'];
        $payload['sppd']['requester_signature'] = $this->signatures->createSppdSignature($ticket, $user, 0);
        $payload['sppd']['submitted_at'] = now()->toIso8601String();

        $first = $ticket->approvals()->create([
            'approver_id' => $participants['approvers'][1],
            'level' => 1,
            'status' => 'Pending',
        ]);
        $payload['sppd']['approvals'] = [1 => $first->id];
        $ticket->update(['payload' => $payload, 'status_id' => $this->statusId('In Progress')]);
        $this->history($ticket, $user, 'Pengajuan '.Sppd::reference($ticket).' dikirim.');
        DB::afterCommit(fn () => rescue(fn () => $this->notifications->notifyApprovalRequested($ticket, $first), report: true));

        return $ticket;
    }

    private function submittedTicket(User $user, string $token): ?Ticket
    {
        $existing = Ticket::where('submission_token', $token)->first();
        if ($existing) {
            abort_unless(Sppd::is($existing) && (int) $existing->requester_id === (int) $user->id, 409);
        }

        return $existing;
    }

    private function statusId(string $name): int
    {
        return Status::where('name', $name)->firstOrFail()->id;
    }

    private function history(Ticket $ticket, User $actor, string $action): void
    {
        $ticket->histories()->create([
            'user_id' => $actor->id,
            'status_id' => $ticket->status_id,
            'action' => $action,
        ]);
    }
}
