<?php

namespace App\Services\It;

use App\Models\It\TicketDetail;
use App\Models\Master\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Call transition under the workflow's request lock; reconcile takes the same lock order. */
class SlaEngine
{
    public function __construct(private SlaCalendar $calendar) {}

    public function available(): bool
    {
        return Schema::hasTable('it_sla_clocks');
    }

    public function transition(Ticket $ticket, TicketDetail $detail, string $action, ?User $actor, array $metadata = []): void
    {
        if (! $this->available() || ($detail->configurationVersion?->configuration['schema_version'] ?? 1) !== 3) {
            return;
        }
        if (! $detail->sla_cycle) {
            $detail->sla_cycle = 1;
            $detail->save();
        }
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $clocks = DB::table('it_sla_clocks')->where('request_id', $ticket->id)->where('cycle', $detail->sla_cycle)->orderBy('id')->lockForUpdate()->get();
        if ($action === 'submitted') {
            $this->create($ticket, $detail, $ticket->created_at->toImmutable()->utc(), $now);

            return;
        }
        // No implicit adoption of pre-SLA tickets; cutover requires its own audited migration.
        if ($clocks->isEmpty()) {
            return;
        }
        if ($action === 'reopen') {
            $detail->sla_cycle++;
            $detail->save();
            $this->create($ticket, $detail, $now, $now, $clocks->keyBy('kind')->all());

            return;
        }
        $human = $actor && $actor->id !== $ticket->requester_id && app(TicketWorkflow::class)->staff($actor, $ticket, $detail)
            && (($action === 'comment' && ! ($metadata['internal'] ?? false)) || $action === 'respond' || $action === 'major_update' || $action === 'resolve');
        foreach ($clocks as $clock) {
            $before = $this->summary($clock);
            $this->account($clock, $now);
            $this->due($clock, $now);
            if ($clock->consumed_seconds + $clock->carried_seconds > $clock->target_seconds) {
                $this->evaluate($ticket, $detail, $clock, $now);
            }
            $snapshot = json_decode($clock->snapshot, true, 512, JSON_THROW_ON_ERROR);
            if ($clock->priority !== $detail->priority && in_array($clock->state, ['running', 'paused'], true)) {
                // Evaluate the old promise first; relaxing priority cannot erase a breach.
                $this->evaluate($ticket, $detail, $clock, $now);
                $clock->priority = $detail->priority;
                $clock->target_seconds = (int) $snapshot['policy']['targets'][$detail->priority][$clock->kind] * 60;
            }
            if ($clock->kind === 'response' && $human && in_array($clock->state, ['running', 'paused'], true)) {
                $clock->state = 'completed';
                $clock->completed_at = $now->toDateTimeString();
                $clock->pause_reason = null;
            }
            if (in_array($detail->status, ['cancelled', 'rejected'], true) && in_array($clock->state, ['running', 'paused'], true)) {
                $clock->state = 'cancelled';
                $clock->completed_at = $now->toDateTimeString();
            } elseif (in_array($detail->status, ['resolved', 'closed'], true) && in_array($clock->state, ['running', 'paused'], true)) {
                $clock->state = $clock->kind === 'response' ? 'unanswered' : 'completed';
                $clock->completed_at = $now->toDateTimeString();
                $clock->pause_reason = null;
            } elseif (in_array($clock->state, ['running', 'paused'], true)) {
                $pause = $detail->status === 'pending' ? $detail->pending_code : (in_array($detail->status, ['awaiting_approval', 'needs_revision'], true) ? $detail->status : null);
                $allowed = $snapshot['policy']['pause'][$clock->kind];
                $clock->state = $pause && in_array($pause, $allowed, true) ? 'paused' : 'running';
                $clock->pause_reason = $clock->state === 'paused' ? $pause : null;
            }
            if (in_array($clock->state, ['completed', 'unanswered'], true)) {
                $state = $clock->state;
                $clock->state = 'running';
                $this->due($clock, $now);
                $clock->state = $state;
            } else {
                $this->due($clock, $now);
            }
            $this->evaluate($ticket, $detail, $clock, $now);
            $this->save($clock, $now);
            $after = $this->summary($clock);
            if ($before['state'] !== $after['state'] || $before['target_seconds'] !== $after['target_seconds']) {
                $this->audit($ticket->id, $clock->id, 'clock:'.$clock->id.':action:'.$detail->lock_version, 'clock_changed', ['trigger' => $action, 'before' => $before, 'after' => $after], $actor?->id);
            }
        }
    }

    private function create(Ticket $ticket, TicketDetail $detail, CarbonImmutable $start, CarbonImmutable $now, array $previous = []): void
    {
        $cfg = $detail->configurationVersion->configuration;
        $sla = $cfg['profiles']['sla'];
        $selected = null;
        foreach ($sla['policies'] as $p) {
            if (app(OperationalProfile::class)->matches($p, $detail->getAttributes())) {
                $selected = $p;
                break;
            }
        }
        $selected ??= collect($sla['policies'])->firstWhere('key', $sla['fallback'][$detail->record_type]);
        abort_unless($selected, 409, 'Kebijakan SLA untuk tiket tidak ditemukan.');
        $cal = collect($sla['calendars'])->firstWhere('key', $selected['calendar_key']);
        $service = collect($cfg['services'])->firstWhere('key', $detail->service_key);
        $snapshot = ['configuration_version_id' => $detail->configuration_version_id, 'service_owner_id' => $service['owner_id'] ?? null, 'policy' => $selected, 'calendar' => $cal, 'reopen_budget' => $sla['reopen_budget']];
        foreach (['response', $detail->record_type === 'incident' ? 'restore' : 'fulfil'] as $kind) {
            $old = $previous[$kind] ?? null;
            $carry = $kind !== 'response' && $old && $sla['reopen_budget'] === 'remaining' ? (int) $old->carried_seconds + (int) $old->consumed_seconds : 0;
            $pause = $detail->status === 'pending' ? $detail->pending_code : (in_array($detail->status, ['awaiting_approval', 'needs_revision'], true) ? $detail->status : null);
            $paused = $pause && in_array($pause, $selected['pause'][$kind], true);
            $target = (int) $selected['targets'][$detail->priority][$kind] * 60;
            $used = $paused ? 0 : $this->calendar->seconds($cal, $start, $now);
            $id = DB::table('it_sla_clocks')->insertGetId(['request_id' => $ticket->id, 'cycle' => $detail->sla_cycle, 'kind' => $kind, 'previous_clock_id' => $old?->id,
                'snapshot' => json_encode($snapshot), 'snapshot_checksum' => ConfigurationStore::checksum($snapshot), 'priority' => $detail->priority, 'target_seconds' => $target,
                'carried_seconds' => $carry, 'consumed_seconds' => $used, 'state' => $paused ? 'paused' : 'running', 'pause_reason' => $paused ? $pause : null,
                'started_at' => $start->toDateTimeString(), 'accounted_at' => $now->toDateTimeString(), 'due_at' => $paused ? null : $this->calendar->deadline($cal, $now, max(0, $target - $carry - $used))->toDateTimeString(), 'created_at' => $now, 'updated_at' => $now]);
            if ($used > 0) {
                $this->span($id, $start, $now, $used);
            }
            $clock = DB::table('it_sla_clocks')->find($id);
            $this->audit($ticket->id, $id, 'clock:'.$id.':started', 'clock_started', ['snapshot_checksum' => ConfigurationStore::checksum($snapshot), 'previous_clock_id' => $old?->id, 'carried_seconds' => $carry, 'deadline' => $clock->due_at]);
            $this->evaluate($ticket, $detail, $clock, $now);
            $this->save($clock, $now);
        }
    }

    private function account(object $clock, CarbonImmutable $now): void
    {
        $at = $this->calendar->instant($clock->accounted_at);
        if ($now->lessThan($at)) {
            return;
        }
        if ($clock->state === 'running') {
            $seconds = $this->calendar->seconds(json_decode($clock->snapshot, true)['calendar'], $at, $now);
            $clock->consumed_seconds += $seconds;
            if ($seconds > 0) {
                $this->span($clock->id, $at, $now, $seconds);
            }
        }
        if (in_array($clock->state, ['running', 'paused'], true)) {
            $clock->accounted_at = $now->toDateTimeString();
        }
    }

    private function span(int $id, CarbonImmutable $from, CarbonImmutable $to, int $seconds): void
    {
        $last = DB::table('it_sla_spans')->where('clock_id', $id)->orderByDesc('id')->first();
        if ($last && $last->to_at === $from->toDateTimeString()) {
            DB::table('it_sla_spans')->where('id', $last->id)->update(['to_at' => $to->toDateTimeString(), 'consumed_seconds' => $last->consumed_seconds + $seconds]);
        } else {
            DB::table('it_sla_spans')->insert(['clock_id' => $id, 'from_at' => $from->toDateTimeString(), 'to_at' => $to->toDateTimeString(), 'consumed_seconds' => $seconds]);
        }
    }

    private function due(object $clock, CarbonImmutable $now): void
    {
        if ($clock->state === 'running') {
            $cal = json_decode($clock->snapshot, true)['calendar'];
            $remaining = $clock->target_seconds - $clock->carried_seconds - $clock->consumed_seconds;
            if ($remaining > 0) {
                $clock->due_at = $this->calendar->deadline($cal, $now, $remaining)->toDateTimeString();
            } else {
                $budget = max(0, $clock->target_seconds - $clock->carried_seconds);
                $clock->due_at = $clock->started_at;
                foreach (DB::table('it_sla_spans')->where('clock_id', $clock->id)->orderBy('id')->get() as $span) {
                    if ($budget <= $span->consumed_seconds) {
                        $clock->due_at = $this->calendar->deadline($cal, $span->from_at, $budget)->toDateTimeString();
                        break;
                    }$budget -= $span->consumed_seconds;
                }
            }
        } elseif ($clock->state === 'paused') {
            $clock->due_at = null;
        }
    }

    private function evaluate(Ticket $ticket, TicketDetail $detail, object $clock, CarbonImmutable $now): void
    {
        if ($clock->state === 'cancelled') {
            return;
        }
        $used = (int) $clock->carried_seconds + (int) $clock->consumed_seconds;
        $target = (int) $clock->target_seconds;
        $p = json_decode($clock->snapshot, true)['policy'];
        if ($used > $target && ! $clock->breached_at) {
            $clock->breached_at = $clock->due_at ?? $now->toDateTimeString();
            $this->audit($ticket->id, $clock->id, 'clock:'.$clock->id.':breach', 'breached', ['due_at' => $clock->breached_at, 'detected_at' => $now->toDateTimeString(), 'consumed_seconds' => $used, 'target_seconds' => $target, 'priority' => $clock->priority]);
        }
        if (! in_array($clock->state, ['running', 'paused'], true) && $used <= $target) {
            return;
        }
        if ($used * 100 >= $target * (int) $p['warning_percent']) {
            $this->audit($ticket->id, $clock->id, 'clock:'.$clock->id.':warning', 'warning', ['percent' => $p['warning_percent'], 'detected_at' => $now->toDateTimeString()]);
        }
        foreach ($p['escalations'] as $level) {
            $threshold = (int) ceil($target * (int) $level['at_percent'] / 100);
            if ($used < $threshold) {
                continue;
            }
            $repeat = (int) ($level['repeat_minutes'] ?? 0) * 60;
            $slot = $repeat > 0 ? intdiv(max(0, $used - $threshold), $repeat) : 0;
            $event = $this->audit($ticket->id, $clock->id, 'clock:'.$clock->id.':escalation:'.$level['key'].':'.$slot, 'escalated', ['level' => $level['key'], 'slot' => $slot, 'at_percent' => $level['at_percent'], 'detected_at' => $now->toDateTimeString(), 'due_at' => $clock->due_at]);
            if ($event) {
                $this->notify($ticket, $detail, $event, $level['recipients'], $level['user_ids'] ?? []);
            }
        }
    }

    private function save(object $c, CarbonImmutable $now): void
    {
        DB::table('it_sla_clocks')->where('id', $c->id)->update(array_intersect_key((array) $c, array_flip(['priority', 'target_seconds', 'carried_seconds', 'consumed_seconds', 'state', 'pause_reason', 'accounted_at', 'due_at', 'completed_at', 'breached_at'])) + ['updated_at' => $now]);
    }

    private function summary(object $c): array
    {
        return array_intersect_key((array) $c, array_flip(['state', 'priority', 'target_seconds', 'consumed_seconds', 'carried_seconds', 'due_at', 'breached_at', 'pause_reason']));
    }

    public function audit(int $request, ?int $clock, string $key, string $action, array $data, ?int $actor = null): ?int
    {
        if (DB::table('it_sla_events')->where('event_key', $key)->exists()) {
            return null;
        }

        return DB::table('it_sla_events')->insertGetId(['request_id' => $request, 'clock_id' => $clock, 'actor_id' => $actor, 'event_key' => $key, 'action' => $action, 'metadata' => json_encode($data), 'created_at' => CarbonImmutable::now('UTC')]);
    }

    public function notify(Ticket $ticket, TicketDetail $detail, int $event, array $roles, array $explicit = []): void
    {
        $snapshot = DB::table('it_sla_clocks')->where('request_id', $ticket->id)->orderByDesc('id')->value('snapshot');
        $owner = $snapshot ? json_decode($snapshot, true)['service_owner_id'] : null;
        $ids = $explicit;
        if (in_array('assignee', $roles, true) && $ticket->assigned_user_id) {
            $ids[] = $ticket->assigned_user_id;
        }
        if (in_array('service_owner', $roles, true) && $owner) {
            $ids[] = $owner;
        }
        if (in_array('coordinators', $roles, true)) {
            $ids = array_merge($ids, DB::table('support_team_members')->where('support_team_id', $detail->support_team_id)->where('role', 'coordinator')->pluck('user_id')->all());
        }
        foreach (array_unique($ids) as $id) {
            $user = User::find($id);
            if (! $user || ! app(TicketWorkflow::class)->staff($user, $ticket, $detail)) {
                continue;
            }
            DB::table('it_delivery_outbox')->insertOrIgnore(['delivery_key' => 'sla:'.$event.':user:'.$id, 'request_id' => $ticket->id, 'user_id' => $id, 'sla_event_id' => $event, 'status' => 'pending', 'created_at' => CarbonImmutable::now('UTC'), 'updated_at' => CarbonImmutable::now('UTC')]);
        }
    }

    public function reconcileTicket(int $id): void
    {
        DB::transaction(function () use ($id) {
            $ticket = Ticket::whereKey($id)->lockForUpdate()->first();
            if (! $ticket) {
                return;
            }
            $d = TicketDetail::where('request_id', $id)->lockForUpdate()->first();
            if (! $d || ! $d->catalog_key) {
                return;
            }
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            foreach (DB::table('it_sla_clocks')->where('request_id', $id)->where('cycle', $d->sla_cycle)->whereIn('state', ['running', 'paused'])->orderBy('id')->lockForUpdate()->get() as $c) {
                // Check using the previously persisted deadline before projecting a new one.
                $this->account($c, $now);
                $this->evaluate($ticket, $d, $c, $now);
                $this->due($c, $now);
                $this->save($c, $now);
            }
            if ($d->is_major && $d->major_next_update_at && $this->calendar->instant($d->major_next_update_at)->lessThanOrEqualTo($now)) {
                $event = $this->audit($id, null, 'major:'.$id.':due:'.str_replace([' ', ':'], '_', $d->major_next_update_at), 'communication_overdue', ['next_update_at' => $d->major_next_update_at]);
                if ($event) {
                    $this->notify($ticket, $d, $event, ['coordinators'], array_filter([$d->major_commander_id]));
                }
            }
        }, 3);
    }

    public function view(TicketDetail $detail): array
    {
        if (! $this->available()) {
            return [];
        }

        return DB::table('it_sla_clocks')->where('request_id', $detail->request_id)->orderBy('cycle')->orderBy('id')->get()->map(function ($c) {
            $snapshot = json_decode($c->snapshot, true);
            $used = $c->consumed_seconds + ($c->state === 'running' ? $this->calendar->seconds($snapshot['calendar'], $c->accounted_at, CarbonImmutable::now('UTC')) : 0);

            return ['id' => $c->id, 'cycle' => $c->cycle, 'kind' => $c->kind, 'state' => $c->state, 'target_seconds' => $c->target_seconds, 'consumed_seconds' => $used, 'carried_seconds' => $c->carried_seconds,
                'remaining_seconds' => max(0, $c->target_seconds - $c->carried_seconds - $used), 'due_at' => $c->due_at, 'breached_at' => $c->breached_at, 'completed_at' => $c->completed_at, 'pause_reason' => $c->pause_reason, 'previous_clock_id' => $c->previous_clock_id];
        })->all();
    }
}
