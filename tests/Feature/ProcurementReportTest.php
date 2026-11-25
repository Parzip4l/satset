<?php

namespace Tests\Feature;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\ProcurementReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcurementReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(12, 0));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no');
            $table->unsignedBigInteger('requester_id');
            $table->string('title');
            $table->json('payload');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('approver_id');
            $table->unsignedInteger('level');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    private function user(string $role = 'superadmin', string $name = 'Management'): User
    {
        return User::forceCreate(['name' => $name, 'email' => uniqid().'@example.test', 'password' => bcrypt('password'), 'role' => $role]);
    }

    private function ticket(User $user, string $department, string $date, string $status, array $items, array $records = [], bool $approved = false, string $type = 'goods_procurement'): Ticket
    {
        $ticket = Ticket::withoutEvents(fn () => Ticket::create([
            'ticket_no' => 'PGB-'.uniqid(), 'title' => 'Kebutuhan '.$department, 'requester_id' => $user->id,
            'payload' => ['request_type' => $type, 'requester_name' => $user->name, 'requester_department' => $department,
                'workflow_status' => $status, 'items' => $items, 'fulfillments' => $records, 'total_estimated_amount' => 99999999],
        ]));
        DB::table('requests')->where('id', $ticket->id)->update(['created_at' => $date, 'closed_at' => $status === 'CLOSED' ? '2026-02-15 12:00:00' : null]);
        if ($approved) {
            $ticket->approvals()->create(['approver_id' => $user->id, 'level' => 1, 'status' => 'Approved']);
        }

        return $ticket->refresh();
    }

    private function items(float $budget, int $quantity = 1, int $fulfilled = 0): array
    {
        return [['need' => 'Barang', 'estimated_budget' => $budget, 'quantity' => $quantity, 'fulfilled_quantity' => $fulfilled]];
    }

    private function fixtures(): array
    {
        $andi = $this->user('pelapor', 'Andi');
        $budi = $this->user('pelapor', 'Budi');
        $partial = $this->ticket($andi, 'Engineering', '2026-01-10 09:00:00', 'PARTIALLY_FULFILLED', array_merge($this->items(1000, 4, 2), $this->items(600, 2)), [
            ['method' => 'funds', 'amount' => 200, 'quantity' => 1, 'recorded_at' => '2026-11-20 09:00:00'],
            ['method' => 'stock', 'amount' => 999999, 'quantity' => 1],
            ['method' => 'waiting', 'amount' => 999999, 'quantity' => 0],
        ], true);
        $closed = $this->ticket($andi, ' Engineering ', '2026-02-10 12:00:00', 'CLOSED', $this->items(400, 1, 1), [['method' => 'funds', 'amount' => 350, 'quantity' => 1]], true);
        $rejected = $this->ticket($budi, 'Operations', '2026-02-05 12:00:00', 'REJECTED_BY_MANAGER', $this->items(3000));
        $pending = $this->ticket($budi, 'Operations', '2026-02-28 23:59:59', 'WAITING_MANAGER_APPROVAL', $this->items(500));
        $this->ticket($budi, 'Operations', '2026-03-01 00:00:00', 'WAITING_MANAGER_APPROVAL', $this->items(99999));
        $this->ticket($budi, 'Operations', '2026-01-01 00:00:00', 'CLOSED', $this->items(99999), [], false, 'atk_rtk');

        return compact('partial', 'closed', 'rejected', 'pending', 'andi', 'budi');
    }

    private function filters(array $changes = []): array
    {
        return array_replace(['from' => '2026-01-01', 'to' => '2026-02-28', 'ranking' => 'budget'], $changes);
    }

    public function test_report_is_scoped_and_distinguishes_budget_cash_stock_and_outstanding_needs(): void
    {
        $this->fixtures();
        $report = app(ProcurementReportService::class)->build($this->filters());
        $summary = $report['summary'];
        $this->assertSame(4, $summary['count']);
        $this->assertSame(550000, $summary['budget']);
        $this->assertSame(200000, $summary['approved_budget']);
        $this->assertSame(50000, $summary['pending_budget']);
        $this->assertSame(55000, $summary['funds']);
        $this->assertSame(160000, $summary['outstanding_budget']);
        $this->assertSame(1, $summary['stock_quantity']);
        $this->assertSame(2, $summary['funded_quantity']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(1, $summary['rejected']);
        $this->assertSame(2, $summary['aging_count']);
        $this->assertEquals(25, $summary['completion_rate']);
        $this->assertEquals(5, $summary['average_completion_days']);
        $this->assertCount(2, $report['departmentRows']);
        $this->assertSame('Operations', $report['departmentRows'][0]['label']);
        $this->assertSame('Budi', $report['requesterRows'][0]['label']);
        $this->assertSame([160000, 390000], array_column($report['months'], 'budget'));
        $this->assertSame([20000, 35000], array_column($report['months'], 'funds'));
    }

    public function test_department_status_and_ranking_filters_are_applied_to_every_section(): void
    {
        $this->fixtures();
        $report = app(ProcurementReportService::class)->build($this->filters(['department' => 'engineering', 'status' => 'CLOSED', 'ranking' => 'funds']));
        $this->assertSame(1, $report['summary']['count']);
        $this->assertSame(40000, $report['summary']['budget']);
        $this->assertSame(35000, $report['summary']['funds']);
        $this->assertSame(0, $report['summary']['outstanding_budget']);
        $this->assertCount(0, $report['backlog']);
        $this->assertCount(2, $report['departments']);
        $all = app(ProcurementReportService::class)->build($this->filters(['ranking' => 'funds']));
        $this->assertSame('Engineering', $all['departmentRows'][0]['label']);
        $this->assertSame('Andi', $all['requesterRows'][0]['label']);
    }

    public function test_authorized_roles_can_view_report_and_exports_while_employees_cannot(): void
    {
        $this->fixtures();
        foreach (['superadmin', 'admin', 'ga', 'bum', 'procurement'] as $role) {
            $this->actingAs($this->user($role))->get(route('procurement.reports', $this->filters()))->assertOk()
                ->assertSee('Rekap per departemen')->assertSee('Operations')->assertSee('Rp5.500,00')->assertSee('Anggaran diajukan');
            $this->get(route('procurement.reports.export', $this->filters()))->assertOk()->assertDownload();
        }
        $this->actingAs($this->user('pelapor'))->get(route('procurement.reports'))->assertForbidden();
        $this->get(route('procurement.reports.export'))->assertForbidden();
    }

    public function test_export_matches_filtered_totals_includes_all_rows_and_escapes_formulas(): void
    {
        $fixtures = $this->fixtures();
        $this->actingAs($this->user('ga'));
        $csv = $this->get(route('procurement.reports.export', $this->filters()))->assertOk()->streamedContent();
        $this->assertStringContainsString('Engineering;2;2000.00;2000.00;550.00;1100.00', $csv);
        $this->assertStringContainsString('Operations;2;3500.00;0.00;0.00;500.00', $csv);
        DB::table('requests')->where('id', $fixtures['closed']->id)->update(['title' => '=HYPERLINK("https://example.test")']);
        $details = $this->get(route('procurement.reports.export', $this->filters(['kind' => 'requests', 'department' => 'engineering'])))->assertOk()->streamedContent();
        $this->assertStringContainsString($fixtures['closed']->ticket_no, $details);
        $this->assertStringContainsString("'=HYPERLINK", $details);
        $this->assertStringNotContainsString($fixtures['rejected']->ticket_no, $details);
    }

    public function test_empty_report_invalid_filters_and_pagination_are_handled(): void
    {
        $this->actingAs($this->user())->get(route('procurement.reports'))->assertOk()->assertSee('Belum ada pengajuan pada filter ini');
        $this->getJson(route('procurement.reports', $this->filters(['from' => '2026-03-01'])))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('procurement.reports', ['ranking' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('ranking');
        $this->getJson(route('procurement.reports', ['status' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('status');
        $user = $this->user('pelapor');
        for ($i = 0; $i < 22; $i++) {
            $this->ticket($user, 'Engineering', '2026-02-01 00:00:00', 'WAITING_MANAGER_APPROVAL', $this->items(100));
        }
        $this->get(route('procurement.reports', $this->filters(['page' => 2])))->assertOk()->assertViewHas('details', fn ($details) => $details->count() === 2 && $details->total() === 22);
        $csv = $this->get(route('procurement.reports.export', $this->filters(['kind' => 'requests', 'page' => 2])))->assertOk()->streamedContent();
        $this->assertSame(22, substr_count($csv, 'PGB-'));
    }
}
