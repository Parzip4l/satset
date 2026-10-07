<?php

namespace Tests\Feature;

use App\Http\Controllers\Master\ExecutiveSummaryController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExecutiveSummaryReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'stock_opname_items',
            'stock_opnames',
            'procurement_receiving_items',
            'procurement_receivings',
            'stock_movements',
            'consumable_items',
            'approvals',
            'requests',
            'departments',
            'problem_categories',
            'priorities',
            'statuses',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('priorities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('problem_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->foreignId('requester_id');
            $table->foreignId('department_id')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('title');
            $table->foreignId('priority_id')->nullable();
            $table->foreignId('status_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id');
            $table->foreignId('approver_id');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function test_it_builds_current_and_previous_period_metrics_from_ticket_data(): void
    {
        $user = User::create([
            'name' => 'GA Admin',
            'email' => 'ga@example.test',
            'password' => 'password',
            'role' => 'ga',
        ]);
        Auth::login($user);

        $openStatus = DB::table('statuses')->insertGetId(['name' => 'Open', 'created_at' => now(), 'updated_at' => now()]);
        $closedStatus = DB::table('statuses')->insertGetId(['name' => 'Closed', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('requests')->insert([
            [
                'ticket_no' => 'TCK-CURRENT-1',
                'requester_id' => $user->id,
                'title' => 'Current closed ticket',
                'status_id' => $closedStatus,
                'payload' => json_encode(['request_type' => 'atk_rtk']),
                'resolved_at' => now()->subDay(),
                'closed_at' => now()->subDay(),
                'created_at' => now()->subDays(3),
                'updated_at' => now(),
            ],
            [
                'ticket_no' => 'TCK-CURRENT-2',
                'requester_id' => $user->id,
                'title' => 'Current open ticket',
                'status_id' => $openStatus,
                'payload' => json_encode(['request_type' => 'consumption']),
                'resolved_at' => null,
                'closed_at' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now(),
            ],
            [
                'ticket_no' => 'TCK-PREVIOUS-1',
                'requester_id' => $user->id,
                'title' => 'Previous ticket',
                'status_id' => $closedStatus,
                'payload' => json_encode(['request_type' => 'atk_rtk']),
                'resolved_at' => now()->subDays(35),
                'closed_at' => now()->subDays(35),
                'created_at' => now()->subDays(40),
                'updated_at' => now(),
            ],
        ]);

        $request = Request::create(route('bum.executive-summary'), 'GET', [
            'date_from' => now()->subDays(29)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);
        $view = app(ExecutiveSummaryController::class)->index($request);
        $data = $view->getData();

        $this->assertSame(2, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['completed']);
        $this->assertSame(50.0, $data['summary']['completion_rate']);
        $this->assertSame(1, $data['summary']['open']);
        $this->assertSame(1, $data['previous']['total']);
    }

    public function test_it_includes_inventory_and_facility_findings(): void
    {
        $this->createInventoryTables();

        $user = User::create([
            'name' => 'GA Admin',
            'email' => 'ga-inventory@example.test',
            'password' => 'password',
            'role' => 'ga',
        ]);
        Auth::login($user);
        $openStatus = DB::table('statuses')->insertGetId(['name' => 'Open', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('requests')->insert([
            'ticket_no' => 'GA-FINDING-1',
            'requester_id' => $user->id,
            'title' => 'Lampu mati',
            'status_id' => $openStatus,
            'payload' => json_encode(['request_type' => 'ga_request_finding', 'report_type' => 'Temuan', 'location' => 'Stasiun A']),
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);
        $itemId = DB::table('consumable_items')->insertGetId([
            'code' => 'ATK-001',
            'name' => 'Pulpen',
            'category' => 'ATK',
            'unit' => 'pcs',
            'large_uom' => 'box',
            'small_uom' => 'pcs',
            'conversion_qty' => 10,
            'minimum_stock' => 5,
            'buffer_stock' => 2,
            'current_stock' => 0,
            'small_stock' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'item_id' => $itemId,
            'movement_type' => 'OUT',
            'stock_location' => 'small_warehouse',
            'qty' => 3,
            'balance_before' => 6,
            'balance_after' => 3,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);
        $receivingId = DB::table('procurement_receivings')->insertGetId([
            'reference_number' => 'RCV-001',
            'status' => 'SUBMITTED',
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);
        DB::table('procurement_receiving_items')->insert([
            'receiving_id' => $receivingId,
            'item_id' => $itemId,
            'qty_received' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opnameId = DB::table('stock_opnames')->insertGetId([
            'period' => now()->format('Y-m'),
            'status' => 'COMPLETED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_opname_items')->insert([
            'stock_opname_id' => $opnameId,
            'item_id' => $itemId,
            'variance' => -2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $view = app(ExecutiveSummaryController::class)->index(Request::create(route('bum.executive-summary'), 'GET'));
        $data = $view->getData();

        $this->assertSame(1, $data['findings']['total']);
        $this->assertSame(1, $data['findings']['open']);
        $this->assertSame(1, $data['inventory']['active_items']);
        $this->assertSame(1, $data['inventory']['low_stock']);
        $this->assertSame(3, $data['inventory']['outgoing_qty']);
        $this->assertSame(5, $data['inventory']['received_qty']);
        $this->assertSame(2, $data['inventory']['opname_variance']);
    }

    private function createInventoryTables(): void
    {
        Schema::create('consumable_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit')->nullable();
            $table->string('large_uom')->nullable();
            $table->string('small_uom')->nullable();
            $table->integer('conversion_qty')->default(1);
            $table->integer('minimum_stock')->default(0);
            $table->integer('buffer_stock')->default(0);
            $table->integer('current_stock')->default(0);
            $table->integer('small_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id');
            $table->string('movement_type');
            $table->string('stock_location');
            $table->integer('qty');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->timestamps();
        });
        Schema::create('procurement_receivings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('procurement_receiving_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receiving_id');
            $table->foreignId('item_id');
            $table->integer('qty_received')->default(0);
            $table->timestamps();
        });
        Schema::create('stock_opnames', function (Blueprint $table): void {
            $table->id();
            $table->string('period');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('stock_opname_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_opname_id');
            $table->foreignId('item_id');
            $table->integer('variance');
            $table->timestamps();
        });
    }
}
