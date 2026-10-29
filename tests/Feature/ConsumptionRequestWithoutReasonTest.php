<?php

namespace Tests\Feature;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\LrtjSpaceApprovalResolverService;
use App\Services\LrtjSpaceMobileNotificationService;
use App\Services\LrtjSpacePortalSignatureService;
use App\Services\SatsetInitialApprovalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ConsumptionRequestWithoutReasonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['approvals', 'ticket_histories', 'attachments', 'requests', 'consumable_items', 'category_department', 'departments', 'statuses', 'ticket_categories', 'urgencies', 'impacts', 'priorities', 'problem_categories', 'users'] as $table) {
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
    }

    public function test_public_consumption_request_can_be_submitted_without_reason(): void
    {
        Mail::fake();

        $now = ['created_at' => now(), 'updated_at' => now()];
        DB::table('priorities')->insert(['name' => 'Medium', ...$now]);
        DB::table('impacts')->insert(['name' => 'Medium', ...$now]);
        DB::table('urgencies')->insert(['name' => 'Medium', ...$now]);
        DB::table('statuses')->insert(['name' => 'Open', ...$now]);
        DB::table('problem_categories')->insert(['code' => 'KONSUMSI', 'name' => 'Permintaan Konsumsi', 'description' => null, ...$now]);
        DB::table('ticket_categories')->insert(['code' => 'BUM', 'name' => 'Layanan BUM', 'description' => null, ...$now]);

        $approver = User::create([
            'name' => 'Manager Pemohon',
            'email' => 'manager@example.test',
            'username' => 'manager',
            'phone' => '0',
            'role' => 'manager',
            'password' => 'secret',
        ]);

        $this->mock(LrtjSpacePortalSignatureService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createRequesterSignature')->twice()->andReturn([
                'portal_signature_id' => 'signature-requester',
                'portal_signature_url' => 'https://portal.example.test/signatures/requester',
            ]);
        });
        $this->mock(LrtjSpaceApprovalResolverService::class, function (MockInterface $mock) use ($approver): void {
            $mock->shouldReceive('resolveFirstApprover')->twice()->andReturn($approver);
        });
        $this->mock(LrtjSpaceMobileNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyTicketCreated')->zeroOrMoreTimes();
            $mock->shouldReceive('notifyApprovalRequested')->twice();
            $mock->shouldReceive('notifyTicketStatusChanged')->zeroOrMoreTimes();
        });

        $submissionToken = (string) Str::uuid();
        $requestData = [
            'submission_token' => $submissionToken,
            'reporter_name' => 'Pemohon Konsumsi',
            'reporter_email' => 'pemohon@lrtjakarta.co.id',
            'payload' => [
                'activity_name' => 'Meeting Evaluasi',
                'event_type' => 'Meeting',
                'event_date' => now()->addDay()->format('Y-m-d'),
                'event_end_date' => now()->addDays(7)->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '11:00',
                'location' => 'Ruang Rapat Utama',
                'participant_count' => 12,
                'consumption_type' => 'Snack',
            ],
        ];

        $invalidDomainResponse = $this->from(route('public.ticket.konsumsi.create'))
            ->post(route('public.ticket.konsumsi.store'), [
                ...$requestData,
                'submission_token' => (string) Str::uuid(),
                'reporter_email' => 'pemohon@gmail.com',
            ]);

        $invalidDomainResponse->assertRedirect(route('public.ticket.konsumsi.create'))
            ->assertSessionHasErrors([
                'reporter_email' => 'Email harus menggunakan domain @lrtjakarta.co.id.',
            ]);
        $this->assertSame(0, DB::table('requests')->count());

        $tooLongResponse = $this->from(route('public.ticket.konsumsi.create'))
            ->post(route('public.ticket.konsumsi.store'), [
                ...$requestData,
                'submission_token' => (string) Str::uuid(),
                'payload' => [
                    ...$requestData['payload'],
                    'event_end_date' => now()->addDays(8)->format('Y-m-d'),
                ],
            ]);

        $tooLongResponse->assertRedirect(route('public.ticket.konsumsi.create'))
            ->assertSessionHasErrors([
                'payload.event_end_date' => 'Rentang tanggal kegiatan maksimal 7 hari kalender.',
            ]);
        $this->assertSame(0, DB::table('requests')->count());

        $response = $this->post(route('public.ticket.konsumsi.store'), $requestData);

        $response->assertRedirect(route('public.ticket.konsumsi.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $ticket = DB::table('requests')->first();
        $this->assertNotNull($ticket);
        $payload = json_decode($ticket->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Meeting Evaluasi', $payload['activity_name']);
        $this->assertSame($requestData['payload']['event_end_date'], $payload['event_end_date']);
        $this->assertArrayNotHasKey('request_reason', $payload);
        $this->assertDatabaseHas('approvals', ['request_id' => $ticket->id, 'approver_id' => $approver->id]);

        $duplicateResponse = $this->post(route('public.ticket.konsumsi.store'), $requestData);

        $duplicateResponse->assertRedirect(route('public.ticket.konsumsi.create'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'sudah tercatat'));
        $this->assertSame(1, DB::table('requests')->where('submission_token', $submissionToken)->count());
        $this->assertSame(1, DB::table('ticket_histories')->where('ticket_id', $ticket->id)->count());
        $this->assertSame(1, DB::table('approvals')->where('request_id', $ticket->id)->count());

        $reporter = User::where('email', $requestData['reporter_email'])->firstOrFail();
        $loggedInResponse = $this->actingAs($reporter)->post(route('ticket.store'), [
            'submission_token' => (string) Str::uuid(),
            'request_type' => 'consumption',
            'category_id' => $ticket->category_id,
            'ticket_category_id' => $ticket->ticket_category_id,
            'priority_id' => $ticket->priority_id,
            'impact_id' => $ticket->impact_id,
            'urgency_id' => $ticket->urgency_id,
            'payload' => [
                ...$requestData['payload'],
                'activity_name' => 'Pelatihan Tujuh Hari',
            ],
        ]);

        $loggedInResponse->assertRedirect(route('ticket.konsumsi.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertSame(2, DB::table('requests')->count());
        $loggedInPayload = json_decode(DB::table('requests')->latest('id')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($requestData['payload']['event_date'], $loggedInPayload['event_date']);
        $this->assertSame($requestData['payload']['event_end_date'], $loggedInPayload['event_end_date']);
        Mail::assertNothingSent();
    }

    public function test_team_ga_custom_account_skips_manager_and_routes_directly_to_ga(): void
    {
        config()->set('satset.approval_resolver.direct_ga_requester_emails', [
            'teamga@lrtjakarta.co.id',
        ]);

        $now = ['created_at' => now(), 'updated_at' => now()];
        $categoryId = DB::table('problem_categories')->insertGetId([
            'code' => 'KONSUMSI',
            'name' => 'Permintaan Konsumsi',
            'description' => null,
            ...$now,
        ]);
        $statusId = DB::table('statuses')->insertGetId(['name' => 'Open', ...$now]);

        $requester = User::create([
            'name' => 'Team GA',
            'email' => 'teamga@lrtjakarta.co.id',
            'username' => 'teamga',
            'phone' => '0',
            'role' => 'ga',
            'password' => 'secret',
        ]);
        $gaApprover = User::create([
            'name' => 'Kepala Bagian Umum',
            'email' => 'ga.head@lrtjakarta.co.id',
            'username' => 'ga.head',
            'phone' => '0',
            'role' => 'manager',
            'password' => 'secret',
        ]);

        $resolver = $this->mock(LrtjSpaceApprovalResolverService::class, function (MockInterface $mock) use ($gaApprover): void {
            $mock->shouldNotReceive('resolveFirstApprover');
            $mock->shouldReceive('resolveConsumptionBumApprover')->once()->andReturn($gaApprover);
        });
        $notifications = $this->mock(LrtjSpaceMobileNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyApprovalRequested')->once();
            $mock->shouldReceive('notifyTicketCreated')->zeroOrMoreTimes();
            $mock->shouldReceive('notifyTicketStatusChanged')->zeroOrMoreTimes();
        });
        $service = new SatsetInitialApprovalService($resolver, $notifications);

        $consumption = Ticket::create([
            'ticket_no' => 'KONSUMSI-TEAM-GA',
            'requester_id' => $requester->id,
            'category_id' => $categoryId,
            'title' => 'Permintaan Konsumsi Team GA',
            'status_id' => $statusId,
            'payload' => [
                'request_type' => 'consumption',
                'workflow_status' => 'WAITING_MANAGER_APPROVAL',
                'reporter_email' => 'teamga@lrtjakarta.co.id',
            ],
        ]);

        $approval = $service->create($consumption);

        $this->assertNotNull($approval);
        $this->assertSame(2, (int) $approval->level);
        $this->assertSame($gaApprover->id, (int) $approval->approver_id);
        $this->assertSame('WAITING_BUM_VERIFICATION', data_get($consumption->fresh()->payload, 'workflow_status'));
        $this->assertTrue((bool) data_get($consumption->fresh()->payload, 'manager_approval_skipped'));

        $atk = Ticket::create([
            'ticket_no' => 'ATK-TEAM-GA',
            'requester_id' => $requester->id,
            'category_id' => $categoryId,
            'title' => 'Permintaan ATK Team GA',
            'status_id' => $statusId,
            'payload' => [
                'request_type' => 'atk_rtk',
                'workflow_status' => 'WAITING_MANAGER_APPROVAL',
                'reporter_email' => 'teamga@lrtjakarta.co.id',
            ],
        ]);

        $this->assertNull($service->create($atk));
        $this->assertSame('WAITING_BUM_REVIEW', data_get($atk->fresh()->payload, 'workflow_status'));
        $this->assertDatabaseMissing('approvals', ['request_id' => $atk->id]);
    }

    public function test_team_ga_public_consumption_skips_portal_requester_lookup(): void
    {
        config()->set('satset.approval_resolver.direct_ga_requester_emails', [
            'teamga@lrtjakarta.co.id',
        ]);

        $now = ['created_at' => now(), 'updated_at' => now()];
        DB::table('priorities')->insert(['name' => 'Medium', ...$now]);
        DB::table('impacts')->insert(['name' => 'Medium', ...$now]);
        DB::table('urgencies')->insert(['name' => 'Medium', ...$now]);
        DB::table('statuses')->insert(['name' => 'Open', ...$now]);
        DB::table('problem_categories')->insert([
            'code' => 'KONSUMSI',
            'name' => 'Permintaan Konsumsi',
            'description' => null,
            ...$now,
        ]);
        DB::table('ticket_categories')->insert([
            'code' => 'BUM',
            'name' => 'Layanan BUM',
            'description' => null,
            ...$now,
        ]);

        $gaApprover = User::create([
            'name' => 'Kepala Bagian Umum',
            'email' => 'ga.head@lrtjakarta.co.id',
            'username' => 'ga.head',
            'phone' => '0',
            'role' => 'manager',
            'password' => 'secret',
        ]);

        $this->mock(LrtjSpacePortalSignatureService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createRequesterSignature')->once()->andReturnNull();
        });
        $this->mock(LrtjSpaceApprovalResolverService::class, function (MockInterface $mock) use ($gaApprover): void {
            $mock->shouldNotReceive('resolveFirstApprover');
            $mock->shouldNotReceive('resolveRequester');
            $mock->shouldReceive('resolveConsumptionBumApprover')->once()->andReturn($gaApprover);
        });
        $this->mock(LrtjSpaceMobileNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyTicketCreated')->zeroOrMoreTimes();
            $mock->shouldReceive('notifyTicketStatusChanged')->zeroOrMoreTimes();
            $mock->shouldReceive('notifyApprovalRequested')->once();
        });

        $response = $this->post(route('public.ticket.konsumsi.store'), [
            'submission_token' => (string) Str::uuid(),
            'reporter_name' => 'Team GA',
            'reporter_email' => 'teamga@lrtjakarta.co.id',
            'payload' => [
                'activity_name' => 'Koordinasi GA',
                'event_type' => 'Meeting',
                'event_date' => now()->addDay()->format('Y-m-d'),
                'event_end_date' => now()->addDay()->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '11:00',
                'location' => 'Ruang GA',
                'participant_count' => 5,
                'consumption_type' => 'Snack',
            ],
        ]);

        $response->assertRedirect(route('public.ticket.konsumsi.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $ticket = Ticket::where('requester_id', User::where('email', 'teamga@lrtjakarta.co.id')->value('id'))->firstOrFail();
        $this->assertSame('WAITING_BUM_VERIFICATION', data_get($ticket->payload, 'workflow_status'));
        $this->assertTrue((bool) data_get($ticket->payload, 'manager_approval_skipped'));
        $this->assertDatabaseHas('approvals', [
            'request_id' => $ticket->id,
            'approver_id' => $gaApprover->id,
            'level' => 2,
            'status' => 'Pending',
        ]);
    }

    public function test_logged_in_atk_request_persists_recipient_pic_from_form(): void
    {
        $now = ['created_at' => now(), 'updated_at' => now()];
        $priorityId = DB::table('priorities')->insertGetId(['name' => 'Medium', ...$now]);
        $impactId = DB::table('impacts')->insertGetId(['name' => 'Medium', ...$now]);
        $urgencyId = DB::table('urgencies')->insertGetId(['name' => 'Medium', ...$now]);
        DB::table('statuses')->insert(['name' => 'Open', ...$now]);
        $categoryId = DB::table('problem_categories')->insertGetId([
            'code' => 'ATKRTK',
            'name' => 'Permintaan ATK/RTK',
            'description' => null,
            ...$now,
        ]);
        $ticketCategoryId = DB::table('ticket_categories')->insertGetId([
            'code' => 'BUM',
            'name' => 'Layanan BUM',
            'description' => null,
            ...$now,
        ]);
        $itemId = DB::table('consumable_items')->insertGetId([
            'code' => 'I.45',
            'name' => 'Lakban Bening Besar',
            'large_uom' => 'pack',
            'small_uom' => 'pcs',
            'conversion_qty' => 6,
            'unit_price' => 8800,
            'small_stock' => 10,
            'current_stock' => 2,
            'is_active' => true,
            ...$now,
        ]);
        $requester = User::create([
            'name' => 'Pemohon ATK',
            'email' => 'pemohon.atk@lrtjakarta.co.id',
            'username' => 'pemohon.atk',
            'phone' => '0',
            'role' => 'user',
            'password' => 'secret',
        ]);

        $this->mock(LrtjSpacePortalSignatureService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createRequesterSignature')->once()->andReturnNull();
        });
        $this->mock(LrtjSpaceMobileNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyTicketCreated')->zeroOrMoreTimes();
            $mock->shouldReceive('notifyTicketStatusChanged')->zeroOrMoreTimes();
        });

        $response = $this->actingAs($requester)->post(route('ticket.store'), [
            'submission_token' => (string) Str::uuid(),
            'request_type' => 'atk_rtk',
            'category_id' => $categoryId,
            'ticket_category_id' => $ticketCategoryId,
            'priority_id' => $priorityId,
            'impact_id' => $impactId,
            'urgency_id' => $urgencyId,
            'payload' => [
                'request_subject' => 'Permintaan Lakban',
                'item_type' => 'ATK',
                'items' => [['item_id' => $itemId, 'quantity' => 1]],
                'needed_date' => now()->addDay()->format('Y-m-d'),
                'delivery_location' => 'MCC',
                'recipient_pic' => 'Budi PIC MCC',
            ],
        ]);

        $response->assertRedirect(route('ticket.atk-rtk.create'))
            ->assertSessionHasNoErrors();

        $ticket = Ticket::where('requester_id', $requester->id)->firstOrFail();
        $this->assertSame('Budi PIC MCC', data_get($ticket->payload, 'recipient_pic'));
    }

    public function test_public_ga_report_attachment_is_saved_and_can_be_viewed(): void
    {
        Mail::fake();
        Storage::fake('public');

        $now = ['created_at' => now(), 'updated_at' => now()];
        DB::table('priorities')->insert(['name' => 'Medium', ...$now]);
        DB::table('impacts')->insert(['name' => 'Medium', ...$now]);
        DB::table('urgencies')->insert(['name' => 'Medium', ...$now]);
        DB::table('statuses')->insert(['name' => 'Open', ...$now]);
        DB::table('problem_categories')->insert(['code' => 'GAQR', 'name' => 'GA Permintaan dan Temuan', 'description' => null, ...$now]);
        DB::table('ticket_categories')->insert(['code' => 'BUM', 'name' => 'Layanan BUM', 'description' => null, ...$now]);

        $file = UploadedFile::fake()->createWithContent(
            'kondisi-dinding.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );

        $requestWithoutAttachment = [
            'submission_token' => (string) Str::uuid(),
            'reporter_name' => 'Pelapor Tanpa Bukti',
            'reporter_email' => 'tanpa.bukti@lrtjakarta.co.id',
            'payload' => [
                'report_type' => 'Temuan',
                'location' => 'Lantai 5',
                'description' => 'Dinding perlu dicat ulang.',
            ],
        ];

        $this->from(route('public.ticket.ga-permintaan-temuan.create'))
            ->post(route('public.ticket.ga-permintaan-temuan.store'), $requestWithoutAttachment)
            ->assertRedirect(route('public.ticket.ga-permintaan-temuan.create'))
            ->assertSessionHasErrors([
                'evidence_file' => 'Bukti foto atau dokumen wajib dilampirkan.',
            ]);
        $this->assertSame(0, DB::table('requests')->count());

        $response = $this->post(route('public.ticket.ga-permintaan-temuan.store'), [
            'submission_token' => (string) Str::uuid(),
            'reporter_name' => 'Pelapor GA',
            'reporter_email' => 'pelapor.ga@lrtjakarta.co.id',
            'payload' => [
                'report_type' => 'Temuan',
                'location' => 'Lantai 5',
                'detail_location' => 'Ruang Audit Internal',
                'description' => 'Dinding perlu dicat ulang.',
                'expected_action' => 'Pengecatan ulang',
                'reporter_phone' => '08123456789',
            ],
            'evidence_file' => $file,
        ]);

        $response->assertRedirect(route('public.ticket.ga-permintaan-temuan.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $ticket = \App\Models\Master\Ticket::query()->firstOrFail();
        $attachment = \App\Models\Master\Attachment::query()->firstOrFail();

        $this->assertSame($ticket->id, $attachment->request_id);
        $this->assertSame('ga_report_evidence', $attachment->attachment_type);
        $this->assertSame('kondisi-dinding.png', $attachment->file_name);
        Storage::disk('public')->assertExists($attachment->file_path);

        $reporter = User::where('email', 'pelapor.ga@lrtjakarta.co.id')->firstOrFail();
        $this->actingAs($reporter)
            ->get(route('ticket.attachments.show', [$ticket, $attachment]))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=kondisi-dinding.png');

        Mail::assertNothingSent();

        $this->actingAs($reporter)
            ->from(route('ticket.ga-permintaan-temuan.create'))
            ->post(route('ticket.store'), [
                ...$requestWithoutAttachment,
                'submission_token' => (string) Str::uuid(),
                'request_type' => 'ga_request_finding',
                'category_id' => $ticket->category_id,
                'ticket_category_id' => $ticket->ticket_category_id,
                'priority_id' => $ticket->priority_id,
                'impact_id' => $ticket->impact_id,
                'urgency_id' => $ticket->urgency_id,
            ])
            ->assertRedirect(route('ticket.ga-permintaan-temuan.create'))
            ->assertSessionHasErrors([
                'evidence_file' => 'Bukti foto atau dokumen wajib dilampirkan.',
            ]);
        $this->assertSame(1, DB::table('requests')->count());
    }

    public function test_ga_team_can_reopen_closed_consumption_ticket_with_reason(): void
    {
        $now = ['created_at' => now(), 'updated_at' => now()];
        $openStatus = DB::table('statuses')->insertGetId(['name' => 'Open', ...$now]);
        $closedStatus = DB::table('statuses')->insertGetId(['name' => 'Closed', ...$now]);
        $categoryId = DB::table('problem_categories')->insertGetId([
            'code' => 'KONSUMSI',
            'name' => 'Permintaan Konsumsi',
            'description' => null,
            ...$now,
        ]);
        $ticketCategoryId = DB::table('ticket_categories')->insertGetId([
            'code' => 'BUM',
            'name' => 'Layanan BUM',
            'description' => null,
            ...$now,
        ]);

        $requester = User::create([
            'name' => 'Pemohon Konsumsi',
            'email' => 'pemohon.reopen@lrtjakarta.co.id',
            'username' => 'pemohon.reopen',
            'phone' => '0',
            'role' => 'user',
            'password' => 'secret',
        ]);
        $gaOfficer = User::create([
            'name' => 'Petugas GA',
            'email' => 'ga.reopen@lrtjakarta.co.id',
            'username' => 'ga.reopen',
            'phone' => '0',
            'role' => 'ga',
            'password' => 'secret',
        ]);
        $ticket = \App\Models\Master\Ticket::create([
            'ticket_no' => 'KONSUMSI-REOPEN-001',
            'requester_id' => $requester->id,
            'category_id' => $categoryId,
            'ticket_category_id' => $ticketCategoryId,
            'status_id' => $closedStatus,
            'title' => 'Permintaan konsumsi rapat',
            'payload' => [
                'request_type' => 'consumption',
                'workflow_status' => 'CLOSED',
                'consumption_type' => 'Snack + Makan + Coffee',
            ],
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);

        $this->mock(LrtjSpaceMobileNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyTicketStatusChanged')->once();
        });

        $this->actingAs($gaOfficer)
            ->from(route('ticket.show', $ticket))
            ->post(route('ticket.consumption.reopen', $ticket), [
                'reason' => 'Dokumen pertanggungjawaban perlu diperbaiki.',
            ])
            ->assertRedirect(route('ticket.show', $ticket))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Tiket konsumsi berhasil dibuka kembali.');

        $ticket->refresh();
        $this->assertSame($openStatus, $ticket->status_id);
        $this->assertSame('REOPENED', data_get($ticket->payload, 'workflow_status'));
        $this->assertSame('Dokumen pertanggungjawaban perlu diperbaiki.', data_get($ticket->payload, 'reopen_reason'));
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $gaOfficer->id,
            'status_id' => $openStatus,
            'action' => 'Tiket konsumsi dibuka kembali. Alasan: Dokumen pertanggungjawaban perlu diperbaiki.',
        ]);
    }

    public function test_approver_still_sees_ticket_after_approval_is_completed(): void
    {
        $now = ['created_at' => now(), 'updated_at' => now()];
        $categoryId = DB::table('problem_categories')->insertGetId([
            'code' => 'KONSUMSI',
            'name' => 'Permintaan Konsumsi',
            'description' => null,
            ...$now,
        ]);
        $statusId = DB::table('statuses')->insertGetId(['name' => 'Closed', ...$now]);
        $requester = User::create([
            'name' => 'Pemohon Approval',
            'email' => 'pemohon.approval@lrtjakarta.co.id',
            'username' => 'pemohon.approval',
            'role' => 'user',
            'password' => 'secret',
        ]);
        $approver = User::create([
            'name' => 'Atasan Approver',
            'email' => 'atasan.approval@lrtjakarta.co.id',
            'username' => 'atasan.approval',
            'role' => 'user',
            'password' => 'secret',
        ]);

        $approvedTicket = Ticket::create([
            'ticket_no' => 'TCK-APPROVED-VISIBLE',
            'requester_id' => $requester->id,
            'category_id' => $categoryId,
            'status_id' => $statusId,
            'title' => 'Tiket yang sudah disetujui',
            'payload' => [
                'request_type' => 'consumption',
                'workflow_status' => 'CLOSED',
            ],
        ]);
        $unrelatedTicket = Ticket::create([
            'ticket_no' => 'TCK-UNRELATED-HIDDEN',
            'requester_id' => $requester->id,
            'category_id' => $categoryId,
            'status_id' => $statusId,
            'title' => 'Tiket tanpa relasi approver',
            'payload' => [
                'request_type' => 'consumption',
                'workflow_status' => 'CLOSED',
            ],
        ]);
        DB::table('approvals')->insert([
            'request_id' => $approvedTicket->id,
            'approver_id' => $approver->id,
            'status' => 'approved',
            'level' => 1,
            ...$now,
        ]);

        $this->actingAs($approver);
        $view = app(\App\Http\Controllers\Master\TicketController::class)
            ->ticketsIndex(\Illuminate\Http\Request::create('/ticket/tiket-saya', 'GET'));
        $visibleTicketNumbers = $view->getData()['tickets']->getCollection()->pluck('ticket_no');

        $this->assertTrue($visibleTicketNumbers->contains($approvedTicket->ticket_no));
        $this->assertFalse($visibleTicketNumbers->contains($unrelatedTicket->ticket_no));
    }

    public function test_requester_can_sync_pending_manager_approval_from_portal(): void
    {
        $now = ['created_at' => now(), 'updated_at' => now()];
        $openStatus = DB::table('statuses')->insertGetId(['name' => 'Open', ...$now]);
        $categoryId = DB::table('problem_categories')->insertGetId([
            'code' => 'KONSUMSI',
            'name' => 'Permintaan Konsumsi',
            'description' => null,
            ...$now,
        ]);

        $requester = User::create([
            'name' => 'Pemohon',
            'email' => 'pemohon.sync@lrtjakarta.co.id',
            'role' => 'user',
            'password' => 'secret',
        ]);
        $oldApprover = User::create([
            'name' => 'Atasan A',
            'email' => 'atasan.a@lrtjakarta.co.id',
            'role' => 'manager',
            'password' => 'secret',
        ]);
        $newApprover = User::create([
            'name' => 'Atasan B',
            'email' => 'atasan.b@lrtjakarta.co.id',
            'role' => 'manager',
            'password' => 'secret',
        ]);

        $ticket = \App\Models\Master\Ticket::create([
            'ticket_no' => 'KONSUMSI-SYNC-001',
            'requester_id' => $requester->id,
            'category_id' => $categoryId,
            'status_id' => $openStatus,
            'title' => 'Permintaan konsumsi',
            'payload' => [
                'request_type' => 'consumption',
                'workflow_status' => 'WAITING_MANAGER_APPROVAL',
                'supervisor_id' => $oldApprover->id,
                'supervisor_name' => $oldApprover->name,
            ],
        ]);
        $approvalId = DB::table('approvals')->insertGetId([
            'request_id' => $ticket->id,
            'approver_id' => $oldApprover->id,
            'status' => 'Pending',
            'level' => 1,
            ...$now,
        ]);

        $this->mock(LrtjSpaceApprovalResolverService::class, function (MockInterface $mock) use ($ticket, $newApprover): void {
            $mock->shouldReceive('resolveFirstApprover')
                ->once()
                ->withArgs(fn ($resolvedTicket) => (int) $resolvedTicket->id === (int) $ticket->id)
                ->andReturn($newApprover);
        });
        $this->mock(LrtjSpaceMobileNotificationService::class, function (MockInterface $mock) use ($approvalId): void {
            $mock->shouldReceive('notifyApprovalRequested')
                ->once()
                ->withArgs(fn ($notifiedTicket, $approval) => (int) $approval->id === (int) $approvalId);
            $mock->shouldReceive('notifyTicketStatusChanged')->zeroOrMoreTimes();
        });

        $this->actingAs($requester)
            ->post(route('ticket.approvals.sync-manager', $ticket))
            ->assertRedirect(route('ticket.show', $ticket))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Atasan B'));

        $ticket->refresh();
        $this->assertDatabaseHas('approvals', [
            'id' => $approvalId,
            'approver_id' => $newApprover->id,
            'status' => 'Pending',
        ]);
        $this->assertSame($newApprover->id, data_get($ticket->payload, 'supervisor_id'));
        $this->assertSame('Atasan B', data_get($ticket->payload, 'supervisor_name'));
        $this->assertNotNull(data_get($ticket->payload, 'manager_approval_synced_at'));
        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'action' => 'Approval atasan disinkronkan dari Portal: Atasan A menjadi Atasan B.',
        ]);
    }
}
