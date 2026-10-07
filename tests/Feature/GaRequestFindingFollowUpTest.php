<?php

namespace Tests\Feature;

use App\Models\Master\Attachment;
use App\Models\Master\Ticket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
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
        config([
            'satset.portal_signatures.shared_secret' => 'test-secret',
            'satset.portal_signatures.base_url' => 'https://portal.example.test',
            'satset.portal_signatures.endpoint' => '/api/signatures',
        ]);
        Http::fakeSequence()
            ->push(['data' => [
                'signature_id' => 'sig-requester',
                'verify_url' => 'https://portal.example.test/signatures/sig-requester',
            ]])
            ->push(['data' => [
                'signature_id' => 'sig-ga-officer',
                'verify_url' => 'https://portal.example.test/signatures/sig-ga-officer',
            ]]);

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
                'follow_up_evidence_file' => UploadedFile::fake()->createWithContent(
                    'bukti.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
                ),
            ]
        );

        $response->assertRedirect()->assertSessionHas('success');
        $ticket->refresh();

        $this->assertSame('CLOSED', data_get($ticket->payload, 'workflow_status'));
        $this->assertSame('sig-requester', data_get($ticket->payload, 'portal_signatures.requester.portal_signature_id'));
        $this->assertSame('sig-ga-officer', data_get($ticket->payload, 'portal_signatures.ga_officer.portal_signature_id'));
        $this->assertSame($closedStatus, $ticket->status_id);
        $this->assertNotNull($ticket->closed_at);
        $this->assertDatabaseHas('attachments', [
            'request_id' => $ticket->id,
            'uploaded_by' => $gaOfficer->id,
            'attachment_type' => 'ga_follow_up_evidence',
            'file_name' => 'bukti.png',
        ]);
        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $gaOfficer->id,
            'status_id' => $closedStatus,
        ]);

        $pdfEvidence = Pdf::loadHTML(
            '<h1>Evidence PDF</h1><p>Halaman satu.</p><div style="page-break-before:always"></div><p>Halaman dua.</p>'
        )->output();
        Storage::disk('public')->put('request-attachments/'.$ticket->id.'/evidence-tambahan.pdf', $pdfEvidence);
        Attachment::create([
            'request_id' => $ticket->id,
            'uploaded_by' => $gaOfficer->id,
            'file_name' => 'evidence-tambahan.pdf',
            'file_path' => 'request-attachments/'.$ticket->id.'/evidence-tambahan.pdf',
            'mime_type' => 'application/pdf',
            'size' => strlen($pdfEvidence),
            'attachment_type' => 'ga_follow_up_evidence',
            'uploaded_at' => now(),
        ]);

        $report = $this->actingAs($requester)->get(route('ticket.ga-request-finding.report', $ticket));

        $report->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="laporan-tck-ga-0001.pdf"');
        $this->assertStringStartsWith('%PDF', $report->getContent());
        $pdfReader = new Fpdi;
        $this->assertGreaterThanOrEqual(
            4,
            $pdfReader->setSourceFile(StreamReader::createByString($report->getContent()))
        );
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
