<?php

namespace App\Services\It;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Support\ItAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Internal Problem and Change records always retain team ownership and a separate audit trail. */
class PracticeService
{
    public const TYPES = ['problem', 'change', 'knowledge', 'asset'];

    private const TRANSITIONS = [
        'problem' => ['identified' => ['assessment'], 'assessment' => ['investigation'], 'investigation' => ['known_error', 'remediation'], 'known_error' => ['remediation'], 'remediation' => ['review'], 'review' => ['closed', 'investigation']],
        'asset' => ['draft' => ['active'], 'active' => ['retired']],
        'knowledge' => ['draft' => ['review'], 'review' => ['published', 'draft'], 'published' => ['retired']],
        'change' => ['draft' => ['assessment'], 'assessment' => ['authorized', 'rejected'], 'authorized' => ['scheduled', 'cancelled'], 'scheduled' => ['implementing', 'cancelled'], 'implementing' => ['review'], 'review' => ['closed']],
    ];

    public function teamMember(User $user, int $team): bool
    {
        return DB::table('support_team_members')->join('support_teams', 'support_teams.id', '=', 'support_team_members.support_team_id')
            ->where('support_team_id', $team)->where('user_id', $user->id)->where('support_teams.is_active', true)->whereIn('role', ['coordinator', 'technician'])->exists();
    }

    public function canRead(User $user, object $record): bool
    {
        return $this->teamMember($user, $record->support_team_id) && (! $record->is_restricted || ItAccess::can($user, 'view_restricted_it'));
    }

    public function canManage(User $user, object $record): bool
    {
        return $this->canRead($user, $record) && ItAccess::can($user, 'manage_it_practices');
    }

    public function create(User $actor, array $input): object
    {
        abort_unless(ItAccess::can($actor, 'manage_it_practices'), 403);
        $data = Validator::make($input, ['type' => 'required|in:problem,change,knowledge,asset', 'title' => 'required|string|max:200', 'description' => 'required|string|max:10000',
            'support_team_id' => 'required|integer|exists:support_teams,id', 'owner_id' => 'required|integer|exists:users,id', 'is_restricted' => 'required|boolean', 'reason' => 'required|string|min:5|max:5000'])->validate();
        abort_unless($this->teamMember($actor, (int) $data['support_team_id']) && $this->teamMember(User::findOrFail($data['owner_id']), (int) $data['support_team_id']), 403);
        abort_if($data['is_restricted'] && (! ItAccess::can($actor, 'view_restricted_it') || ! ItAccess::can(User::findOrFail($data['owner_id']), 'view_restricted_it')), 403);

        return DB::transaction(function () use ($actor, $data) {
            $now = now();
            $status = $data['type'] === 'problem' ? 'identified' : 'draft';
            $id = DB::table('it_practice_records')->insertGetId(['type' => $data['type'], 'number' => strtoupper(substr($data['type'], 0, 3)).'-'.now()->format('Ymd').'-'.Str::upper(Str::random(12)),
                'title' => $data['title'], 'description' => $data['description'], 'support_team_id' => $data['support_team_id'], 'owner_id' => $data['owner_id'], 'is_restricted' => $data['is_restricted'],
                'status' => $status, 'lock_version' => 1, 'data' => '{}', 'created_at' => $now, 'updated_at' => $now]);
            $this->event($id, $actor, 1, 'created', null, $status, $data['reason'], []);

            return DB::table('it_practice_records')->find($id);
        });
    }

    public function action(User $actor, int $id, string $action, array $input): object
    {
        $data = Validator::make($input, ['lock_version' => 'required|integer|min:1', 'reason' => 'required|string|min:5|max:5000'])->validate();

        return DB::transaction(function () use ($actor, $id, $action, $input, $data) {
            $record = DB::table('it_practice_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($this->canManage($actor, $record), 403);
            abort_unless($record->lock_version === (int) $data['lock_version'], 409, 'Catatan berubah; muat ulang.');
            $fields = json_decode($record->data, true, 512, JSON_THROW_ON_ERROR);
            $from = $record->status;
            $to = $from;
            $metadata = [];
            if ($action === 'update') {
                if (in_array($record->type, ['change', 'knowledge', 'asset'], true)) {
                    abort_unless($actor->id === (int) $record->owner_id, 403, 'Hanya owner dapat mengubah isi rencana atau artikel.');
                }
                $rules = match ($record->type) {
                    'problem' => ['root_cause' => 'nullable|string|max:10000', 'workaround' => 'nullable|string|max:10000', 'prevention' => 'nullable|string|max:10000'],
                    'asset' => ['asset_tag' => 'required|string|max:100', 'ci_type' => 'required|in:device,network,server,application,service,other', 'service_key' => 'required|string|max:50', 'location' => 'nullable|string|max:200', 'serial_number' => 'nullable|string|max:200', 'supplier' => 'nullable|string|max:200'],
                    'knowledge' => ['article' => 'required|string|min:20|max:20000', 'review_date' => 'required|date|after:today'],
                    'change' => ['change_type' => 'required|in:standard,normal,emergency', 'risk' => 'required|in:low,medium,high,critical', 'implementation_plan' => 'required|string|max:10000', 'test_plan' => 'required|string|max:10000', 'rollback_plan' => 'required|string|max:10000', 'scheduled_start' => 'nullable|date', 'scheduled_end' => 'nullable|date|after:scheduled_start', 'authority_id' => 'required|integer|exists:users,id'],
                };
                if ($record->type === 'knowledge') {
                    abort_unless($from === 'draft', 409, 'Artikel dalam review/terpublikasi tidak dapat diubah.');
                }
                if ($record->type === 'change') {
                    abort_unless(in_array($from, ['draft', 'assessment'], true), 409, 'Rencana terotorisasi tidak dapat diubah; buat revisi.');
                }
                $fields = array_replace($fields, Validator::make($input, $rules)->validate());
                if ($record->type === 'asset') {
                    $set = DB::table('it_configuration_sets')->where('code', 'it')->lockForUpdate()->first();
                    $versionId = $set?->active_version_id;
                    abort_if($record->status === 'retired', 409, 'Aset yang dinonaktifkan tidak dapat diubah.');
                    $config = \App\Models\It\ConfigurationVersion::find($versionId)?->configuration;
                    abort_unless($config && collect($config['services'])->contains(fn ($service) => $service['key'] === $fields['service_key'] && $service['is_active']), 422, 'Layanan aktif tidak ditemukan pada konfigurasi aktif.');
                    $duplicate = DB::table('it_practice_records')->where('type', 'asset')->where('id', '!=', $id)->where('data->asset_tag', $fields['asset_tag'])->exists();
                    abort_if($duplicate, 409, 'Kode aset sudah dipakai.');
                }
                if ($record->type === 'change') {
                    $authority = User::findOrFail($fields['authority_id']);
                    abort_unless($this->teamMember($authority, $record->support_team_id) && ItAccess::can($authority, 'authorize_it_change') && (! $record->is_restricted || ItAccess::can($authority, 'view_restricted_it')), 422, 'Authority harus memiliki hak dan akses tim.');
                }
                $metadata = ['updated_fields' => array_keys($fields)];
            } elseif ($action === 'transition') {
                $next = Validator::make($input, ['status' => 'required|string|max:30'])->validate()['status'];
                abort_unless(in_array($next, self::TRANSITIONS[$record->type][$from] ?? [], true), 409, 'Transisi tidak diizinkan.');
                if ($record->type === 'problem') {
                    if ($next === 'known_error') {
                        abort_unless(! empty($fields['workaround']), 422, 'Known error memerlukan workaround.');
                    }
                    if ($next === 'review' || $next === 'closed') {
                        abort_unless(! empty($fields['root_cause']) && ! empty($fields['prevention']), 422, 'RCA dan tindakan pencegahan wajib.');
                    }
                } elseif ($record->type === 'asset') {
                    if ($next === 'active') {
                        abort_unless(! empty($fields['asset_tag']) && ! empty($fields['service_key']) && ! empty($fields['ci_type']), 422, 'Isi data aset/CI sebelum aktivasi.');
                    }
                } elseif ($record->type === 'knowledge') {
                    if ($next === 'review') {
                        abort_unless(! empty($fields['article']) && ! empty($fields['review_date']), 422, 'Artikel dan jadwal review wajib.');
                    }
                    if ($next === 'published') {
                        abort_unless(ItAccess::can($actor, 'publish_it_knowledge') && $actor->id !== (int) $record->owner_id, 403, 'Publikasi memerlukan reviewer terpisah.');
                        abort_unless(DB::table('it_practice_links')->where('record_id', $id)->where('type', 'change_knowledge')->exists(), 422, 'Artikel harus terkait Change yang direview.');
                        $fields['published_by'] = $actor->id;
                        $fields['published_at'] = now()->toIso8601String();
                    }
                    if ($next === 'retired') {
                        abort_unless(ItAccess::can($actor, 'publish_it_knowledge'), 403);
                    }
                } else {
                    foreach (['change_type', 'risk', 'implementation_plan', 'test_plan', 'rollback_plan', 'authority_id'] as $key) {
                        abort_unless(! empty($fields[$key]), 422, 'Lengkapi jenis, risiko, rencana, uji, rollback dan authority.');
                    }
                    if (in_array($next, ['authorized', 'rejected'], true)) {
                        abort_unless($actor->id === (int) $fields['authority_id'] && $actor->id !== (int) $record->owner_id && ItAccess::can($actor, 'authorize_it_change'), 403, 'Keputusan harus oleh authority terpisah.');
                    }
                    if ($next === 'scheduled') {
                        abort_unless(! empty($fields['scheduled_start']) && ! empty($fields['scheduled_end']), 422, 'Jadwal perubahan wajib.');
                    }
                    if ($next === 'review') {
                        $result = Validator::make($input, ['result' => 'required|in:success,failed,rolled_back', 'test_result' => 'required|string|min:5|max:10000', 'actual_result' => 'required|string|min:5|max:10000'])->validate();
                        $fields = array_replace($fields, $result);
                    }
                    if ($next === 'closed') {
                        $review = Validator::make($input, ['review' => 'required|string|min:5|max:10000'])->validate();
                        $fields = array_replace($fields, $review);
                    }
                }$to = $next;
            } elseif ($action === 'link_ticket') {
                $target = Validator::make($input, ['request_id' => 'required|integer|exists:requests,id'])->validate()['request_id'];
                $ticket = Ticket::findOrFail($target);
                abort_unless(ItAccess::isIt($ticket) && ItAccess::canRead($actor, $ticket) && $ticket->itDetail?->record_type === 'incident', 403);
                abort_if($ticket->itDetail?->is_restricted && ! $record->is_restricted, 422, 'Problem harus rahasia untuk menghubungkan incident rahasia.');
                abort_unless($record->type === 'problem', 422);
                abort_if(DB::table('it_practice_links')->where('record_id', $id)->where('request_id', $ticket->id)->where('type', 'incident_problem')->exists(), 409, 'Hubungan sudah ada.');
                DB::table('it_practice_links')->insert(['record_id' => $id, 'request_id' => $ticket->id, 'type' => 'incident_problem', 'created_by' => $actor->id, 'reason' => $data['reason'], 'created_at' => now()]);
                $metadata = ['relation' => 'incident_problem'];
            } elseif ($action === 'link_problem') {
                abort_unless($record->type === 'change', 422);
                $target = Validator::make($input, ['problem_id' => 'required|integer|exists:it_practice_records,id'])->validate()['problem_id'];
                $problem = DB::table('it_practice_records')->where('id', $target)->first();
                abort_unless($problem && $problem->type === 'problem' && $this->canRead($actor, $problem), 403);
                abort_if($problem->is_restricted && ! $record->is_restricted, 422, 'Change harus rahasia untuk menghubungkan Problem rahasia.');
                abort_if(DB::table('it_practice_links')->where('record_id', $id)->where('related_record_id', $problem->id)->where('type', 'problem_change')->exists(), 409, 'Hubungan sudah ada.');
                DB::table('it_practice_links')->insert(['record_id' => $id, 'related_record_id' => $problem->id, 'type' => 'problem_change', 'created_by' => $actor->id, 'reason' => $data['reason'], 'created_at' => now()]);
                $metadata = ['relation' => 'problem_change'];
            } elseif ($action === 'link_asset') {
                abort_unless(in_array($record->type, ['problem', 'change'], true), 422);
                $target = Validator::make($input, ['asset_id' => 'required|integer|exists:it_practice_records,id'])->validate()['asset_id'];
                $asset = DB::table('it_practice_records')->find($target);
                abort_unless($asset && $asset->type === 'asset' && $this->canRead($actor, $asset), 403);
                abort_if($asset->is_restricted && ! $record->is_restricted, 422, 'Catatan harus rahasia untuk menghubungkan aset rahasia.');
                abort_if(DB::table('it_practice_links')->where('record_id', $id)->where('related_record_id', $asset->id)->where('type', 'related_asset')->exists(), 409, 'Hubungan sudah ada.');
                DB::table('it_practice_links')->insert(['record_id' => $id, 'related_record_id' => $asset->id, 'type' => 'related_asset', 'created_by' => $actor->id, 'reason' => $data['reason'], 'created_at' => now()]);
                $metadata = ['relation' => 'related_asset'];
            } elseif ($action === 'link_change') {
                abort_unless($record->type === 'knowledge' && $record->status === 'draft', 422);
                $target = Validator::make($input, ['change_id' => 'required|integer|exists:it_practice_records,id'])->validate()['change_id'];
                $change = DB::table('it_practice_records')->find($target);
                abort_unless($change && $change->type === 'change' && $change->status === 'closed' && $this->canRead($actor, $change), 403);
                abort_if($change->is_restricted && ! $record->is_restricted, 422, 'Artikel harus rahasia untuk menghubungkan Change rahasia.');
                abort_if(DB::table('it_practice_links')->where('record_id', $id)->where('related_record_id', $change->id)->where('type', 'change_knowledge')->exists(), 409, 'Hubungan sudah ada.');
                DB::table('it_practice_links')->insert(['record_id' => $id, 'related_record_id' => $change->id, 'type' => 'change_knowledge', 'created_by' => $actor->id, 'reason' => $data['reason'], 'created_at' => now()]);
                $metadata = ['relation' => 'change_knowledge'];
            } else {
                abort(404);
            }
            DB::table('it_practice_records')->where('id', $id)->update(['status' => $to, 'data' => json_encode($fields), 'lock_version' => $record->lock_version + 1, 'updated_at' => now()]);
            $this->event($id, $actor, $record->lock_version + 1, $action, $from, $to, $data['reason'], $metadata);

            return DB::table('it_practice_records')->find($id);
        });
    }

    private function event(int $id, User $actor, int $sequence, string $action, ?string $from, string $to, string $reason, array $metadata): void
    {
        DB::table('it_practice_events')->insert(['record_id' => $id, 'actor_id' => $actor->id, 'sequence' => $sequence, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'metadata' => json_encode($metadata), 'created_at' => now()]);
    }

    public function view(User $user, int $id): array
    {
        $record = DB::table('it_practice_records')->where('id', $id)->first();
        abort_unless($record, 404);
        abort_unless($this->canRead($user, $record), 403);
        $links = DB::table('it_practice_links')->where(fn ($q) => $q->where('record_id', $id)->orWhere('related_record_id', $id))->orderBy('id')->get()->filter(function ($l) use ($user, $id) {
            if ($l->request_id) {
                $t = Ticket::find($l->request_id);

                return $t && ItAccess::canRead($user, $t);
            }
            $other = DB::table('it_practice_records')->find($l->record_id === $id ? $l->related_record_id : $l->record_id);

            return $other && $this->canRead($user, $other);
        })->values()->all();

        return ['record' => $record, 'data' => json_decode($record->data, true), 'links' => $links, 'events' => DB::table('it_practice_events')->where('record_id', $id)->orderBy('sequence')->get()->all()];
    }
}
