<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LrtjSpaceApprovalResolverService;
use App\Services\LrtjSpaceMobileNotificationService;
use App\Services\LrtjSpacePortalSignatureService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ConsumptionRequestWithoutReasonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['approvals', 'ticket_histories', 'attachments', 'requests', 'category_department', 'departments', 'statuses', 'ticket_categories', 'urgencies', 'impacts', 'priorities', 'problem_categories', 'users'] as $table) {
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
            $mock->shouldReceive('createRequesterSignature')->once()->andReturn([
                'portal_signature_id' => 'signature-requester',
                'portal_signature_url' => 'https://portal.example.test/signatures/requester',
            ]);
        });
        $this->mock(LrtjSpaceApprovalResolverService::class, function (MockInterface $mock) use ($approver): void {
            $mock->shouldReceive('resolveFirstApprover')->once()->andReturn($approver);
        });
        $this->mock(LrtjSpaceMobileNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyApprovalRequested')->once();
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

        $response = $this->post(route('public.ticket.konsumsi.store'), $requestData);

        $response->assertRedirect(route('public.ticket.konsumsi.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $ticket = DB::table('requests')->first();
        $this->assertNotNull($ticket);
        $payload = json_decode($ticket->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Meeting Evaluasi', $payload['activity_name']);
        $this->assertArrayNotHasKey('request_reason', $payload);
        $this->assertDatabaseHas('approvals', ['request_id' => $ticket->id, 'approver_id' => $approver->id]);

        $duplicateResponse = $this->post(route('public.ticket.konsumsi.store'), $requestData);

        $duplicateResponse->assertRedirect(route('public.ticket.konsumsi.create'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'sudah tercatat'));
        $this->assertSame(1, DB::table('requests')->where('submission_token', $submissionToken)->count());
        $this->assertSame(1, DB::table('ticket_histories')->where('ticket_id', $ticket->id)->count());
        $this->assertSame(1, DB::table('approvals')->where('request_id', $ticket->id)->count());
    }
}
