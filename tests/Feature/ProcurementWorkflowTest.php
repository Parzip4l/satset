<?php

namespace Tests\Feature;

use App\Models\Master\ConsumableItem;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\LrtjSpaceApprovalResolverService;
use App\Services\LrtjSpaceMobileNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    private User $requester;

    private User $manager;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->mock(LrtjSpaceMobileNotificationService::class, fn ($mock) => $mock->shouldIgnoreMissing());

        foreach (['approvals', 'ticket_histories', 'attachments', 'requests', 'consumable_items', 'category_department', 'departments', 'statuses', 'ticket_categories', 'urgencies', 'impacts', 'priorities', 'problem_categories', 'menus', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('phone')->nullable();
            $table->string('role')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('problem_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        foreach (['priorities', 'impacts', 'urgencies'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }
        Schema::create('ticket_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });
        Schema::create('category_department', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('department_id');
            $table->timestamps();
        });
        Schema::create('consumable_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('large_uom')->nullable();
            $table->string('small_uom')->nullable();
            $table->integer('conversion_qty')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->integer('small_stock')->default(0);
            $table->integer('current_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->uuid('submission_token')->nullable()->unique();
            $table->foreignId('requester_id');
            $table->foreignId('department_id')->nullable();
            $table->foreignId('assigned_department_id')->nullable();
            $table->foreignId('category_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('priority_id')->nullable();
            $table->foreignId('impact_id')->nullable();
            $table->foreignId('urgency_id')->nullable();
            $table->foreignId('ticket_category_id')->nullable();
            $table->foreignId('status_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ticket_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('user_id');
            $table->foreignId('status_id')->nullable();
            $table->string('action')->nullable();
            $table->timestamps();
        });
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id');
            $table->foreignId('approver_id');
            $table->string('status');
            $table->unsignedInteger('level');
            $table->timestamps();
        });
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id');
            $table->foreignId('uploaded_by');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->integer('size')->nullable();
            $table->string('attachment_type')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('user_id');
            $table->text('message');
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable();
        });
        Schema::table('requests', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_user_id')->nullable();
        });
        Schema::table('approvals', function (Blueprint $table) {
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
        });
        (require database_path('migrations/2026_08_14_103937_add_portal_sync_fields_to_approvals.php'))->up();
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            foreach (['item_id', 'qty', 'balance_before', 'balance_after', 'reference_id', 'created_by'] as $field) {
                $table->integer($field)->nullable();
            }
            foreach (['movement_type', 'stock_location', 'source_stock_location', 'destination_stock_location', 'balance_uom', 'reference_type', 'notes'] as $field) {
                $table->string($field)->nullable();
            }
            $table->timestamps();
        });
        foreach (['Open', 'In Progress', 'Closed'] as $name) {
            DB::table('statuses')->insert(['name' => $name]);
        }
        foreach (['priorities', 'impacts', 'urgencies'] as $table) {
            DB::table($table)->insert(['name' => 'Medium']);
        }
        $this->requester = $this->user('Pemohon', 'pelapor');
        $this->manager = $this->user('Manager', 'manager');
        $this->officer = $this->user('Procurement', 'procurement');
        $this->mock(LrtjSpaceApprovalResolverService::class, function ($mock) {
            $mock->shouldReceive('resolveFirstApprover')->andReturn($this->manager);
            $mock->shouldReceive('resolveRequester')->andReturn(['name' => $this->requester->name, 'email' => $this->requester->email, 'organization' => ['department' => ['name' => 'Engineering']]]);
        });
    }

    private function user(string $name, string $role): User
    {
        return User::forceCreate(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => bcrypt('password'), 'role' => $role, 'department' => 'Engineering']);
    }

    private function submission(): array
    {
        return ['submission_token' => (string) Str::uuid(), 'items' => [
            ['need' => 'Kursi', 'description' => 'Kursi kerja ergonomis', 'location' => 'Kantor A', 'quantity' => 3, 'estimated_budget' => 1500000],
            ['need' => 'Meja', 'description' => 'Meja kerja', 'location' => 'Kantor B', 'quantity' => 1, 'estimated_budget' => 1000000],
        ]];
    }

    private function ticket(bool $approve = true): Ticket
    {
        $this->actingAs($this->requester)->post(route('procurement.store'), $this->submission())->assertRedirect()->assertSessionHasNoErrors();
        $ticket = Ticket::latest('id')->firstOrFail();
        if ($approve) {
            $this->actingAs($this->manager)->post(route('ticket.approve', $ticket), ['approval_id' => $ticket->approvals()->firstOrFail()->id, 'status' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        }

        return $ticket->refresh();
    }

    private function delivery(array $changes = []): array
    {
        return array_replace(['submission_token' => (string) Str::uuid(), 'line' => 0, 'method' => 'funds', 'quantity' => 2,
            'amount' => 900000, 'recipient' => 'Pemohon', 'notes' => 'Diserahkan kepada pemohon.',
            'evidence' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')], $changes);
    }

    public function test_submission_uses_login_identity_and_requires_assigned_manager_for_every_budget(): void
    {
        $data = $this->submission() + ['requester_name' => 'Palsu', 'requester_department' => 'Palsu', 'workflow_status' => 'CLOSED'];
        $data['items'][0]['estimated_budget'] = 0;
        $this->actingAs($this->requester)->post(route('procurement.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $ticket = Ticket::firstOrFail();
        $this->assertSame('Pemohon', $ticket->payload['requester_name']);
        $this->assertSame('Engineering', $ticket->payload['requester_department']);
        $this->assertSame('WAITING_MANAGER_APPROVAL', $ticket->payload['workflow_status']);
        $this->assertEquals(1000000, $ticket->payload['total_estimated_amount']);
        $this->assertDatabaseHas('approvals', ['request_id' => $ticket->id, 'approver_id' => $this->manager->id, 'status' => 'Pending']);
        $this->actingAs($this->requester)->post(route('procurement.store'), $data)->assertRedirect();
        $this->assertSame(1, Ticket::count());
        $this->assertSame(1, $ticket->approvals()->count());
    }

    public function test_department_is_loaded_from_portal_when_missing_locally(): void
    {
        $this->requester->forceFill(['department' => null])->save();
        $this->actingAs($this->requester)->get(route('procurement.create'))->assertOk()->assertSee('Engineering');
        $ticket = $this->ticket(false);
        $this->assertSame('Engineering', $ticket->payload['requester_department']);
    }

    public function test_new_form_menu_and_procurement_queue_render(): void
    {
        $this->actingAs($this->requester)->get(route('procurement.create'))->assertOk()->assertSee('Tambah baris')->assertSee('items[0][need]', false);
        $this->actingAs($this->requester)->get(route('ticket.index'))->assertOk()->assertSee('Pengajuan Pengadaan Barang');
        $ticket = $this->ticket();
        $this->actingAs($this->officer)->get(route('procurement.index'))->assertOk()->assertSee($ticket->ticket_no);
        $this->actingAs($this->officer)->get(route('ticket.show', $ticket))->assertOk()->assertSee('Tindak lanjut Procurement')->assertSee('Kursi kerja ergonomis');
    }

    public function test_ga_and_procurement_handle_fulfillment_after_manager_approval(): void
    {
        $ticket = $this->ticket(false);
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery())->assertStatus(409);
        $ga = $this->user('GA', 'ga');
        $this->actingAs($ga)->get(route('procurement.index'))->assertOk();
        $this->actingAs($ga)->get(route('ticket.show', $ticket))->assertOk();
        $this->actingAs($ga)->post(route('procurement.fulfill', $ticket), $this->delivery())->assertStatus(409);
        $this->actingAs($this->requester)->post(route('procurement.fulfill', $ticket), $this->delivery())->assertForbidden();
        $this->actingAs($this->manager)->post(route('ticket.approve', $ticket), ['approval_id' => $ticket->approvals()->first()->id, 'status' => 'approved'])->assertRedirect();
        $this->actingAs($ga)->post(route('procurement.fulfill', $ticket), $this->delivery())->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($ga)->post(route('ticket.consumption.flow', $ticket), ['workflow_status' => 'CLOSED'])->assertNotFound();
    }

    public function test_unassigned_manager_and_requester_cannot_approve(): void
    {
        $ticket = $this->ticket(false);
        $approval = $ticket->approvals()->firstOrFail();
        $admin = $this->user('Admin', 'admin');
        $this->actingAs($admin)->post(route('ticket.approve', $ticket), ['approval_id' => $approval->id, 'status' => 'approved'])->assertForbidden();
        $this->actingAs($this->requester)->post(route('ticket.approve', $ticket), ['approval_id' => $approval->id, 'status' => 'approved'])->assertForbidden();
        $this->assertSame('WAITING_MANAGER_APPROVAL', $ticket->refresh()->payload['workflow_status']);
    }

    public function test_manager_rejection_blocks_procurement_and_records_audit(): void
    {
        $ticket = $this->ticket(false);
        $this->actingAs($this->manager)->post(route('ticket.approve', $ticket), ['approval_id' => $ticket->approvals()->first()->id, 'status' => 'rejected', 'note' => 'Belum dibutuhkan'])->assertRedirect();
        $this->assertSame('REJECTED_BY_MANAGER', $ticket->refresh()->payload['workflow_status']);
        $this->assertDatabaseHas('approval_audits', ['ticket_id' => $ticket->id, 'status' => 'rejected']);
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery())->assertStatus(409);
    }

    public function test_waiting_then_partial_funds_and_stock_complete_all_lines_with_evidence(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), ['submission_token' => (string) Str::uuid(), 'line' => 0, 'method' => 'waiting', 'notes' => 'Stok belum ada'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('WAITING_PROCUREMENT', $ticket->refresh()->payload['workflow_status']);
        $data = $this->delivery();
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('PARTIALLY_FULFILLED', $ticket->refresh()->payload['workflow_status']);
        $this->assertEquals(2, $ticket->payload['items'][0]['fulfilled_quantity']);
        $this->assertNull($ticket->closed_at);
        // A retry must not deliver funds twice.
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $data)->assertRedirect();
        $this->assertCount(2, $ticket->refresh()->payload['fulfillments']);
        $item = ConsumableItem::create(['code' => 'KRS', 'name' => 'Kursi', 'small_uom' => 'Unit', 'small_stock' => 2, 'is_active' => true]);
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery(['method' => 'stock', 'item_id' => $item->id, 'quantity' => 1, 'amount' => null]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(1, $item->refresh()->small_stock);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'goods_procurement', 'reference_id' => $ticket->id, 'qty' => 1]);
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery(['line' => 1, 'quantity' => 1, 'amount' => 1000000]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('CLOSED', $ticket->refresh()->payload['workflow_status']);
        $this->assertNotNull($ticket->closed_at);
        $this->assertSame(3, $ticket->attachments()->count());
        foreach ($ticket->attachments as $attachment) {
            Storage::disk('public')->assertExists($attachment->file_path);
        }
        $attachment = $ticket->attachments->first();
        $this->actingAs($this->requester)->get(route('ticket.attachments.show', [$ticket, $attachment]))->assertOk();
        $this->actingAs($this->user('Lain', 'pelapor'))->get(route('ticket.attachments.show', [$ticket, $attachment]))->assertForbidden();
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery())->assertStatus(409);
    }

    public function test_invalid_delivery_never_updates_stock_or_request(): void
    {
        $ticket = $this->ticket();
        $item = ConsumableItem::create(['code' => 'KRS', 'name' => 'Kursi', 'small_stock' => 1, 'is_active' => true]);
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery(['evidence' => null]))->assertSessionHasErrors('evidence');
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery(['quantity' => 4]))->assertSessionHasErrors('quantity');
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery(['line' => 99]))->assertStatus(422);
        $this->actingAs($this->officer)->post(route('procurement.fulfill', $ticket), $this->delivery(['method' => 'stock', 'item_id' => $item->id]))->assertSessionHasErrors('quantity');
        $this->assertSame([], $ticket->refresh()->payload['fulfillments']);
        $this->assertEquals(1, $item->refresh()->small_stock);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_generic_mutation_cannot_bypass_workflow(): void
    {
        $ticket = $this->ticket(false);
        $this->actingAs($this->requester)->put(route('ticket.update', $ticket->id), ['payload' => ['request_type' => 'goods_procurement', 'workflow_status' => 'CLOSED']])->assertNotFound();
        $this->actingAs($this->requester)->delete(route('ticket.destroy', $ticket->id))->assertNotFound();
        $this->actingAs($this->user('Admin', 'admin'))->put(route('ticket.updateStatus', $ticket), ['status_id' => DB::table('statuses')->where('name', 'Closed')->value('id')])->assertNotFound();
        $this->assertSame('WAITING_MANAGER_APPROVAL', $ticket->refresh()->payload['workflow_status']);
    }

    public function test_portal_receives_new_type_budget_and_preserves_department_metadata(): void
    {
        config(['satset.approval_resolver.base_url' => 'https://portal.test', 'satset.approval_resolver.endpoint' => '/resolve', 'satset.approval_resolver.shared_secret' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['https://portal.test/resolve' => Http::response(['data' => [
            'requester' => ['email' => $this->requester->email, 'name' => $this->requester->name, 'organization' => ['department' => ['name' => 'Engineering']]],
            'approver' => ['email' => $this->manager->email, 'name' => $this->manager->name],
        ]])]);
        $this->app->instance(LrtjSpaceApprovalResolverService::class, new LrtjSpaceApprovalResolverService);
        $this->requester->forceFill(['department' => null])->save();
        $ticket = $this->ticket(false);
        Http::assertSent(fn ($request) => $request['request_type'] === 'goods_procurement' && $request['amount'] == 2500000 && $request['requester_email'] === $this->requester->email);
        $this->assertSame('Engineering', $ticket->payload['requester_department']);
        $this->assertDatabaseHas('approvals', ['request_id' => $ticket->id, 'approver_id' => $this->manager->id]);
    }

    public function test_notifications_go_to_procurement_ga_and_requester(): void
    {
        $ticket = $this->ticket();
        (require database_path('migrations/2025_07_28_112236_create_notifications_table.php'))->up();
        config(['satset.lrtj_space_notifications.enabled' => false, 'satset.web_push.enabled' => false]);
        $ga = $this->user('GA', 'ga');
        (new LrtjSpaceMobileNotificationService)->notifyTicketCreated($ticket);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->officer->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->requester->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $ga->id]);
    }

    public function test_invalid_submission_and_missing_reporting_line_leave_no_partial_request(): void
    {
        $data = $this->submission();
        $data['items'][0]['quantity'] = 0;
        $this->actingAs($this->requester)->post(route('procurement.store'), $data)->assertSessionHasErrors('items.0.quantity');
        $this->actingAs($this->requester)->post(route('procurement.store'), ['submission_token' => (string) Str::uuid(), 'items' => []])->assertSessionHasErrors('items');
        $this->mock(LrtjSpaceApprovalResolverService::class, fn ($mock) => $mock->shouldReceive('resolveFirstApprover')->andThrow(\Illuminate\Validation\ValidationException::withMessages(['approval_resolver' => 'Atasan belum tersedia.'])));
        $this->actingAs($this->requester)->post(route('procurement.store'), $this->submission())->assertSessionHasErrors('approval_resolver');
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('approvals', 0);
    }

    public function test_service_registration_is_repeatable_and_rollback_preserves_requests(): void
    {
        $ticket = $this->ticket(false);
        Schema::create('service_domains', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon');
            $table->integer('sort_order');
            $table->boolean('is_active');
            $table->timestamps();
        });
        Schema::table('requests', fn (Blueprint $table) => $table->unsignedBigInteger('service_domain_id')->nullable());
        DB::table('service_domains')->insert(['code' => 'it', 'name' => 'IT', 'icon' => 'bi-laptop', 'sort_order' => 3, 'is_active' => false]);
        $migration = require database_path('migrations/2026_11_24_000001_register_goods_procurement_service.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('service_domains', 2);
        $domain = DB::table('service_domains')->where('code', 'goods_procurement')->first();
        $this->assertSame(4, $domain->sort_order);
        $this->assertEquals($domain->id, $ticket->refresh()->service_domain_id);
        $this->assertEquals(0, DB::table('service_domains')->where('code', 'it')->value('is_active'));
        $migration->down();
        $this->assertDatabaseHas('requests', ['id' => $ticket->id, 'ticket_no' => $ticket->ticket_no, 'service_domain_id' => null]);
        $this->assertSame('WAITING_MANAGER_APPROVAL', $ticket->refresh()->payload['workflow_status']);
    }
}
