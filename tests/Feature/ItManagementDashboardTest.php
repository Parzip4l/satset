<?php

namespace Tests\Feature;

use App\Services\It\ManagementDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItManagementDashboardTest extends TestCase
{
    use \Tests\Support\BuildsItWorkflow { setUp as workflowSetUp; }

    protected function setUp(): void
    {
        $this->workflowSetUp();
        (require database_path('migrations/2026_10_28_000005_create_it_sla_operations.php'))->up();
        $this->travelTo(CarbonImmutable::parse('2026-10-28 04:00:00', 'UTC'));
    }

    private function filters(): array
    {
        return ['from' => '2026-10-28', 'to' => '2026-10-28'];
    }

    public function test_management_counts_use_visibility_and_current_backlog_and_reopened_resolution_events(): void
    {
        $visible = $this->ticket();
        $hidden = $this->ticket('service_request');
        DB::table('it_ticket_details')->where('request_id', $hidden->id)->update(['is_restricted' => true]);
        foreach ([1, 2] as $sequence) {
            DB::table('it_ticket_events')->insert(['request_id' => $visible->id, 'actor_id' => $this->technician->id, 'sequence' => 100 + $sequence, 'action' => 'resolve', 'from_status' => 'in_progress', 'to_status' => 'resolved', 'reason' => 'Uji', 'created_at' => now()->subMinutes($sequence)]);
        }
        $data = app(ManagementDashboard::class)->build($this->technician, $this->filters());
        $this->assertSame(1, $data['incoming']);
        $this->assertSame(1, $data['resolved']);
        $this->assertSame(1, $data['backlog']);
        $this->assertSame(1, collect($data['trend'])->sum('resolved'));
        $this->assertCount(1, $data['recent']);
        $this->grant($this->technician, 'view_restricted_it');
        $this->assertSame(2, app(ManagementDashboard::class)->build($this->technician, $this->filters())['incoming']);
    }

    public function test_completed_sla_is_counted_per_clock_and_excludes_cancelled_and_unanswered(): void
    {
        $ticket = $this->ticket();
        foreach ([['response', 'completed', 50, null], ['restore', 'completed', 80, now()], ['fulfil', 'cancelled', 20, null], ['response', 'unanswered', 20, null]] as $i => [$kind, $state, $used, $breached]) {
            DB::table('it_sla_clocks')->insert(['request_id' => $ticket->id, 'cycle' => $i + 1, 'kind' => $kind, 'snapshot' => '{}', 'snapshot_checksum' => str_repeat('a', 64), 'priority' => 'P2', 'target_seconds' => 100, 'consumed_seconds' => $used, 'carried_seconds' => 0, 'state' => $state, 'started_at' => now()->subHour(), 'accounted_at' => now(), 'completed_at' => now(), 'breached_at' => $breached, 'created_at' => now(), 'updated_at' => now()]);
        }
        $sla = app(ManagementDashboard::class)->build($this->technician, $this->filters())['sla'];
        $this->assertSame(['total' => 1, 'met' => 1, 'percent' => 100.0], $sla['response']);
        $this->assertSame(['total' => 1, 'met' => 0, 'percent' => 0.0], $sla['restore']);
        $this->assertNull($sla['fulfil']['percent']);
    }

    public function test_date_boundaries_use_jakarta_and_configuration_access_does_not_grant_dashboard_access(): void
    {
        $ticket = $this->ticket();
        DB::table('requests')->where('id', $ticket->id)->update(['created_at' => '2026-10-28 00:00:00']);
        $this->assertSame(1, app(ManagementDashboard::class)->build($this->technician, $this->filters())['incoming']);
        DB::table('requests')->where('id', $ticket->id)->update(['created_at' => '2026-10-27 23:59:59']);
        $this->assertSame(0, app(ManagementDashboard::class)->build($this->technician, $this->filters())['incoming']);
        $this->actingAs($this->manager)->get(route('it.dashboard'))->assertOk();
        $this->assertSame(0, app(ManagementDashboard::class)->build($this->manager, $this->filters())['incoming']);
        $this->actingAs($this->requester)->get(route('it.dashboard'))->assertForbidden();
        $this->actingAs($this->technician)->get(route('it.dashboard', ['from' => '2026-10-30', 'to' => '2026-10-01']))->assertStatus(422);
    }

    public function test_csv_respects_ticket_access_and_escapes_spreadsheet_formulas(): void
    {
        $visible = $this->ticket();
        $hidden = $this->ticket('service_request');
        DB::table('it_ticket_details')->where('request_id', $hidden->id)->update(['is_restricted' => true]);
        DB::table('requests')->where('id', $visible->id)->update(['title' => '=1+1']);
        $response = $this->actingAs($this->technician)->get(route('it.dashboard', $this->filters() + ['export' => 'csv']));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($visible->ticket_no, $csv);
        $this->assertStringNotContainsString($hidden->ticket_no, $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $managerCsv = $this->actingAs($this->manager)->get(route('it.dashboard', $this->filters() + ['export' => 'csv']))->streamedContent();
        $this->assertStringNotContainsString($visible->ticket_no, $managerCsv);
        $this->assertStringNotContainsString($hidden->ticket_no, $managerCsv);
    }

    public function test_dashboard_renders_with_empty_data(): void
    {
        $this->actingAs($this->technician)->get(route('it.dashboard', $this->filters()))->assertOk()->assertSee('Dashboard Manajemen IT')->assertSee('Belum ada aktivitas pada periode ini.');
        $ticket = $this->ticket();
        $this->actingAs($this->technician)->get(route('it.dashboard', $this->filters()))->assertOk()->assertSee($ticket->ticket_no)->assertSee('Kategori teratas');
    }

    public function test_active_sla_counts_are_distinct_by_ticket_and_ignore_old_cycles(): void
    {
        $ticket = $this->ticket();
        $this->assertSame(1, (int) DB::table('it_ticket_details')->where('request_id', $ticket->id)->value('sla_cycle'));
        foreach ([['response', 1, now('UTC')->subMinute()], ['restore', 1, now('UTC')->subMinute()], ['response', 2, now('UTC')->subMinute()]] as [$kind, $cycle, $due]) {
            DB::table('it_sla_clocks')->insert(['request_id' => $ticket->id, 'cycle' => $cycle, 'kind' => $kind, 'snapshot' => '{}', 'snapshot_checksum' => str_repeat('a', 64), 'priority' => 'P2', 'target_seconds' => 100, 'consumed_seconds' => 0, 'carried_seconds' => 0, 'state' => 'running', 'started_at' => now()->subHour(), 'accounted_at' => now(), 'due_at' => $due, 'created_at' => now(), 'updated_at' => now()]);
        }
        $data = app(ManagementDashboard::class)->build($this->technician, $this->filters());
        $this->assertSame(1, $data['breached']);
        DB::table('it_sla_clocks')->where('request_id', $ticket->id)->where('cycle', 1)->update(['state' => 'paused', 'due_at' => null]);
        $this->assertSame(0, app(ManagementDashboard::class)->build($this->technician, $this->filters())['breached']);
    }

    public function test_filters_and_drill_down_keep_scope_and_count_the_same_tickets(): void
    {
        $visible = $this->ticket();
        $hidden = $this->ticket();
        DB::table('it_ticket_details')->where('request_id', $hidden->id)->update(['is_restricted' => true]);
        $detail = $visible->fresh()->itDetail;
        DB::table('requests')->where('id', $visible->id)->update(['assigned_user_id' => $this->technician->id]);
        $filters = $this->filters() + ['service_key' => $detail->service_key, 'category_key' => $detail->category_key, 'status' => $detail->status, 'assigned_user_id' => $this->technician->id];
        $dashboard = app(ManagementDashboard::class);
        $data = $dashboard->build($this->technician, $filters);
        $this->assertSame(1, $data['incoming']);
        $this->assertSame($data['incoming'], $dashboard->detailScope($this->technician, $filters, 'incoming')->count());
        $this->assertSame($data['backlog'], $dashboard->detailScope($this->technician, $filters, 'backlog')->count());
        $response = $this->actingAs($this->technician)->get(route('it.dashboard', $filters + ['detail' => 'incoming']));
        $response->assertOk()->assertSee($visible->ticket_no)->assertDontSee($hidden->ticket_no);
        $csv = $this->actingAs($this->technician)->get(route('it.dashboard', $filters + ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString($visible->ticket_no, $csv);
        $this->assertStringNotContainsString($hidden->ticket_no, $csv);
        $this->assertSame(0, $dashboard->scope($this->technician, $filters + ['priority' => 'P4'])->count());
    }

    public function test_previous_period_comparison_uses_equal_length_and_current_filters(): void
    {
        $ticket = $this->ticket();
        DB::table('requests')->where('id', $ticket->id)->update(['created_at' => '2026-10-27 23:59:59']);
        $data = app(ManagementDashboard::class)->build($this->technician, $this->filters());
        $this->assertSame(0, $data['incoming']);
        $this->assertSame('2026-10-27', $data['previous']['from']);
        $this->assertSame('2026-10-27', $data['previous']['to']);
        $this->assertSame(1, $data['previous']['incoming']);
    }

    public function test_main_dashboard_year_filter_does_not_reveal_restricted_ticket_years(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('YEAR', fn ($date) => (int) substr($date, 0, 4), 1);
        $visible = $this->ticket();
        $restricted = $this->ticket();
        DB::table('it_ticket_details')->where('request_id', $restricted->id)->update(['is_restricted' => true]);
        DB::table('requests')->where('id', $visible->id)->update(['created_at' => '2026-01-02 12:00:00']);
        DB::table('requests')->where('id', $restricted->id)->update(['created_at' => '2024-01-02 12:00:00']);
        $this->grant($this->manager, 'view_it_queue');
        $response = $this->actingAs($this->manager)->get(route('dashboard.index'));
        $response->assertOk();
        $this->assertContains(2026, $response->viewData('availableYears'));
        $this->assertNotContains(2024, $response->viewData('availableYears'));
    }
}
