<?php

namespace App\Services\It;

use App\Models\It\ConfigurationVersion;
use App\Models\It\TicketDetail;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\LrtjSpaceApprovalResolverService;
use App\Support\ItAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** All web/mobile writes use this service; the request row serializes every ticket mutation. */
class TicketWorkflow
{
    public const TERMINAL = ['closed', 'cancelled', 'rejected'];

    public const STATES = ['triage', 'queued', 'awaiting_approval', 'approval_exception', 'needs_revision', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled', 'rejected'];

    public function __construct(private FormEngine $forms, private OperationalProfile $profiles) {}

    public function active(): ConfigurationVersion
    {
        $id = DB::table('it_configuration_sets')->where('code', 'it')->value('active_version_id');
        $version = ConfigurationVersion::find($id);
        abort_unless($version && $version->state === 'published' && in_array(($version->configuration['schema_version'] ?? 1), [2, 3], true), 409, 'Workflow IT belum dipublikasikan.');

        return $version;
    }

    public function dependencies(array $cfg, string $formKey): array
    {
        $form = collect($cfg['forms'])->firstWhere('key', $formKey);
        $keys = [];
        $walk = function ($node) use (&$walk, &$keys) {
            if (! $node) {
                return;
            }
            if (isset($node['rules'])) {
                foreach ($node['rules'] as $child) {
                    $walk($child);
                }
            } elseif (isset($node['field'])) {
                $keys[] = $node['field'];
            }
        };
        foreach ($form['fields'] as $field) {
            if (in_array('requester', $field['visible_roles'], true)) {
                $walk($field['visible_when'] ?? null);
                $walk($field['required_when'] ?? null);
            }
        }

        return array_values(array_unique($keys));
    }

    public function enabled(?User $user = null): bool
    {
        return app(PilotGate::class)->allowed($user ?? auth()->user());
    }

    public function submit(User $actor, array $input, array $uploads = []): Ticket
    {
        abort_unless($this->enabled($actor), 423, 'Pengajuan IT belum dibuka untuk akun ini atau gerbang pilot belum siap.');
        $data = Validator::make($input, [
            'configuration_version_id' => 'sometimes|integer|min:1', 'submission_token' => 'required|uuid', 'catalog_key' => 'required|string|max:50',
            'title' => 'required|string|max:150', 'description' => 'required|string|max:10000',
            'answers' => 'present|array|max:60', 'impact' => 'required|in:high,medium,low', 'urgency' => 'required|in:high,medium,low',
        ])->validate();
        $fingerprintData = $data;
        foreach ($uploads as $field => $files) {
            foreach ((array) $files as $file) {
                abort_unless($file instanceof UploadedFile && $file->isValid(), 422, 'Upload tidak valid.');
                $fingerprintData['files'][$field][] = [$file->getClientOriginalName(), hash_file('sha256', $file->getRealPath())];
            }
        }
        $fingerprint = ConfigurationStore::checksum($fingerprintData);
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $data, $uploads, $fingerprint, &$paths) {
                DB::table('it_configuration_sets')->where('code', 'it')->lockForUpdate()->first();
                $existing = Ticket::where('submission_token', $data['submission_token'])->first();
                if ($existing) {
                    abort_unless($existing->requester_id === $actor->id && $existing->itDetail?->submission_fingerprint === $fingerprint, 409, 'Token pengajuan sudah digunakan dengan isi berbeda.');

                    return $existing;
                }
                abort_unless(app(PilotGate::class)->allowed($actor, $data['catalog_key']), 423, 'Katalog tidak termasuk pilot atau gerbang pilot belum siap.');
                $version = $this->active();
                $cfg = $version->configuration;
                abort_if(isset($data['configuration_version_id']) && (int) $data['configuration_version_id'] !== $version->id, 409, 'Konfigurasi berubah sejak form dibuka. Muat ulang formulir.');
                $catalog = collect($cfg['catalog'])->firstWhere('key', $data['catalog_key']);
                abort_unless($catalog && $catalog['is_active'], 422, 'Item katalog tidak aktif.');
                $service = collect($cfg['services'])->firstWhere('key', $catalog['service_key']);
                $category = collect($cfg['categories'])->firstWhere('key', $catalog['category_key']);
                abort_unless($service['is_active'] && $category['is_active'], 422, 'Layanan/kategori tidak aktif.');
                $answers = $this->answers($cfg, $catalog['form_key'], $data['answers'], $uploads);
                $legacyCategory = DB::table('problem_categories')->where('code', 'IT')->value('id');
                abort_unless($legacyCategory, 503, 'Kategori induk IT belum tersedia.');
                $ticket = new Ticket;
                $ticket->forceFill(['ticket_no' => 'IT-'.now()->format('Ymd').'-'.Str::upper(Str::random(12)),
                    'submission_token' => $data['submission_token'], 'requester_id' => $actor->id,
                    'category_id' => $legacyCategory, 'service_domain_id' => DB::table('service_domains')->where('code', 'it')->value('id'),
                    'title' => $data['title'], 'description' => $data['description'], 'status_id' => $this->legacyStatus('triage'),
                    'payload' => ['request_type' => 'it', 'it_workflow' => 'it_v1']])->save();
                $detail = new TicketDetail;
                $detail->forceFill(['request_id' => $ticket->id, 'configuration_version_id' => $version->id, 'form_key' => $catalog['form_key'],
                    'form_answers' => $answers, 'is_restricted' => (bool) ($catalog['is_restricted'] ?? false), 'record_type' => $catalog['record_type'], 'catalog_key' => $catalog['key'],
                    'service_key' => $catalog['service_key'], 'category_key' => $catalog['category_key'], 'status' => 'triage',
                    'submission_fingerprint' => $fingerprint, 'impact' => $data['impact'], 'urgency' => $data['urgency'],
                    'priority' => $cfg['profiles']['priority_matrix'][$data['impact'].'.'.$data['urgency']], 'lock_version' => 1, 'cycle' => 1])->save();
                $this->route($detail, $cfg);
                $this->approval($ticket, $detail, $cfg);
                $detail->save();
                $this->uploads($ticket, $actor, $uploads, false, $paths, 1);
                $this->event($ticket, $detail, $actor, 'submitted', null, 'Pengajuan IT', ['configuration_version_id' => $version->id, 'priority' => $detail->priority]);

                return $ticket->fresh();
            }, 1);
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                Storage::disk('it_private')->delete($path);
            } throw $e;
        }
    }

    private function answers(array $cfg, string $key, array $answers, array $uploads): array
    {
        $result = $this->forms->inspect($cfg, $key, 'requester', $answers, $uploads, true);
        if ($result['errors']) {
            throw ValidationException::withMessages($result['errors']);
        }

        return $result['answers'];
    }

    private function route(TicketDetail $d, array $cfg): void
    {
        $service = collect($cfg['services'])->firstWhere('key', $d->service_key);
        $team = $service['fallback_team_id'] ?? null;
        foreach ($cfg['profiles']['routing'] as $rule) {
            if ($this->profiles->matches($rule, $d->getAttributes())) {
                $team = $rule['team_id'];
                break;
            }
        }
        if ($team) {
            try {
                $this->profiles->readyTeam((int) $team);
            } catch (ValidationException) {
                $team = null;
            }
        }
        // A stale target falls back to its service team; unavailable fallback is a visible triage exception.
        if (! $team && ! empty($service['fallback_team_id'])) {
            try {
                $this->profiles->readyTeam((int) $service['fallback_team_id']);
                $team = $service['fallback_team_id'];
            } catch (ValidationException) {
            }
        }
        $d->support_team_id = $team;
        $d->status = $team ? 'queued' : 'triage';
        $d->pending_reason = $team ? null : 'Tim routing/fallback tidak tersedia. Koordinator perlu melakukan triage.';
    }

    private function approval(Ticket $ticket, TicketDetail $d, array $cfg): void
    {
        if ($d->record_type !== 'service_request') {
            return;
        }
        foreach ($cfg['profiles']['approval'] as $rule) {
            if (! $this->profiles->matches($rule, $d->getAttributes())) {
                continue;
            }
            $approverId = null;
            $exception = null;
            try {
                $approverId = $rule['resolver'] === 'portal'
                    ? app(LrtjSpaceApprovalResolverService::class)->resolveFirstApprover($ticket)->id : (int) $rule['approver_id'];
                if (! $approverId || $approverId === $ticket->requester_id || ! User::whereKey($approverId)->exists()) {
                    throw new \RuntimeException('Approver tidak memenuhi syarat.');
                }
            } catch (\Throwable $e) {
                report($e);
                $approverId = null;
                $exception = 'Resolver approver gagal atau menghasilkan self-approval; diperlukan tindak lanjut koordinator.';
            }
            DB::table('it_ticket_approvals')->insert(['request_id' => $ticket->id, 'cycle' => $d->cycle, 'rule_key' => $rule['key'],
                'approver_id' => $approverId, 'status' => $exception ? 'exception' : 'pending', 'reason' => $exception, 'created_at' => now(), 'updated_at' => now()]);
            $d->status = $exception ? 'approval_exception' : 'awaiting_approval';
            $d->pending_reason = $exception;

            return;
        }
    }

    public function member(User $user, ?int $team, ?array $roles = null): bool
    {
        if (! $team || ! DB::table('support_teams')->where('id', $team)->where('is_active', true)->exists()) {
            return false;
        }
        if (\App\Support\AccountAccess::isSuperadmin($user)) {
            return true;
        }
        $q = DB::table('support_team_members')->where('support_team_id', $team)->where('user_id', $user->id);

        return $q->whereIn('role', $roles ?? ['technician', 'coordinator'])->exists();
    }

    public function staff(User $user, Ticket $ticket, TicketDetail $detail): bool
    {
        return ItAccess::canRead($user, $ticket) && ($this->member($user, $detail->support_team_id) || ItAccess::can($user, 'manage_it_operations'));
    }

    public function coordinator(User $user, Ticket $ticket, TicketDetail $detail): bool
    {
        return ItAccess::canRead($user, $ticket) && ($this->member($user, $detail->support_team_id, ['coordinator']) || ItAccess::can($user, 'manage_it_operations'));
    }

    public function action(User $actor, Ticket $ticket, string $action, array $input = [], array $files = []): Ticket
    {
        $data = Validator::make($input, ['lock_version' => 'required|integer|min:1', 'reason' => 'sometimes|nullable|string|max:5000'])->validate();
        $paths = [];
        try {
            return DB::transaction(function () use ($actor, $ticket, $action, $input, $data, $files, &$paths) {
                $t = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                $d = TicketDetail::where('request_id', $t->id)->lockForUpdate()->firstOrFail();
                abort_unless($d->catalog_key && $d->configuration_version_id, 409, 'Tiket lama belum dimigrasikan ke workflow IT.');
                abort_unless(ItAccess::canRead($actor, $t), 403);
                abort_unless((int) $d->lock_version === (int) $data['lock_version'], 409, 'Tiket berubah. Muat ulang sebelum melanjutkan.');
                $cfg = $d->configurationVersion->configuration;
                $staff = $this->staff($actor, $t, $d);
                $coordinator = $this->coordinator($actor, $t, $d);
                $owner = $actor->id === $t->requester_id;
                $from = $d->status;
                $reason = trim($data['reason'] ?? '');
                $metadata = [];
                switch ($action) {
                    case 'triage':
                        abort_unless($coordinator, 403);
                        $this->state($d, ['triage', 'queued']);
                        $this->reason($reason);
                        Validator::make($input, ['impact' => 'required|in:high,medium,low', 'urgency' => 'required|in:high,medium,low', 'team_id' => 'required|integer'])->validate();
                        $this->profiles->readyTeam((int) $input['team_id']);
                        $d->impact = $input['impact'];
                        $d->urgency = $input['urgency'];
                        $d->priority = $cfg['profiles']['priority_matrix'][$d->impact.'.'.$d->urgency];
                        $d->support_team_id = (int) $input['team_id'];
                        $d->status = 'queued';
                        $d->pending_reason = null;
                        $t->assigned_user_id = null;
                        $metadata = ['priority' => $d->priority, 'support_team_id' => $d->support_team_id];
                        break;
                    case 'priority':
                        abort_unless($coordinator, 403);
                        $this->state($d, array_values(array_diff(self::STATES, array_merge(self::TERMINAL, ['resolved']))));
                        $this->reason($reason);
                        Validator::make($input, ['impact' => 'required|in:high,medium,low', 'urgency' => 'required|in:high,medium,low'])->validate();
                        $metadata = ['previous_priority' => $d->priority];
                        $d->impact = $input['impact'];
                        $d->urgency = $input['urgency'];
                        $d->priority = $cfg['profiles']['priority_matrix'][$d->impact.'.'.$d->urgency];
                        $metadata['priority'] = $d->priority;
                        break;
                    case 'major_start':
                        abort_unless($coordinator, 403);
                        $this->state($d, ['triage', 'queued', 'in_progress', 'pending']);
                        $this->reason($reason);
                        abort_unless(($cfg['schema_version'] ?? 1) === 3 && $d->record_type === 'incident' && ! $d->is_major, 409, 'Major incident memerlukan Incident dengan profil operasi.');
                        abort_if(DB::table('it_ticket_links')->where('child_request_id', $t->id)->where('type', 'major_child')->exists(), 409, 'Incident ini sudah terkait major incident lain.');
                        Validator::make($input, ['commander_id' => 'required|integer|exists:users,id', 'interval_minutes' => 'required|integer|between:15,1440'])->validate();
                        $commander = User::findOrFail($input['commander_id']);
                        abort_unless($this->staff($commander, $t, $d), 422, 'Koordinator komunikasi harus memiliki akses penanganan.');
                        $d->is_major = true;
                        $d->major_commander_id = $commander->id;
                        $d->major_interval_minutes = (int) $input['interval_minutes'];
                        $d->major_next_update_at = now('UTC')->addMinutes($d->major_interval_minutes);
                        $d->major_summary = $reason;
                        break;
                    case 'major_update':
                        abort_unless($staff && ($coordinator || $d->major_commander_id === $actor->id), 403);
                        abort_unless($d->is_major, 409);
                        $this->reason($reason);
                        $d->major_summary = $reason;
                        $d->major_next_update_at = now('UTC')->addMinutes($d->major_interval_minutes);
                        DB::table('it_ticket_comments')->insert(['request_id' => $t->id, 'author_id' => $actor->id, 'body' => $reason, 'is_internal' => false, 'created_at' => now()]);
                        break;
                    case 'major_end':
                        abort_unless($coordinator, 403);
                        $this->state($d, ['resolved', 'closed']);
                        abort_unless($d->is_major, 409);
                        $this->reason($reason);
                        $d->is_major = false;
                        $d->major_next_update_at = null;
                        $d->major_review = $reason;
                        break;
                    case 'major_link':
                        abort_unless($coordinator && $d->is_major, 403);
                        $this->reason($reason);
                        Validator::make($input, ['child_request_id' => 'required|integer'])->validate();
                        abort_if((int) $input['child_request_id'] === $t->id, 422);
                        $child = Ticket::whereKey($input['child_request_id'])->lockForUpdate()->firstOrFail();
                        abort_unless($child->itDetail && $this->staff($actor, $child, $child->itDetail), 403);
                        abort_unless($child->itDetail->record_type === 'incident' && ! $child->itDetail->is_major, 422, 'Tautan harus menuju Incident biasa.');
                        abort_if(DB::table('it_ticket_links')->where('child_request_id', $child->id)->where('type', 'major_child')->exists(), 409, 'Incident sudah memiliki tautan major.');
                        DB::table('it_ticket_links')->insert(['parent_request_id' => $t->id, 'child_request_id' => $child->id, 'type' => 'major_child', 'created_by' => $actor->id, 'reason' => $reason, 'created_at' => now('UTC')]);
                        break;
                    case 'claim':
                        abort_unless($staff && $this->member($actor, $d->support_team_id), 403);
                        $this->state($d, ['queued']);
                        abort_if($t->assigned_user_id, 409, 'Tiket sudah ditangani petugas lain.');
                        $t->assigned_user_id = $actor->id;
                        break;
                    case 'assign': case 'transfer':
                        abort_unless($coordinator, 403);
                        $this->state($d, ['triage', 'queued', 'in_progress', 'pending']);
                        $this->reason($reason);
                        Validator::make($input, ['team_id' => 'required|integer', 'user_id' => 'nullable|integer|exists:users,id'])->validate();
                        $this->profiles->readyTeam((int) $input['team_id']);
                        if (! empty($input['user_id'])) {
                            $target = User::findOrFail($input['user_id']);
                            abort_unless($this->member($target, (int) $input['team_id']) && (! $d->is_restricted || ItAccess::can($target, 'view_restricted_it')), 422, 'PIC harus anggota aktif tim dan memiliki akses tiket.');
                        }
                        $d->support_team_id = (int) $input['team_id'];
                        $t->assigned_user_id = $input['user_id'] ?? null;
                        $d->status = 'queued';
                        $d->pending_reason = null;
                        $d->next_update_at = null;
                        $metadata = ['support_team_id' => $d->support_team_id, 'assigned_user_id' => $t->assigned_user_id];
                        break;
                    case 'start':
                        abort_unless($staff && $t->assigned_user_id === $actor->id, 403);
                        $this->state($d, ['queued']);
                        $d->status = 'in_progress';
                        break;
                    case 'pending':
                        abort_unless($staff && $t->assigned_user_id === $actor->id, 403);
                        $this->state($d, ['in_progress']);
                        $this->reason($reason);
                        Validator::make($input, ['next_update_at' => 'required|date|after:now'])->validate();
                        if (($cfg['schema_version'] ?? 1) === 3) {
                            Validator::make($input, ['pending_code' => 'required|in:waiting_customer,waiting_vendor,waiting_internal,waiting_parts,scheduled_change'])->validate();
                            $d->pending_code = $input['pending_code'];
                        }
                        $d->status = 'pending';
                        $d->pending_reason = $reason;
                        $d->next_update_at = $input['next_update_at'];
                        break;
                    case 'resume':
                        abort_unless($staff && $t->assigned_user_id === $actor->id, 403);
                        $this->state($d, ['pending']);
                        $this->reason($reason);
                        $d->status = 'in_progress';
                        if (($cfg['schema_version'] ?? 1) === 3) {
                            $d->pending_code = null;
                        }
                        $d->pending_reason = null;
                        $d->next_update_at = null;
                        break;
                    case 'task':
                        abort_unless($staff && ($t->assigned_user_id === $actor->id || $coordinator), 403);
                        $this->state($d, ['queued', 'in_progress', 'pending']);
                        $task = Validator::make($input, ['title' => 'required|string|max:200', 'assigned_to' => 'required|integer|exists:users,id', 'required' => 'required|boolean'])->validate();
                        $target = User::findOrFail($task['assigned_to']);
                        abort_unless($this->member($target, $d->support_team_id) && (! $d->is_restricted || ItAccess::can($target, 'view_restricted_it')), 422, 'Pelaksana tugas harus anggota tim dengan akses tiket.');
                        $metadata['task_id'] = DB::table('it_ticket_tasks')->insertGetId($task + ['request_id' => $t->id, 'created_by' => $actor->id, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
                        break;
                    case 'reassign_task':
                        abort_unless($coordinator, 403);
                        $this->state($d, ['queued', 'in_progress', 'pending']);
                        $this->reason($reason);
                        Validator::make($input, ['task_id' => 'required|integer', 'assigned_to' => 'required|integer|exists:users,id'])->validate();
                        $task = DB::table('it_ticket_tasks')->where('request_id', $t->id)->where('id', $input['task_id'])->where('status', 'open')->first();
                        abort_unless($task, 409, 'Tugas terbuka tidak ditemukan.');
                        $target = User::findOrFail($input['assigned_to']);
                        abort_unless($this->member($target, $d->support_team_id) && (! $d->is_restricted || ItAccess::can($target, 'view_restricted_it')), 422, 'Pelaksana harus anggota tim dengan akses tiket.');
                        DB::table('it_ticket_tasks')->where('id', $task->id)->update(['assigned_to' => $target->id, 'updated_at' => now()]);
                        $metadata = ['task_id' => $task->id, 'previous_assignee' => $task->assigned_to, 'assigned_to' => $target->id];
                        break;
                    case 'complete_task':
                        abort_unless($staff, 403);
                        $this->state($d, ['queued', 'in_progress', 'pending']);
                        $this->reason($reason);
                        $task = DB::table('it_ticket_tasks')->where('request_id', $t->id)->where('id', $input['task_id'] ?? 0)->first();
                        abort_unless($task && $task->assigned_to === $actor->id && $task->status === 'open', 409, 'Tugas tidak tersedia untuk diselesaikan.');
                        DB::table('it_ticket_tasks')->where('id', $task->id)->update(['status' => 'done', 'result' => $reason, 'updated_at' => now()]);
                        $metadata['task_id'] = $task->id;
                        break;
                    case 'resolve':
                        abort_unless($staff && $t->assigned_user_id === $actor->id, 403);
                        $this->state($d, ['in_progress']);
                        $this->reason($reason);
                        abort_if(DB::table('it_ticket_tasks')->where('request_id', $t->id)->where('required', true)->where('status', 'open')->exists(), 409, 'Tugas wajib belum selesai.');
                        $d->status = 'resolved';
                        $d->resolution = $reason;
                        $d->resolved_at = now();
                        $t->resolved_at = now();
                        break;
                    case 'confirm':
                        abort_unless($owner, 403);
                        $this->state($d, ['resolved']);
                        $d->status = 'closed';
                        $d->closed_at = now();
                        $t->closed_at = now();
                        break;
                    case 'reopen':
                        abort_unless($owner, 403);
                        $this->state($d, ['resolved', 'closed']);
                        $this->reason($reason);
                        $since = $d->closed_at ?? $d->resolved_at;
                        abort_unless($since && now()->lessThanOrEqualTo(\Illuminate\Support\Carbon::parse($since)->addDays($cfg['profiles']['reopen_days'])), 409, 'Batas waktu reopen berakhir; buat tiket terkait.');
                        $d->status = $d->support_team_id ? 'queued' : 'triage';
                        $d->resolved_at = null;
                        $d->closed_at = null;
                        $d->resolution = null;
                        $t->resolved_at = null;
                        $t->closed_at = null;
                        break;
                    case 'cancel':
                        abort_unless($owner, 403);
                        $this->state($d, ['triage', 'queued', 'awaiting_approval', 'approval_exception', 'needs_revision']);
                        $this->reason($reason);
                        $d->status = 'cancelled';
                        $d->closed_at = now();
                        $t->closed_at = now();
                        DB::table('it_ticket_approvals')->where('request_id', $t->id)->where('cycle', $d->cycle)->whereIn('status', ['pending', 'exception'])->update(['status' => 'cancelled', 'updated_at' => now()]);
                        break;
                    case 'restrict':
                        abort_unless($coordinator && ItAccess::can($actor, 'view_restricted_it'), 403);
                        $this->reason($reason);
                        Validator::make($input, ['is_restricted' => 'required|boolean'])->validate();
                        $d->is_restricted = (bool) $input['is_restricted'];
                        break;
                    case 'retry_approval':
                        abort_unless($coordinator, 403);
                        $this->state($d, ['approval_exception']);
                        $this->reason($reason);
                        DB::table('it_ticket_approvals')->where('request_id', $t->id)->where('cycle', $d->cycle)->update(['status' => 'superseded', 'updated_at' => now()]);
                        $d->cycle++;
                        $this->route($d, $cfg);
                        $this->approval($t, $d, $cfg);
                        break;
                    case 'revise':
                        abort_unless($owner, 403);
                        $this->state($d, ['needs_revision']);
                        $this->reason($reason);
                        Validator::make($input, ['title' => 'required|string|max:150', 'description' => 'required|string|max:10000', 'answers' => 'present|array|max:60'])->validate();
                        $retained = $this->retainedUploads($t);
                        $visible = array_column($this->forms->inspect($cfg, $d->form_key, 'requester', $input['answers'])['fields'], 'key');
                        $retained = array_intersect_key($retained, array_flip($visible));
                        $allUploads = array_replace($retained, $files);
                        $d->form_answers = $this->answers($cfg, $d->form_key, $input['answers'], $allUploads);
                        $t->title = $input['title'];
                        $t->description = $input['description'];
                        $t->assigned_user_id = null;
                        $d->cycle++;
                        $this->route($d, $cfg);
                        $this->approval($t, $d, $cfg);
                        $this->uploads($t, $actor, $files, false, $paths, (int) $d->cycle);
                        break;
                    case 'respond':
                        abort_unless($staff, 403);
                        $input['is_internal'] = false;
                    case 'comment':
                        $this->state($d, array_values(array_diff(self::STATES, self::TERMINAL)));
                        $this->reason($reason);
                        Validator::make($input, ['is_internal' => 'required|boolean'])->validate();
                        $internal = (bool) $input['is_internal'];
                        abort_unless($staff || ($owner && ! $internal), 403);
                        DB::table('it_ticket_comments')->insert(['request_id' => $t->id, 'author_id' => $actor->id, 'body' => $reason, 'is_internal' => $internal, 'created_at' => now()]);
                        $metadata['internal'] = $internal;
                        break;
                    case 'attachment':
                        $this->state($d, array_values(array_diff(self::STATES, self::TERMINAL)));
                        Validator::make($input, ['is_internal' => 'required|boolean'])->validate();
                        $internal = (bool) $input['is_internal'];
                        abort_unless($staff || ($owner && ! $internal), 403);
                        Validator::make(['files' => $files['attachments'] ?? null], ['files' => 'required|array|min:1|max:5', 'files.*' => 'required|file|max:5120|mimes:'.implode(',', ConfigurationSchema::MIMES)])->validate();
                        $this->uploads($t, $actor, ['attachments' => $files['attachments']], $internal, $paths);
                        $metadata['internal'] = $internal;
                        break;
                    default: abort(404);
                }
                $d->lock_version++;
                $d->save();
                $t->status_id = $this->legacyStatus($d->status);
                $t->save();
                $this->event($t, $d, $actor, $action, $from, $reason, $metadata);

                return $t->fresh();
            }, 1);
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                Storage::disk('it_private')->delete($path);
            } throw $e;
        }
    }

    public function decide(User $actor, int $approvalId, array $input): Ticket
    {
        $data = Validator::make($input, ['lock_version' => 'required|integer|min:1', 'decision' => 'required|in:approved,rejected,revision', 'reason' => 'required|string|min:5|max:5000'])->validate();
        $candidate = DB::table('it_ticket_approvals')->where('id', $approvalId)->first();
        abort_unless($candidate, 404);

        return DB::transaction(function () use ($actor, $candidate, $data) {
            $t = Ticket::whereKey($candidate->request_id)->lockForUpdate()->firstOrFail();
            $d = TicketDetail::where('request_id', $t->id)->lockForUpdate()->firstOrFail();
            $approval = DB::table('it_ticket_approvals')->where('id', $candidate->id)->lockForUpdate()->first();
            abort_unless($approval->approver_id === $actor->id && $actor->id !== $t->requester_id, 403);
            abort_unless($approval->status === 'pending' && $approval->cycle === $d->cycle && $d->status === 'awaiting_approval' && (int) $d->lock_version === (int) $data['lock_version'], 409, 'Approval telah diputuskan atau tiket berubah.');
            DB::table('it_ticket_approvals')->where('id', $approval->id)->update(['status' => $data['decision'], 'reason' => $data['reason'], 'decided_at' => now(), 'updated_at' => now()]);
            $from = $d->status;
            $d->status = match ($data['decision']) {
                'approved' => $d->support_team_id ? 'queued' : 'triage', 'rejected' => 'rejected', 'revision' => 'needs_revision'
            };
            if ($data['decision'] === 'rejected') {
                $d->closed_at = now();
                $t->closed_at = now();
            }
            $d->lock_version++;
            $d->save();
            $t->status_id = $this->legacyStatus($d->status);
            $t->save();
            $this->event($t, $d, $actor, 'approval_'.$data['decision'], $from, $data['reason']);

            return $t->fresh();
        });
    }

    /** Purpose-limited approval view, never exposes comments, staff answers or attachment paths. */
    public function approvalView(User $actor, int $id): array
    {
        $a = DB::table('it_ticket_approvals')->where('id', $id)->first();
        abort_unless($a && $a->approver_id === $actor->id, 404);
        $t = Ticket::findOrFail($a->request_id);
        $d = $t->itDetail;

        return ['id' => $a->id, 'ticket_no' => $t->ticket_no, 'title' => $t->title, 'description' => $t->description,
            'requester' => $t->requester?->name, 'status' => $a->status, 'reason' => $a->reason, 'cycle' => $a->cycle,
            'lock_version' => $d->lock_version, 'answers' => $this->projectAnswers($d, 'approver')];
    }

    public function view(User $actor, Ticket $t): array
    {
        abort_unless(ItAccess::isIt($t) && ItAccess::canRead($actor, $t), 403);
        $d = $t->itDetail;
        $runtime = $d && $d->catalog_key && $d->configuration_version_id;
        $staff = $runtime && $this->staff($actor, $t, $d);
        $result = ['id' => $t->id, 'ticket_no' => $t->ticket_no, 'title' => $t->title, 'description' => $t->description,
            'created_at' => $t->created_at?->toIso8601String(), 'read_only' => ! $runtime, 'status' => $d?->status ?? 'legacy', 'staff' => (bool) $staff];
        if (! $runtime) {
            return $result;
        }
        $result += ['lock_version' => $d->lock_version, 'record_type' => $d->record_type, 'priority' => $d->priority,
            'impact' => $d->impact, 'urgency' => $d->urgency, 'support_team' => $d->supportTeam?->name, 'assigned_user' => $t->assignedUser?->name,
            'sla' => app(SlaEngine::class)->view($d),
            'configuration_version_id' => $d->configuration_version_id, 'form_key' => $d->form_key, 'cycle' => $d->cycle,
            'pending_reason' => $d->pending_reason, 'resolution' => $d->resolution,
            'answers' => $this->projectAnswers($d, $staff ? 'technician' : 'requester'), 'actions' => $this->actions($actor, $t, $d),
            'comments' => DB::table('it_ticket_comments')->where('request_id', $t->id)->when(! $staff, fn ($q) => $q->where('is_internal', false))->orderBy('id')->get(['id', 'author_id', 'body', 'is_internal', 'created_at'])->all(),
            'attachments' => DB::table('it_ticket_attachments')->where('request_id', $t->id)->when(! $staff, fn ($q) => $q->where('is_internal', false))->orderBy('id')->get(['id', 'original_name', 'mime', 'size', 'is_internal'])->all()];
        if ($staff) {
            if (app(SlaEngine::class)->available()) {
                $result['major'] = ['active' => (bool) $d->is_major, 'commander_id' => $d->major_commander_id, 'next_update_at' => $d->major_next_update_at, 'summary' => $d->major_summary, 'review' => $d->major_review];
                $result['links'] = DB::table('it_ticket_links')->where('parent_request_id', $t->id)->get()->filter(function ($link) use ($actor) {
                    $child = Ticket::find($link->child_request_id);

                    return $child && ItAccess::canRead($actor, $child);
                })->values()->all();
                $result['sla_events'] = DB::table('it_sla_events')->where('request_id', $t->id)->orderBy('id')->get(['action', 'metadata', 'created_at'])->all();
            }
            $result['tasks'] = DB::table('it_ticket_tasks')->where('request_id', $t->id)->get()->all();
            $result['events'] = DB::table('it_ticket_events')->where('request_id', $t->id)->orderBy('sequence')->get()->all();
            $result['approvals'] = DB::table('it_ticket_approvals')->where('request_id', $t->id)->get()->all();
        }

        return $result;
    }

    public function actions(User $actor, Ticket $t, TicketDetail $d): array
    {
        $actions = [];
        $state = $d->status;
        $staff = $this->staff($actor, $t, $d);
        $coord = $this->coordinator($actor, $t, $d);
        if (! in_array($state, self::TERMINAL, true) && ($staff || $t->requester_id === $actor->id)) {
            $actions = ['comment', 'attachment'];
        }
        if ($t->requester_id === $actor->id) {
            if ($state === 'resolved') {
                $actions[] = 'confirm';
            }
            if (in_array($state, ['resolved', 'closed'])) {
                $actions[] = 'reopen';
            }
            if ($state === 'needs_revision') {
                $actions[] = 'revise';
            }
            if (in_array($state, ['triage', 'queued', 'awaiting_approval', 'approval_exception', 'needs_revision'])) {
                $actions[] = 'cancel';
            }
        }
        if ($coord) {
            if (! in_array($state, array_merge(self::TERMINAL, ['resolved']), true)) {
                $actions[] = 'priority';
            }
            if (($d->configurationVersion->configuration['schema_version'] ?? 1) === 3 && $d->record_type === 'incident') {
                if (! $d->is_major && in_array($state, ['triage', 'queued', 'in_progress', 'pending'], true)) {
                    $actions[] = 'major_start';
                }
                if ($d->is_major) {
                    $actions = array_merge($actions, ['major_update', 'major_link']);
                    if (in_array($state, ['resolved', 'closed'], true)) {
                        $actions[] = 'major_end';
                    }
                }
            }
            if (in_array($state, ['triage', 'queued'])) {
                $actions[] = 'triage';
            }
            if (in_array($state, ['triage', 'queued', 'in_progress', 'pending'])) {
                $actions = array_merge($actions, ['assign', 'transfer']);
            }
            if (in_array($state, ['queued', 'in_progress', 'pending'])) {
                $actions[] = 'reassign_task';
            }
            if ($state === 'approval_exception') {
                $actions[] = 'retry_approval';
            }
            if (ItAccess::can($actor, 'view_restricted_it')) {
                $actions[] = 'restrict';
            }
        }
        if ($staff) {
            if (! in_array($state, self::TERMINAL, true)) {
                $actions[] = 'respond';
            }
            if ($d->is_major && $d->major_commander_id === $actor->id) {
                $actions[] = 'major_update';
            }
            if ($state === 'queued' && ! $t->assigned_user_id && $this->member($actor, $d->support_team_id)) {
                $actions[] = 'claim';
            }
            if ($t->assigned_user_id === $actor->id) {
                if ($state === 'queued') {
                    $actions[] = 'start';
                }
                if ($state === 'in_progress') {
                    $actions = array_merge($actions, ['pending', 'resolve']);
                }
                if ($state === 'pending') {
                    $actions[] = 'resume';
                }
            }
            if (in_array($state, ['queued', 'in_progress', 'pending'])) {
                $actions[] = 'complete_task';
            }
            if (in_array($state, ['queued', 'in_progress', 'pending']) && ($t->assigned_user_id === $actor->id || $coord)) {
                $actions[] = 'task';
            }
        }

        return array_values(array_unique($actions));
    }

    private function projectAnswers(TicketDetail $d, string $role): array
    {
        $cfg = $d->configurationVersion->configuration;
        $form = collect($cfg['forms'])->firstWhere('key', $d->form_key);
        $allowed = collect($form['fields'])->filter(fn ($f) => in_array($role, $f['visible_roles'], true) && $f['type'] !== 'file')->pluck('key')->all();

        return array_intersect_key($d->form_answers ?? [], array_flip($allowed));
    }

    public function attachment(User $actor, Ticket $ticket, int $id): object
    {
        abort_unless(ItAccess::isIt($ticket) && ItAccess::canRead($actor, $ticket), 403);
        $a = DB::table('it_ticket_attachments')->where('request_id', $ticket->id)->where('id', $id)->first();
        abort_unless($a && (! $a->is_internal || ($ticket->itDetail && $this->staff($actor, $ticket, $ticket->itDetail))), 404);

        return $a;
    }

    private function retainedUploads(Ticket $t): array
    {
        $files = [];
        $rows = DB::table('it_ticket_attachments')->where('request_id', $t->id)->where('is_internal', false)->whereNotNull('form_cycle')->orderBy('id')->get()->groupBy('field_key');
        foreach ($rows as $key => $attachments) {
            $latest = $attachments->max('form_cycle');
            foreach ($attachments->where('form_cycle', $latest) as $a) {
                $files[$key][] = new UploadedFile(Storage::disk('it_private')->path($a->path), $a->original_name, $a->mime, null, true);
            }
        }

        return $files;
    }

    private function uploads(Ticket $t, User $actor, array $uploads, bool $internal, array &$paths, ?int $cycle = null): void
    {
        foreach ($uploads as $key => $files) {
            foreach ((array) $files as $file) {
                $path = $file->store((string) $t->id, 'it_private');
                $paths[] = $path;
                DB::table('it_ticket_attachments')->insert(['request_id' => $t->id, 'uploaded_by' => $actor->id, 'field_key' => $key, 'form_cycle' => $cycle,
                    'original_name' => Str::limit(preg_replace('/[\x00-\x1F\x7F]/', '_', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 240, ''), 'path' => $path,
                    'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'is_internal' => $internal, 'created_at' => now()]);
            }
        }
    }

    private function state(TicketDetail $d, array $allowed): void
    {
        abort_unless(in_array($d->status, $allowed, true), 409, 'Aksi tidak sesuai status tiket.');
    }

    private function reason(string $reason): void
    {
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Alasan/keterangan minimal 5 karakter.']);
        }
    }

    private function legacyStatus(string $state): int
    {
        $names = match ($state) {
            'closed', 'cancelled', 'rejected' => ['Closed', 'Selesai'], 'resolved' => ['Resolved', 'Selesai'], 'in_progress','pending' => ['In Progress', 'On Progress', 'Dalam Proses'], default => ['Open']
        };
        $id = DB::table('statuses')->whereIn('name', $names)->orderBy('id')->value('id') ?? DB::table('statuses')->orderBy('id')->value('id');
        abort_unless($id, 503, 'Status dasar belum tersedia.');

        return (int) $id;
    }

    private function event(Ticket $t, TicketDetail $d, User $actor, string $action, ?string $from, string $reason, array $metadata = []): void
    {
        app(SlaEngine::class)->transition($t, $d, $action, $actor, $metadata);
        $eventId = DB::table('it_ticket_events')->insertGetId(['request_id' => $t->id, 'actor_id' => $actor->id, 'sequence' => $d->lock_version,
            'action' => $action, 'from_status' => $from, 'to_status' => $d->status, 'reason' => $reason ?: null, 'metadata' => json_encode($metadata), 'created_at' => now()]);
        // Transactional personal inbox: no broadcasts to GA/admin; each event/recipient is unique.
        $recipients = [];
        $internal = ($metadata['internal'] ?? false) || in_array($action, ['task', 'complete_task', 'reassign_task', 'restrict'], true);
        if (! $internal) {
            $recipients[] = $t->requester_id;
        }
        if ($t->assigned_user_id) {
            $assigned = User::find($t->assigned_user_id);
            if ($assigned && $this->staff($assigned, $t, $d)) {
                $recipients[] = $assigned->id;
            }
        }
        foreach (DB::table('support_team_members')->where('support_team_id', $d->support_team_id)->pluck('user_id') as $id) {
            $user = User::find($id);
            if ($user && $this->staff($user, $t, $d)) {
                $recipients[] = $id;
            }
        }
        if ($d->status === 'awaiting_approval') {
            $recipients[] = DB::table('it_ticket_approvals')->where('request_id', $t->id)->where('cycle', $d->cycle)->value('approver_id');
        }
        foreach (array_unique(array_filter($recipients)) as $id) {
            if ((int) $id !== $actor->id) {
                DB::table('it_ticket_notifications')->insert(['event_id' => $eventId, 'user_id' => $id, 'kind' => $internal ? 'internal' : 'update', 'created_at' => now()]);
            }
        }
    }
}
