<?php

namespace Tests\Unit;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\LrtjSpaceApprovalResolverService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LrtjSpaceApprovalResolverServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->default('karyawan');
            $table->string('nik')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('kartu_uang_1')->nullable();
            $table->string('kartu_uang_2')->nullable();
            $table->string('role')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        config([
            'satset.approval_resolver.base_url' => 'https://space.test',
            'satset.approval_resolver.endpoint' => '/api/v1/approval/resolve',
            'satset.approval_resolver.shared_secret' => 'resolver-secret',
            'satset.approval_resolver.verify_ssl' => true,
        ]);
    }

    public function test_resolves_first_approver_with_signed_raw_json_payload(): void
    {
        Http::fake([
            'https://space.test/api/v1/approval/resolve' => Http::response([
                'data' => [
                    'steps' => [
                        ['approver' => ['id' => 'mgr-1', 'email' => 'manager@lrtjakarta.co.id', 'name' => 'Manager LRTJ']],
                    ],
                ],
            ]),
        ]);

        $ticket = $this->ticket('atk_rtk', 150000);

        $approver = app(LrtjSpaceApprovalResolverService::class)->resolveFirstApprover($ticket);

        $this->assertSame('manager@lrtjakarta.co.id', $approver->email);
        $this->assertSame('Manager LRTJ', $approver->name);

        Http::assertSent(function ($request) {
            $timestamp = $request->header('X-Satset-Timestamp')[0] ?? null;
            $body = $request->body();

            $this->assertSame('https://space.test/api/v1/approval/resolve', (string) $request->url());
            $this->assertSame('application/json', $request->header('Content-Type')[0] ?? null);
            $this->assertSame(hash_hmac('sha256', $timestamp.'.'.$body, 'resolver-secret'), $request->header('X-Satset-Signature')[0] ?? null);
            $this->assertSame([
                'module' => 'satset',
                'request_type' => 'atk_rtk',
                'requester_email' => 'requester@lrtjakarta.co.id',
                'amount' => 150000,
                'metadata' => [
                    'ticket_id' => '99',
                    'ticket_no' => 'TCK-ATKRTK-0001',
                ],
            ], json_decode($body, true));

            return true;
        });
    }

    public function test_throws_clear_validation_error_for_422_response(): void
    {
        Http::fake([
            'https://space.test/api/v1/approval/resolve' => Http::response(['message' => 'Approver tidak ditemukan'], 422),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Approval resolver menolak request: Approver tidak ditemukan');

        app(LrtjSpaceApprovalResolverService::class)->resolveFirstApprover($this->ticket('consumption', 0));
    }

    public function test_accepts_portal_response_with_data_approver_shape(): void
    {
        Http::fake([
            'https://space.test/api/v1/approval/resolve' => Http::response([
                'data' => [
                    'approver' => [
                        'id' => 'portal-123',
                        'email' => 'portal.manager@lrtjakarta.co.id',
                        'name' => 'Portal Manager',
                    ],
                ],
            ]),
        ]);

        $approver = app(LrtjSpaceApprovalResolverService::class)->resolveFirstApprover($this->ticket('consumption', 0));

        $this->assertSame('portal.manager@lrtjakarta.co.id', $approver->email);
        $this->assertSame('Portal Manager', $approver->name);
    }

    public function test_accepts_portal_response_with_approvers_array_shape(): void
    {
        Http::fake([
            'https://space.test/api/v1/approval/resolve' => Http::response([
                'approvers' => [
                    [
                        'user_id' => 'portal-456',
                        'email' => 'array.manager@lrtjakarta.co.id',
                        'full_name' => 'Array Manager',
                    ],
                ],
            ]),
        ]);

        $approver = app(LrtjSpaceApprovalResolverService::class)->resolveFirstApprover($this->ticket('consumption', 0));

        $this->assertSame('array.manager@lrtjakarta.co.id', $approver->email);
        $this->assertSame('Array Manager', $approver->name);
    }

    public function test_throws_clear_validation_error_when_steps_are_missing(): void
    {
        Http::fake([
            'https://space.test/api/v1/approval/resolve' => Http::response(['data' => ['steps' => []]]),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Approval resolver tidak mengembalikan approver');

        app(LrtjSpaceApprovalResolverService::class)->resolveFirstApprover($this->ticket('consumption', 0));
    }

    private function ticket(string $requestType, float $amount): Ticket
    {
        $requester = User::forceCreate([
            'name' => 'Requester LRTJ',
            'email' => 'requester@lrtjakarta.co.id',
            'password' => 'secret',
        ]);

        $ticket = new Ticket([
            'ticket_no' => 'TCK-ATKRTK-0001',
            'payload' => [
                'request_type' => $requestType,
                'total_estimated_amount' => $amount,
            ],
        ]);
        $ticket->id = 99;
        $ticket->requester_id = $requester->id;
        $ticket->setRelation('requester', $requester);

        return $ticket;
    }
}
