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

        foreach (['approvals', 'requests', 'departments', 'problem_categories', 'priorities', 'statuses', 'users'] as $table) {
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
}
