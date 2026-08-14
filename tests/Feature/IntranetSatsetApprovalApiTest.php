<?php

namespace Tests\Feature;

use App\Models\Master\Approval;
use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntranetSatsetApprovalApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('approval_audits');
        Schema::dropIfExists('ticket_histories');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('requests');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('role')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->foreignId('requester_id');
            $table->foreignId('department_id')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('priority_id')->nullable();
            $table->foreignId('impact_id')->nullable();
            $table->foreignId('urgency_id')->nullable();
            $table->foreignId('status_id')->nullable();
            $table->foreignId('ticket_category_id')->nullable();
            $table->foreignId('assigned_user_id')->nullable();
            $table->foreignId('assigned_department_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id');
            $table->foreignId('approver_id');
            $table->integer('level');
            $table->string('status', 20)->default('Pending');
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('last_action_source', 40)->nullable();
            $table->string('portal_reference_id', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('user_id');
            $table->foreignId('status_id')->nullable();
            $table->string('action')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id');
            $table->foreignId('ticket_id');
            $table->foreignId('approver_id')->nullable();
            $table->string('source', 40);
            $table->string('status', 20);
            $table->text('comment')->nullable();
            $table->string('satset_reference_id', 120);
            $table->string('external_reference_id', 120)->nullable();
            $table->string('approver_email')->nullable();
            $table->string('approver_name')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });

        config(['satset.intranet_api.shared_secret' => 'portal-secret']);
    }

    public function test_portal_can_fetch_pending_satset_approvals_with_signature(): void
    {
        $approval = $this->approvalFixture();

        $response = $this->signedCall('GET', '/api/intranet/v1/satset/approvals?status=pending&approver_email=manager@lrtjakarta.co.id');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.approval_id', $approval->id)
            ->assertJsonPath('data.data.0.module', 'satset')
            ->assertJsonPath('data.data.0.ticket.ticket_no', 'TCK-ATKRTK-0001')
            ->assertJsonPath('data.data.0.approver.email', 'manager@lrtjakarta.co.id');
    }

    public function test_portal_list_requires_logged_in_approver_email(): void
    {
        $this->approvalFixture();

        $this->signedCall('GET', '/api/intranet/v1/satset/approvals?status=pending')
            ->assertStatus(422);
    }

    public function test_portal_cannot_view_other_users_approval_detail(): void
    {
        $approval = $this->approvalFixture();

        $this->signedCall('GET', "/api/intranet/v1/satset/approvals/{$approval->id}?approver_email=other@lrtjakarta.co.id")
            ->assertForbidden();
    }

    public function test_portal_decision_updates_satset_status_and_writes_audit(): void
    {
        $approval = $this->approvalFixture();

        $response = $this->signedCall('POST', "/api/intranet/v1/satset/approvals/{$approval->id}/decision", [
            'status' => 'approved',
            'comment' => 'Approved from portal',
            'portal_reference_id' => 'PORTAL-APP-1',
            'approver' => [
                'email' => 'manager@lrtjakarta.co.id',
                'name' => 'Manager Portal',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.last_action_source', 'portal_intranet')
            ->assertJsonPath('ticket.workflow_status', 'WAITING_BUM_REVIEW');

        $this->assertDatabaseHas('approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'last_action_source' => 'portal_intranet',
            'portal_reference_id' => 'PORTAL-APP-1',
        ]);
        $this->assertDatabaseHas('approval_audits', [
            'approval_id' => $approval->id,
            'source' => 'portal_intranet',
            'status' => 'approved',
            'comment' => 'Approved from portal',
            'satset_reference_id' => (string) $approval->id,
            'external_reference_id' => 'PORTAL-APP-1',
            'approver_email' => 'manager@lrtjakarta.co.id',
            'approver_name' => 'Manager Portal',
        ]);
    }

    public function test_portal_cannot_process_approval_twice(): void
    {
        $approval = $this->approvalFixture();
        $payload = [
            'status' => 'approved',
            'approver' => ['email' => 'manager@lrtjakarta.co.id'],
        ];

        $this->signedCall('POST', "/api/intranet/v1/satset/approvals/{$approval->id}/decision", $payload)->assertOk();

        $this->signedCall('POST', "/api/intranet/v1/satset/approvals/{$approval->id}/decision", $payload)
            ->assertStatus(409);
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->approvalFixture();

        $this->getJson('/api/intranet/v1/satset/approvals', [
            'X-Satset-Timestamp' => (string) now()->timestamp,
            'X-Satset-Signature' => 'bad-signature',
        ])->assertUnauthorized();
    }

    private function approvalFixture(): Approval
    {
        $requester = User::forceCreate([
            'name' => 'Requester',
            'email' => 'requester@lrtjakarta.co.id',
            'password' => 'secret',
        ]);
        $approver = User::forceCreate([
            'name' => 'Manager',
            'email' => 'manager@lrtjakarta.co.id',
            'password' => 'secret',
            'role' => 'approver',
        ]);

        $ticket = Ticket::create([
            'ticket_no' => 'TCK-ATKRTK-0001',
            'requester_id' => $requester->id,
            'title' => 'Permintaan ATK/RTK',
            'description' => 'Need approval',
            'payload' => [
                'request_type' => 'atk_rtk',
                'workflow_status' => 'WAITING_MANAGER_APPROVAL',
                'total_estimated_amount' => 150000,
            ],
        ]);

        return Approval::create([
            'request_id' => $ticket->id,
            'approver_id' => $approver->id,
            'level' => 1,
            'status' => 'Pending',
        ]);
    }

    private function signedCall(string $method, string $uri, ?array $payload = null)
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'portal-secret');

        return $this->call($method, $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SATSET_TIMESTAMP' => $timestamp,
            'HTTP_X_SATSET_SIGNATURE' => $signature,
        ], $body);
    }
}
