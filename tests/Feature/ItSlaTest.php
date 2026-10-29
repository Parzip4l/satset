<?php

namespace Tests\Feature;

use App\Jobs\It\DeliverOperationNotification;
use App\Jobs\It\WorkerHeartbeat;
use App\Models\Master\Ticket;
use App\Services\It\ConfigurationStore;
use App\Services\It\OperationDelivery;
use App\Services\It\OperationHealth;
use App\Services\It\SlaCalendar;
use App\Services\It\SlaEngine;
use App\Services\It\SlaProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ItSlaTest extends TestCase
{
    use \Tests\Support\BuildsItWorkflow { setUp as workflowSetUp; }

    protected function setUp(): void
    {
        $this->workflowSetUp();
        (require database_path('migrations/2026_10_28_000005_create_it_sla_operations.php'))->up();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Konfigurasi SLA pengujian');
        $cfg = $v->configuration;
        $cfg['schema_version'] = 3;
        $cfg['profiles']['sla'] = self::policy();
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Simpan SLA pengujian');
        $this->version = $store->activate($v->id, 1, $v->lock_version, $this->manager, 'Publikasi SLA pengujian');
        Queue::fake();
    }

    public static function policy(): array
    {
        $sla = SlaProfile::draft();
        $sla['calendars'][0]['mode'] = '24x7';
        $sla['calendars'][0]['key'] = 'always';
        $sla['calendars'][0]['name'] = '24x7 test';
        foreach (['incident', 'service_request'] as $type) {
            $kind = $type === 'incident' ? 'restore' : 'fulfil';
            $targets = [];
            foreach (['P1' => [30, 120], 'P2' => [60, 240], 'P3' => [120, 480], 'P4' => [240, 960]] as $p => $values) {
                $targets[$p] = ['response' => $values[0], $kind => $values[1]];
            }
            $sla['policies'][] = ['key' => $type, 'name' => 'Test '.$type, 'record_type' => $type, 'calendar_key' => 'always', 'targets' => $targets, 'pause' => ['response' => [], 'restore' => ['waiting_customer'], 'fulfil' => ['waiting_customer', 'awaiting_approval', 'needs_revision']],
                'warning_percent' => 80, 'escalations' => [['key' => 'before_due', 'at_percent' => 80, 'repeat_minutes' => null, 'recipients' => ['coordinators']], ['key' => 'breached', 'at_percent' => 100, 'repeat_minutes' => 60, 'recipients' => ['coordinators']]]];
            $sla['fallback'][$type] = $type;
        }

        return $sla;
    }

    private function clock(Ticket $ticket, string $kind = 'restore', int $cycle = 1): ?object
    {
        return DB::table('it_sla_clocks')->where('request_id', $ticket->id)->where('kind', $kind)->where('cycle', $cycle)->first();
    }

    private function sweep(Ticket $ticket): void
    {
        app(SlaEngine::class)->reconcileTicket($ticket->id);
    }

    private function calendar(): array
    {
        return SlaProfile::draft()['calendars'][0];
    }

    public function test_business_calendar_crosses_weekend_holiday_and_uses_jakarta_timezone(): void
    {
        $c = $this->calendar();
        $c['holidays'] = ['2026-10-26'];
        $calendar = app(SlaCalendar::class);
        $deadline = $calendar->deadline($c, '2026-10-23 09:30:00', 7200); // Friday 16:30 Jakarta, 30 minutes left.
        $this->assertSame('2026-10-27 02:30:00', $deadline->toDateTimeString());
        $this->assertSame(7200, $calendar->seconds($c, '2026-10-23 09:30:00', $deadline));
    }

    public function test_calendar_date_exception_overrides_holiday_and_split_shift_excludes_break(): void
    {
        $c = $this->calendar();
        $c['holidays'] = ['2026-10-26'];
        $c['exceptions'] = [['date' => '2026-10-26', 'windows' => [['start' => '09:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '17:00']]]];
        $engine = app(SlaCalendar::class);
        $this->assertSame('2026-10-26 07:00:00', $engine->deadline($c, '2026-10-26 04:00:00', 7200)->toDateTimeString());
        $this->assertSame(7200, $engine->seconds($c, '2026-10-26 04:00:00', '2026-10-26 07:00:00'));
    }

    public function test_twenty_four_hour_calendar_includes_holidays_and_dst_elapsed_time_is_real_seconds(): void
    {
        $c = $this->calendar();
        $c['mode'] = '24x7';
        $c['timezone'] = 'America/New_York';
        $c['holidays'] = ['2026-11-01'];
        $from = CarbonImmutable::parse('2026-11-01 00:00:00', 'America/New_York');
        $end = app(SlaCalendar::class)->deadline($c, $from, 7200);
        $this->assertSame('2026-11-01 06:00:00', $end->toDateTimeString());
        $this->assertSame(7200, app(SlaCalendar::class)->seconds($c, $from, $end));
    }

    public function test_submit_creates_distinct_response_and_restore_or_fulfil_clocks(): void
    {
        $incident = $this->ticket();
        $request = $this->ticket('service_request');
        $this->assertSame('2026-10-26 09:00:00', $this->clock($incident, 'response')->due_at);
        $this->assertSame('2026-10-26 12:00:00', $this->clock($incident)->due_at);
        $this->assertNotNull($this->clock($request, 'fulfil'));
        $this->assertNull($this->clock($request, 'restore'));
    }

    public function test_claim_start_and_internal_comment_are_not_a_human_response(): void
    {
        $t = $this->ticket();
        $this->travel(10)->minutes();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $t = $this->act($t, 'comment', ['is_internal' => true]);
        $this->assertSame('running', $this->clock($t, 'response')->state);
        $t = $this->act($t, 'respond');
        $this->assertSame('completed', $this->clock($t, 'response')->state);
        $this->assertSame(600, $this->clock($t, 'response')->consumed_seconds);
        $this->assertFalse((bool) DB::table('it_ticket_comments')->orderByDesc('id')->value('is_internal'));
    }

    public function test_customer_pause_affects_only_configured_clock_and_resume_reprojects_deadline(): void
    {
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $this->travel(30)->minutes();
        $t = $this->act($t, 'pending', ['pending_code' => 'waiting_customer', 'next_update_at' => now()->addHours(2)->toDateTimeString()]);
        $this->assertSame('paused', $this->clock($t)->state);
        $this->assertNull($this->clock($t)->due_at);
        $this->assertSame('running', $this->clock($t, 'response')->state);
        $this->travel(90)->minutes();
        $this->sweep($t);
        $this->assertSame(1800, $this->clock($t)->consumed_seconds);
        $t = $this->act($t, 'resume');
        $this->assertSame('2026-10-26 13:30:00', $this->clock($t)->due_at);
        $this->assertNotNull($this->clock($t, 'response')->breached_at);
    }

    public function test_vendor_pending_does_not_pause_without_explicit_policy_and_code_is_mandatory(): void
    {
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $this->actingAs($this->technician)->postJson(route('it.action', [$t, 'pending']), ['lock_version' => $t->itDetail->lock_version, 'reason' => 'Menunggu vendor', 'next_update_at' => now()->addHour()->toDateTimeString()])->assertUnprocessable();
        $t = $this->act($t, 'pending', ['pending_code' => 'waiting_vendor', 'next_update_at' => now()->addHours(2)->toDateTimeString()]);
        $this->travel(30)->minutes();
        $this->sweep($t);
        $this->assertSame('running', $this->clock($t)->state);
        $this->assertSame(1800, $this->clock($t)->consumed_seconds);
    }

    public function test_approval_wait_pauses_fulfilment_but_not_first_response(): void
    {
        $this->approvalRule();
        $t = $this->ticket('service_request');
        $this->assertSame('paused', $this->clock($t, 'fulfil')->state);
        $this->assertSame('running', $this->clock($t, 'response')->state);
        $this->travel(2)->hours();
        $a = DB::table('it_ticket_approvals')->where('request_id', $t->id)->first();
        $this->workflow->decide($this->approver, $a->id, ['lock_version' => 1, 'decision' => 'approved', 'reason' => 'Kebutuhan disetujui']);
        $this->assertSame('running', $this->clock($t, 'fulfil')->state);
        $this->assertSame(0, $this->clock($t, 'fulfil')->consumed_seconds);
        $this->assertSame('2026-10-26 14:00:00', $this->clock($t, 'fulfil')->due_at);
    }

    public function test_priority_change_uses_original_consumption_and_cannot_erase_old_breach(): void
    {
        $t = $this->ticket();
        $this->travel(3)->hours();
        $t = $this->act($t, 'priority', ['impact' => 'high', 'urgency' => 'high']);
        $c = $this->clock($t);
        $this->assertSame(10800, $c->consumed_seconds);
        $this->assertSame('2026-10-26 10:00:00', $c->due_at);
        $this->assertSame('2026-10-26 10:00:00', $c->breached_at);
        $t = $this->act($t, 'priority', ['impact' => 'low', 'urgency' => 'low']);
        $c = $this->clock($t);
        $this->assertSame('2026-10-27 00:00:00', $c->due_at);
        $this->assertNotNull($c->breached_at);
        $this->assertSame(1, DB::table('it_sla_events')->where('clock_id', $c->id)->where('action', 'breached')->count());
    }

    public function test_priority_change_after_pause_computes_past_deadline_from_running_spans(): void
    {
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $this->travel(1)->hours();
        $t = $this->act($t, 'pending', ['pending_code' => 'waiting_customer', 'next_update_at' => now()->addHours(3)->toDateTimeString()]);
        $this->travel(2)->hours();
        $t = $this->act($t, 'resume');
        $this->travel(2)->hours();
        $t = $this->act($t, 'priority', ['impact' => 'high', 'urgency' => 'high']);
        $this->assertSame('2026-10-26 12:00:00', $this->clock($t)->due_at);
    }

    public function test_assignment_transfer_does_not_reset_start_target_or_consumption(): void
    {
        $t = $this->ticket();
        $start = $this->clock($t)->started_at;
        $due = $this->clock($t)->due_at;
        $this->travel(45)->minutes();
        $t = $this->act($t, 'assign', ['team_id' => $this->team, 'user_id' => $this->other->id]);
        $this->assertSame($start, $this->clock($t)->started_at);
        $this->assertSame($due, $this->clock($t)->due_at);
        $this->assertSame(2700, $this->clock($t)->consumed_seconds);
    }

    public function test_reopen_links_new_cycle_and_preserves_used_budget_and_first_cycle_evidence(): void
    {
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $this->travel(1)->hours();
        $t = $this->act($t, 'resolve');
        $old = $this->clock($t);
        $t = $this->act($t, 'confirm', [], $this->requester);
        $this->travel(2)->days();
        $t = $this->act($t, 'reopen', [], $this->requester);
        $next = $this->clock($t, 'restore', 2);
        $this->assertSame($old->id, $next->previous_clock_id);
        $this->assertSame(3600, $next->carried_seconds);
        $this->assertSame('completed', $this->clock($t)->state);
        $this->assertSame(CarbonImmutable::now('UTC')->addHours(3)->toDateTimeString(), $next->due_at);
        $this->assertSame('running', $this->clock($t, 'response', 2)->state);
    }

    public function test_reconciliation_after_outage_records_original_deadline_and_deduplicates_alerts(): void
    {
        $t = $this->ticket();
        $this->travel(10)->hours();
        $this->sweep($t);
        $count = DB::table('it_sla_events')->count();
        $messages = DB::table('it_delivery_outbox')->count();
        $this->assertSame('2026-10-26 12:00:00', $this->clock($t)->breached_at);
        $this->assertGreaterThan(0, $messages);
        $this->sweep($t);
        $this->assertSame($count, DB::table('it_sla_events')->count());
        $this->assertSame($messages, DB::table('it_delivery_outbox')->count());
    }

    public function test_delivery_retry_and_duplicate_job_produce_one_personal_notification(): void
    {
        $t = $this->ticket();
        $this->travel(55)->minutes();
        $this->sweep($t);
        $out = DB::table('it_delivery_outbox')->first();
        $this->assertNotNull($out);
        $job = new DeliverOperationNotification($out->id);
        $job->handle(app(OperationDelivery::class));
        $job->handle(app(OperationDelivery::class));
        $this->assertSame(1, DB::table('it_operation_notifications')->where('delivery_id', $out->id)->count());
        $this->assertSame('delivered', DB::table('it_delivery_outbox')->find($out->id)->status);
        $this->actingAs($this->technician)->getJson(route('it.notifications'))->assertOk()->assertJsonFragment(['kind' => 'operational']);
    }

    public function test_removed_recipient_is_skipped_before_delivery(): void
    {
        $t = $this->ticket();
        $this->travel(55)->minutes();
        $this->sweep($t);
        $out = DB::table('it_delivery_outbox')->first();
        DB::table('support_team_members')->where('user_id', $out->user_id)->delete();
        app(OperationDelivery::class)->deliver($out->id);
        $this->assertSame('skipped', DB::table('it_delivery_outbox')->find($out->id)->status);
        $this->assertSame(0, DB::table('it_operation_notifications')->count());
    }

    public function test_major_incident_overdue_communication_links_and_review_are_audited(): void
    {
        $t = $this->ticket();
        $child = $this->ticket();
        $t = $this->act($t, 'major_start', ['commander_id' => $this->technician->id, 'interval_minutes' => 15]);
        $t = $this->act($t, 'major_link', ['child_request_id' => $child->id]);
        $this->assertSame(1, DB::table('it_ticket_links')->count());
        $this->travel(20)->minutes();
        $this->sweep($t);
        $this->sweep($t);
        $this->assertSame(1, DB::table('it_sla_events')->where('action', 'communication_overdue')->count());
        $t = $this->act($t, 'major_update');
        $this->assertSame(CarbonImmutable::now('UTC')->addMinutes(15)->toDateTimeString(), $t->itDetail->major_next_update_at);
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $t = $this->act($t, 'resolve');
        $t = $this->act($t, 'major_end');
        $this->assertFalse((bool) $t->itDetail->is_major);
        $this->assertNotNull($t->itDetail->major_review);
    }

    public function test_dashboard_and_health_obey_distinct_access_and_ticket_scope(): void
    {
        $this->ticket();
        $this->actingAs($this->technician)->getJson(route('it.operations'))->assertOk()->assertJsonPath('counts.open', 1);
        $this->actingAs($this->manager)->getJson(route('it.operations'))->assertForbidden();
        $this->getJson(route('it.operations.health'))->assertOk()->assertJsonMissingPath('tickets');
        $this->actingAs($this->requester)->getJson(route('it.operations'))->assertForbidden();
    }

    public function test_scheduler_requeues_stale_outbox_and_worker_health_detects_missing_or_stale_heartbeat(): void
    {
        $health = app(OperationHealth::class);
        $this->assertFalse($health->report()['ready']);
        $health->beat('scheduler', 'ok');
        (new WorkerHeartbeat)->handle($health);
        $this->assertTrue($health->report()['ready']);
        $this->travel(4)->minutes();
        $this->assertFalse($health->report()['ready']);
        $t = $this->ticket();
        $this->travel(55)->minutes();
        $this->sweep($t);
        $delivery = app(OperationDelivery::class);
        $delivery->enqueue();
        $count = DB::table('it_delivery_outbox')->count();
        Queue::assertPushed(DeliverOperationNotification::class, $count);
        $this->travel(6)->minutes();
        $delivery->enqueue();
        Queue::assertPushed(DeliverOperationNotification::class, $count * 2);
    }

    public function test_sla_snapshot_is_pinned_and_schema_rejects_overlapping_windows_and_missing_fallback(): void
    {
        $t = $this->ticket();
        $checksum = $this->clock($t)->snapshot_checksum;
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Uji perubahan SLA');
        $cfg = $v->configuration;
        $cfg['profiles']['sla']['calendars'][0]['weekly'][1] = [['start' => '08:00', 'end' => '12:00'], ['start' => '11:00', 'end' => '17:00']];
        $this->actingAs($this->manager)->putJson(route('it.configuration.update', $v), ['configuration' => $cfg, 'lock_version' => 0, 'reason' => 'Tolak interval tumpang tindih'])->assertUnprocessable();
        $cfg = $v->configuration;
        $cfg['profiles']['sla']['fallback']['incident'] = null;
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Draft belum lengkap boleh disimpan');
        $this->postJson(route('it.configuration.publish', $v), ['set_revision' => 2, 'lock_version' => $v->lock_version, 'reason' => 'Tolak fallback tidak lengkap'])->assertUnprocessable();
        $this->assertSame($checksum, $this->clock($t)->snapshot_checksum);
    }

    public function test_cancellation_and_pause_cannot_hide_breach_before_scheduler_has_run(): void
    {
        $t = $this->ticket();
        $this->travel(5)->hours();
        $t = $this->act($t, 'cancel', [], $this->requester);
        $this->assertNotNull($this->clock($t)->breached_at);
        $this->assertSame('cancelled', $this->clock($t)->state);
        $this->travelBack();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $this->travel(5)->hours();
        $t = $this->act($t, 'pending', ['pending_code' => 'waiting_customer', 'next_update_at' => now()->addHour()->toDateTimeString()]);
        $this->assertSame('2026-10-26 12:00:00', $this->clock($t)->breached_at);
        $this->assertSame('paused', $this->clock($t)->state);
    }

    public function test_failed_delivery_requires_explicit_retry_and_recovered_job_history_is_retained(): void
    {
        $t = $this->ticket();
        $this->travel(55)->minutes();
        $this->sweep($t);
        $out = DB::table('it_delivery_outbox')->first();
        $delivery = app(OperationDelivery::class);
        $delivery->failed($out->id, new \RuntimeException('Simulated transient failure'));
        $payload = ['data' => ['commandName' => DeliverOperationNotification::class, 'command' => serialize(new DeliverOperationNotification($out->id))]];
        DB::table('it_queue_failed_jobs')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'it_database', 'queue' => 'it', 'payload' => json_encode($payload), 'exception' => 'Transient test failure', 'failed_at' => now('UTC')]);
        $this->assertSame(1, app(OperationHealth::class)->report()['failed_jobs']);
        $this->assertSame(0, $delivery->enqueue());
        $this->artisan('satset:it-retry', ['id' => $out->id, '--reason' => 'Gangguan sudah diperbaiki'])->assertSuccessful();
        $delivery->deliver($out->id);
        $this->assertSame('delivered', DB::table('it_delivery_outbox')->find($out->id)->status);
        $this->assertSame(0, app(OperationHealth::class)->report()['failed_jobs']);
        $this->assertSame(1, DB::table('it_queue_failed_jobs')->count());
    }

    public function test_new_published_sla_does_not_change_existing_ticket_snapshot(): void
    {
        $t = $this->ticket();
        $old = $this->clock($t);
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Ganti target versi baru');
        $cfg = $v->configuration;
        $cfg['profiles']['sla']['policies'][0]['targets']['P2']['restore'] = 120;
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Simpan target versi baru');
        $this->version = $store->activate($v->id, 2, $v->lock_version, $this->manager, 'Publikasi target versi baru');
        $new = $this->ticket();
        $this->assertSame(7200, $this->clock($new)->target_seconds);
        $this->assertSame(14400, $this->clock($t)->target_seconds);
        $this->assertSame($old->snapshot_checksum, $this->clock($t)->snapshot_checksum);
    }

    public function test_restricted_ticket_never_enters_another_technicians_aggregate_or_inbox(): void
    {
        $t = $this->ticket();
        $this->grant($this->technician, 'view_restricted_it');
        $t = $this->act($t, 'restrict', ['is_restricted' => true]);
        $this->actingAs($this->other)->getJson(route('it.operations'))->assertOk()->assertJsonPath('counts.open', 0)->assertJsonCount(0, 'data');
        $this->actingAs($this->technician)->get(route('it.operations'))->assertOk()->assertSee('Operasional IT');
        $this->actingAs($this->manager)->get(route('it.operations.health'))->assertOk()->assertSee('Status scheduler');
    }

    public function test_configuration_preview_simulates_deadlines_without_tickets_or_operational_access(): void
    {
        $this->actingAs($this->manager)->postJson(route('it.configuration.sla-preview', $this->version), ['catalog_key' => 'network_issue', 'priority' => 'P2', 'started_at' => '2026-10-26T08:00:00Z'])
            ->assertOk()->assertJsonPath('persisted', false)->assertJsonPath('clocks.1.due_at', '2026-10-26T12:00:00+00:00');
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, DB::table('it_sla_clocks')->count());
        $this->actingAs($this->requester)->postJson(route('it.configuration.sla-preview', $this->version), ['catalog_key' => 'network_issue', 'priority' => 'P2', 'started_at' => '2026-10-26T08:00:00Z'])->assertForbidden();
    }

    public function test_one_corrupt_ticket_does_not_starve_other_tickets_and_scheduler_reports_failure(): void
    {
        $first = $this->ticket();
        $second = $this->ticket();
        $mock = $this->mock(SlaEngine::class);
        $mock->shouldReceive('available')->once()->andReturnTrue();
        $mock->shouldReceive('reconcileTicket')->with($first->id)->once()->andThrow(new \RuntimeException('Simulated corrupt snapshot'));
        $mock->shouldReceive('reconcileTicket')->with($second->id)->once();
        $this->artisan('satset:it-reconcile')->assertFailed();
        $this->assertSame('failed', DB::table('it_worker_heartbeats')->where('component', 'scheduler')->value('status'));
        Queue::assertPushed(WorkerHeartbeat::class);
    }
}
