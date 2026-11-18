<?php

namespace App\Services;

use App\Models\Master\Approval;
use App\Models\Master\ApprovalAudit;
use App\Models\Master\Attachment;
use App\Models\Master\Employee;
use App\Models\Master\ProblemCategory;
use App\Models\Master\Status;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Support\AccountAccess;
use App\Support\Sppd;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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
            'job_level' => $this->jobLevel($employee?->nik ?: $user->nik),
        ];
    }

    public function formProfile(User $user): array
    {
        $profile = $this->mergeProfile($user, [], $this->resolver->resolveSppdRequester($user));

        return $profile + ['benefits' => Sppd::benefits($profile['job_level'])];
    }

    public function submit(User $user, array $data): Ticket
    {
        $existing = $this->submittedTicket($user, $data['submission_token']);
        if ($existing) {
            return $existing;
        }

        $storedPaths = [];
        try {
            return DB::transaction(function () use ($user, $data, &$storedPaths) {
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

                return $this->startVersion($ticket, $user, $data, 1, $storedPaths);
            });
        } catch (Throwable $exception) {
            $this->cleanupUploads($storedPaths);
            if ($exception instanceof UniqueConstraintViolationException) {
                return $this->submittedTicket($user, $data['submission_token']) ?? throw $exception;
            }

            throw $exception;
        }
    }

    public function resubmit(Ticket $ticket, User $user, array $data): Ticket
    {
        $storedPaths = [];
        try {
            return DB::transaction(function () use ($ticket, $user, $data, &$storedPaths) {
                $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
                abort_unless(Sppd::is($ticket) && (int) $ticket->requester_id === (int) $user->id, 403);
                abort_unless(
                    data_get($ticket->payload, 'workflow_status') === 'REVISION_REQUESTED'
                        && (int) data_get($ticket->payload, 'sppd.version') === (int) $data['version'],
                    409,
                    'Pengajuan sudah berubah. Muat ulang halaman SPPD.',
                );

                return $this->startVersion($ticket, $user, $data, (int) $data['version'] + 1, $storedPaths);
            });
        } catch (Throwable $exception) {
            $this->cleanupUploads($storedPaths);

            throw $exception;
        }
    }

    private function cleanupUploads(array $paths): void
    {
        if ($paths !== []) {
            rescue(function () use ($paths): void {
                $retained = Attachment::whereIn('file_path', $paths)->pluck('file_path')->all();
                Storage::disk('sppd_private')->delete(array_diff($paths, $retained));
            });
        }
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

    public function proposeBudget(Ticket $ticket, User $admin, array $data): void
    {
        abort_unless(AccountAccess::isAdmin($admin), 403);

        DB::transaction(function () use ($ticket, $admin, $data): void {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticket->id);
            $payload = $ticket->payload;
            $sppd = $payload['sppd'] ?? [];
            $level = array_search($payload['workflow_status'] ?? null, self::WAITING, true);
            abort_unless(Sppd::is($ticket) && $level && (int) $sppd['version'] === (int) $data['version'], 409, 'SPPD ini tidak lagi menunggu persetujuan pada versi tersebut.');
            $pending = $ticket->approvals()->where('status', 'Pending')->lockForUpdate()->findOrFail($sppd['approvals'][$level]);
            $proposal = [];
            $costs = array_intersect_key($sppd['costs'], Sppd::costRows($sppd['category'] === 'international'));
            foreach (['costs' => $costs, 'other_costs' => Sppd::otherCosts($sppd)] as $group => $rows) {
                foreach ($data[$group] ?? [] as $key => $amount) {
                    $row = $rows[$key] ?? null;
                    abort_unless($row, 422, 'Baris anggaran tidak ditemukan.');
                    $previous = $row['usd_cents'] ?? $row['cents'];
                    $cents = (int) round((float) $amount * 100);
                    if (isset($row['usd_cents']) && $cents > 99999999) {
                        throw ValidationException::withMessages(["{$group}.{$key}" => 'Nominal USD maksimum 999.999,99.']);
                    }
                    if ($cents !== $previous) {
                        $proposal[$group][$key] = [
                            'from' => $previous,
                            'to' => $cents,
                            'currency' => isset($row['usd_cents']) ? 'USD' : 'IDR',
                        ];
                    }
                }
            }
            if ($proposal === []) {
                throw ValidationException::withMessages(['costs' => 'Ubah setidaknya satu nominal anggaran.']);
            }

            $payload['workflow_status'] = 'REVISION_REQUESTED';
            $payload['sppd']['budget_revision'] = $proposal + ['reason' => $data['reason']];
            $pending->update([
                'status' => 'revision_requested',
                'decided_at' => now(),
                'notes' => $data['reason'],
                'last_action_source' => 'satset_web',
            ]);
            ApprovalAudit::create([
                'approval_id' => $pending->id,
                'ticket_id' => $ticket->id,
                'approver_id' => $admin->id,
                'source' => 'satset_web',
                'status' => 'revision_requested',
                'comment' => $data['reason'],
                'satset_reference_id' => (string) $pending->id,
                'approver_email' => $admin->email,
                'approver_name' => $admin->name,
                'acted_at' => $pending->decided_at,
            ]);
            $ticket->update(['payload' => $payload]);
            $this->history($ticket, $admin, 'SPPD '.Sppd::reference($ticket).' · Admin mengusulkan revisi nominal anggaran.');
            DB::afterCommit(fn () => rescue(fn () => $this->notifications->notifyApprovalDecided($ticket, $pending, $admin, 'revision_requested', $data['reason']), report: true));
        });
    }

    public function preview(User $user, array $data): array
    {
        $sppd = $this->documentData($data, (int) ($data['version'] ?? 0) + 1);
        $sppd['profile'] = $this->documentProfile($user, $data, $this->resolver->resolveSppdRequester($user));

        return $sppd;
    }

    private function documentData(array $data, int $version): array
    {
        $international = $data['category'] === 'international';
        $rate = (float) data_get($data, 'exchange_rate.idr_per_usd', 0);
        $costs = [];
        foreach (array_keys(Sppd::costRows($international)) as $row) {
            $costs[$row] = $this->cost($data['costs'][$row] ?? [], $rate);
            if ($provider = data_get($data, "costs.{$row}.provider")) {
                $costs[$row]['provider'] = $provider;
            }
        }

        $otherCosts = array_map(fn (array $row) => ['label' => $row['label']] + $this->cost($row, $rate), array_values($data['other_costs'] ?? []));

        $total = array_sum(array_column($costs, 'cents')) + array_sum(array_column($otherCosts, 'cents'));
        $paidToEmployee = array_sum(array_column(array_filter($costs, Sppd::employeeCost(...), ARRAY_FILTER_USE_BOTH), 'cents'));

        return [
            'version' => $version,
            'category' => $data['category'],
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'depart_on' => $data['depart_on'],
            'return_on' => $data['return_on'],
            'days' => (int) CarbonImmutable::parse($data['depart_on'])->diffInDays(CarbonImmutable::parse($data['return_on'])) + 1,
            'purpose' => $data['purpose'],
            'international_purpose' => $data['international_purpose'] ?? null,
            'forecast_temperature' => $data['forecast_temperature'] ?? null,
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'attachments' => $data['attachment_manifest'] ?? [],
            'costs' => $costs,
            'other_costs' => $otherCosts,
            'total_cents' => $total,
            'employee_cents' => $paidToEmployee,
            'notes' => $data['notes'] ?? null,
            'bank' => $data['bank'] ?? [],
        ];
    }

    private function cost(array $input, float $rate): array
    {
        $usd = (int) round((float) ($input['usd_amount'] ?? 0) * 100);
        $cost = [
            'cents' => $usd > 0 ? (int) round($usd * $rate) : (int) round((float) ($input['amount'] ?? 0) * 100),
            'note' => $input['note'] ?? null,
        ];
        if ($usd > 0) {
            $cost['usd_cents'] = $usd;
        }

        return $cost;
    }

    private function jobLevel(?string $nik, mixed $portalGrade = null): ?string
    {
        $level = is_scalar($portalGrade) ? trim((string) $portalGrade) : '';
        if ($level === '' && filled($nik)) {
            $level = trim((string) (config('sppd.employee_grades', [])[$nik] ?? ''));
        }

        return $level === '' ? null : Str::limit($level, 40, '');
    }

    private function mergeProfile(User $user, array $data, array $portal): array
    {
        $local = $this->profile($user);

        return [
            'name' => data_get($portal, 'name') ?: $local['name'],
            'nik' => data_get($portal, 'employee_number') ?: ($local['nik'] ?: ($data['nik'] ?? null)),
            'position' => data_get($portal, 'position') ?: ($local['position'] ?: ($data['position'] ?? null)),
            'division' => data_get($portal, 'organization.division.name') ?: ($local['division'] ?: ($data['division'] ?? null)),
            'directorate' => data_get($portal, 'organization.directorate.name')
                ?: (data_get($portal, 'organization.unit.type') === 'directorate' ? data_get($portal, 'organization.unit.name') : null)
                ?: ($data['directorate'] ?? null),
            'job_level' => $this->jobLevel(data_get($portal, 'employee_number') ?: $local['nik'], data_get($portal, 'job_level') ?? data_get($portal, 'grade')),
        ];
    }

    private function documentProfile(User $user, array $data, array $portal): array
    {
        $profile = $this->mergeProfile($user, $data, $portal);
        $missing = array_filter(array_diff_key($profile, ['job_level' => true]), fn ($value) => blank($value));
        if ($missing) {
            $labels = ['name' => 'Nama', 'nik' => 'NIK', 'position' => 'Jabatan', 'division' => 'Divisi', 'directorate' => 'Direktorat'];
            throw ValidationException::withMessages(array_map(
                fn ($label) => "{$label} wajib diisi karena data profil belum tersedia.",
                array_intersect_key($labels, $missing),
            ));
        }

        return $profile;
    }

    private function startVersion(Ticket $ticket, User $user, array $data, int $version, array &$storedPaths): Ticket
    {
        $sppd = $this->documentData($data, $version);
        $payload = [
            'request_type' => Sppd::TYPE,
            'request_label' => 'SPPD',
            'workflow_status' => self::WAITING[1],
            'total_estimated_amount' => $sppd['total_cents'] / 100,
            'sppd' => $sppd,
        ];
        $ticket->payload = $payload;
        $participants = $this->resolver->resolveSppdParticipants($ticket);
        $payload['sppd']['profile'] = $this->documentProfile($user, $data, $participants['requester']);
        if (array_key_exists('preview_job_level', $data) && $data['preview_job_level'] !== $payload['sppd']['profile']['job_level']) {
            throw ValidationException::withMessages(['preview_token' => 'Job level berubah. Buka ulang pratinjau PDF.']);
        }
        foreach ($data['uploads'] ?? [] as $key => $file) {
            $path = $file->store((string) $ticket->id, 'sppd_private');
            $storedPaths[] = $path;
            $attachment = $ticket->attachments()->create([
                'uploaded_by' => $user->id,
                'file_name' => $payload['sppd']['attachments'][$key]['name'],
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'attachment_type' => 'sppd_'.$key,
                'uploaded_at' => now(),
            ]);
            $payload['sppd']['attachments'][$key]['id'] = $attachment->id;
        }
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
