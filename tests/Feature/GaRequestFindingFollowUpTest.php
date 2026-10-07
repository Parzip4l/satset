<?php

namespace Tests\Feature;

use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GaRequestFindingFollowUpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ticket_histories', 'attachments', 'requests', 'statuses', 'users'] as $table) {
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
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->foreignId('requester_id');
            $table->string('title');
            $table->foreignId('status_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
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
        Schema::create('ticket_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('user_id');
            $table->foreignId('status_id')->nullable();
            $table->string('action')->nullable();
            $table->timestamps();
        });
    }

    public function test_ga_team_can_upload_follow_up_evidence_and_close_ticket(): void
    {
        Storage::fake('public');

        $requester = $this->user('Requester', 'requester@example.test', 'employee');
        $gaOfficer = $this->user('GA Officer', 'ga@example.test', 'ga');
        $openStatus = DB::table('statuses')->insertGetId(['name' => 'Open', 'created_at' => now(), 'updated_at' => now()]);
        $closedStatus = DB::table('statuses')->insertGetId(['name' => 'Closed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('statuses')->insert(['name' => 'In Progress', 'created_at' => now(), 'updated_at' => now()]);

        $ticket = Ticket::create([
            'ticket_no' => 'TCK-GA-0001',
            'requester_id' => $requester->id,
            'title' => 'GA Temuan - Lobby',
            'status_id' => $openStatus,
            'payload' => [
                'request_type' => 'ga_request_finding',
                'workflow_status' => 'WAITING_BUM_REVIEW',
            ],
        ]);

        $response = $this->actingAs($gaOfficer)->post(
            route('ticket.ga-request-finding.follow-up', $ticket),
            [
                'workflow_status' => 'CLOSED',
                'follow_up_notes' => 'Lampu lobby sudah diganti dan berfungsi normal.',
                'follow_up_evidence_file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
            ]
        );

        $response->assertRedirect()->assertSessionHas('success');
        $ticket->refresh();

        $this->assertSame('CLOSED', data_get($ticket->payload, 'workflow_status'));
        $this->assertSame($closedStatus, $ticket->status_id);
        $this->assertNotNull($ticket->closed_at);
        $this->assertDatabaseHas('attachments', [
            'request_id' => $ticket->id,
            'uploaded_by' => $gaOfficer->id,
            'attachment_type' => 'ga_follow_up_evidence',
            'file_name' => 'bukti.pdf',
        ]);
        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $gaOfficer->id,
            'status_id' => $closedStatus,
        ]);
    }

    public function test_non_ga_user_cannot_submit_follow_up(): void
    {
        $requester = $this->user('Requester', 'requester2@example.test', 'employee');
        $openStatus = DB::table('statuses')->insertGetId(['name' => 'Open', 'created_at' => now(), 'updated_at' => now()]);
        $ticket = Ticket::create([
            'ticket_no' => 'TCK-GA-0002',
            'requester_id' => $requester->id,
            'title' => 'GA Permintaan - Lobby',
            'status_id' => $openStatus,
            'payload' => [
                'request_type' => 'ga_request_finding',
                'workflow_status' => 'WAITING_BUM_REVIEW',
            ],
        ]);

        $this->actingAs($requester)
            ->post(route('ticket.ga-request-finding.follow-up', $ticket), [])
            ->assertForbidden();
    }

    private function user(string $name, string $email, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
        ]);
    }
}
