<?php

namespace Tests\Feature;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Support\Sppd;
use Database\Seeders\TicketMasterSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SppdWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private bool $signatureUnavailable = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TicketMasterSeeder::class);
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->boolean('is_active');
            $table->integer('order');
        });
        config([
            'satset.approval_resolver.base_url' => 'https://portal.example.test',
            'satset.approval_resolver.shared_secret' => 'sppd-test',
            'satset.portal_signatures.base_url' => 'https://portal.example.test',
            'satset.portal_signatures.shared_secret' => 'sppd-test',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'portal.example.test/api/v1/approval/resolve' => function (Request $request) {
                $position = $request['target_position'] ?? null;
                if ($position) {
                    $role = $position === config('satset.sppd.positions.2') ? 'hr' : 'director';

                    return Http::response(['data' => ['position_holder' => $this->person($role)]]);
                }

                return Http::response(['data' => [
                    'requester' => [
                        'name' => 'Pemohon Contoh',
                        'employee_number' => '001234',
                        'position' => 'Staf Operasional',
                        'organization' => ['division' => ['name' => 'Operasi']],
                    ],
                    'steps' => [['approver' => $this->person('manager')]],
                ]]);
            },
            'portal.example.test/api/v1/signatures' => function (Request $request) {
                if ($this->signatureUnavailable) {
                    return Http::response([], 503);
                }
                $role = $request['role'];
                $url = 'https://portal.example.test/verify/sppd-'.$role;

                return Http::response(['data' => ['signature' => [
                    'portal_signature_id' => 'sig-'.$role,
                    'portal_signature_url' => $url,
                    'portal_qr_payload' => [
                        'verification_url' => $url,
                        'signer' => ['name' => ucfirst($role).' Contoh', 'position' => 'Jabatan Contoh'],
                    ],
                ]]]);
            },
        ]);
    }

    public function test_revised_sppd_issues_a_pdf_with_four_portal_verification_links(): void
    {
        $owner = $this->createUser('owner');
        $data = $this->input();
        $this->actingAs($owner)->get(route('sppd.create'))->assertOk()->assertSee('Kategori Perjalanan Dinas');
        $this->post(route('sppd.store'), $data)->assertRedirect();
        $ticket = Ticket::sole();
        $this->post(route('sppd.store'), $data)->assertRedirect(route('sppd.show', $ticket));
        $this->get(route('sppd.pdf', $ticket))->assertNotFound();
        $this->get(route('sppd.index'))->assertOk()->assertSee('Bandung');
        $this->assertSame(60000100, data_get($ticket->payload, 'sppd.total_cents'));
        $this->assertSame(4500000, data_get($ticket->payload, 'sppd.employee_cents'));
        $this->assertSame(2, data_get($ticket->payload, 'sppd.days'));
        $this->assertSame('001234', data_get($ticket->payload, 'sppd.profile.nik'));

        $first = $ticket->approvals()->sole();
        $this->post(route('sppd.decide', [$ticket, $first]), ['decision' => 'approved'])->assertForbidden();
        $manager = $first->approver;
        $this->actingAs($manager)->get(route('ticket.approvals'))->assertOk()->assertSee('Tinjau SPPD');
        $this->get(route('sppd.show', $ticket))->assertOk()->assertSee('Konfirmasi Mengetahui');
        $this->postJson(route('sppd.decide', [$ticket, $first]), ['decision' => 'revision_requested'])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->post(route('sppd.decide', [$ticket, $first]), ['decision' => 'revision_requested', 'note' => 'Lengkapi agenda.'])
            ->assertRedirect(route('sppd.show', $ticket));

        $this->actingAs($owner)->get(route('sppd.edit', $ticket))->assertOk()->assertSee('Ajukan Ulang');
        $this->put(route('sppd.update', $ticket), array_replace($data, ['purpose' => 'Koordinasi lapangan dan pembahasan agenda', 'version' => 1]))
            ->assertRedirect(route('sppd.show', $ticket));
        $this->assertSame(2, data_get($ticket->fresh()->payload, 'sppd.version'));
        $this->actingAs($manager)->post(route('sppd.decide', [$ticket, $first]), ['decision' => 'approved'])->assertConflict();

        foreach ([1, 2, 3] as $level) {
            $approval = $ticket->approvals()->where('status', 'Pending')->sole();
            $this->assertSame($level, (int) $approval->level);
            $this->actingAs($approval->approver)
                ->post(route('sppd.decide', [$ticket, $approval]), ['decision' => 'approved'])
                ->assertRedirect(route('sppd.show', $ticket));
        }
        $this->assertSame('Terbit', Sppd::label($ticket->refresh()));
        $this->assertDatabaseCount('approval_audits', 4);
        $this->actingAs($owner)->get(route('sppd.show', $ticket))->assertOk()->assertSee('Unduh PDF SPPD');
        $response = $this->get(route('sppd.pdf', $ticket))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        foreach (Sppd::STAGES as [, , $role]) {
            $this->assertStringContainsString('https://portal.example.test/verify/sppd-'.$role, $response->getContent());
        }
        $unrelated = $this->createUser('unrelated');
        $this->actingAs($unrelated)->get(route('sppd.pdf', $ticket))->assertForbidden();
        $this->actingAs($owner)->patch(route('ticket.update', $ticket->id), ['title' => 'Ubah lewat tiket'])->assertNotFound();
    }

    public function test_a_portal_failure_leaves_submission_or_approval_available_for_retry(): void
    {
        $owner = $this->createUser('owner');
        $data = $this->input();
        $this->signatureUnavailable = true;
        $this->actingAs($owner)->postJson(route('sppd.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('portal_signature');
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('approvals', 0);

        $this->signatureUnavailable = false;
        $this->post(route('sppd.store'), $data)->assertRedirect();
        $ticket = Ticket::sole();
        $approval = $ticket->approvals()->sole();
        $this->signatureUnavailable = true;
        $this->actingAs($approval->approver)->postJson(route('sppd.decide', [$ticket, $approval]), ['decision' => 'approved'])
            ->assertUnprocessable()->assertJsonValidationErrors('portal_signature');
        $this->assertSame('Pending', $approval->fresh()->status);
        $this->assertDatabaseCount('approval_audits', 0);

        $this->post(route('sppd.decide', [$ticket, $approval]), ['decision' => 'rejected', 'note' => 'Perjalanan dibatalkan.'])
            ->assertRedirect(route('sppd.show', $ticket));
        $this->assertSame('Ditolak', Sppd::label($ticket->fresh()));
        $this->get(route('sppd.pdf', $ticket))->assertNotFound();
    }

    private function person(string $role): array
    {
        return ['email' => $role.'@example.test', 'name' => ucfirst($role).' Contoh'];
    }

    private function createUser(string $role): User
    {
        return User::forceCreate($this->person($role) + [
            'password' => 'password',
            'phone' => '',
            'kartu_uang_1' => '',
            'username' => $role,
        ]);
    }

    private function input(): array
    {
        return [
            'submission_token' => (string) Str::uuid(),
            'directorate' => 'Direktorat Operasi',
            'category' => 'domestic_over_60',
            'origin' => 'Jakarta',
            'destination' => 'Bandung',
            'depart_on' => '2026-11-19',
            'return_on' => '2026-11-20',
            'purpose' => 'Koordinasi lapangan',
            'costs' => [
                30 => ['amount' => '450000.50'],
                31 => ['amount' => '50000'],
                32 => ['amount' => '15000.50'],
                33 => ['amount' => '10000'],
                34 => ['amount' => '20000'],
                35 => ['amount' => '10000'],
                36 => ['amount' => '5000'],
                38 => ['amount' => '40000'],
            ],
            'bank' => ['name' => 'Bank DKI', 'account' => '0012345678', 'holder' => 'Pemohon Contoh'],
        ];
    }
}
