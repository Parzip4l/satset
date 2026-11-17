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
                        'organization' => [
                            'division' => ['name' => 'Operasi'],
                            'directorate' => ['name' => 'Direktorat Operasi dan Pengembangan'],
                        ],
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

    public function test_form_loads_and_fetches_the_current_requesters_portal_profile_separately(): void
    {
        $owner = $this->createUser('owner');
        $this->actingAs($owner)->get(route('sppd.create'))->assertOk()->assertSee(route('sppd.profile'));
        Http::assertNothingSent();

        $this->getJson(route('sppd.profile', ['email' => 'other@example.test']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'name' => 'Pemohon Contoh',
                'nik' => '001234',
                'position' => 'Staf Operasional',
                'division' => 'Operasi',
                'directorate' => 'Direktorat Operasi dan Pengembangan',
            ]);
        Http::assertSent(fn (Request $request) => $request['requester_email'] === $owner->email
            && $request['metadata']['purpose'] === 'organization_sync');
    }

    public function test_existing_sppd_can_be_revised_approved_and_downloaded_when_new_requests_are_closed(): void
    {
        $owner = $this->createUser('owner');
        $data = $this->input();
        $this->actingAs($owner)->get(route('sppd.create'))->assertOk()
            ->assertSee('Kategori Perjalanan Dinas')
            ->assertSeeInOrder(['No.', 'Penginapan', 'Uang Makan', 'Lain-lain', 'Tambah Baris'])
            ->assertDontSee('Supported by GA')->assertDontSee('Bantuan Perlengkapan Musim Dingin');
        $this->postJson(route('sppd.store'), array_replace($data, ['other_costs' => [['amount' => '-5']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['other_costs.0.label', 'other_costs.0.amount']);
        $data = $this->withPreview($data);
        $this->post(route('sppd.store'), $data)->assertRedirect();
        $ticket = Ticket::sole();
        $this->post(route('sppd.store'), $data)->assertRedirect(route('sppd.show', $ticket));
        $this->get(route('sppd.pdf', $ticket))->assertNotFound();
        $this->get(route('sppd.index'))->assertOk()->assertSee('Bandung');
        $this->assertSame(60000100, data_get($ticket->payload, 'sppd.total_cents'));
        $this->assertSame(4500000, data_get($ticket->payload, 'sppd.employee_cents'));
        $this->assertNull(data_get($ticket->payload, 'sppd.costs.30.note'));
        $this->assertSame('Biaya parkir', data_get($ticket->payload, 'sppd.other_costs.0.label'));
        $this->assertSame(2, data_get($ticket->payload, 'sppd.days'));
        $this->assertSame('001234', data_get($ticket->payload, 'sppd.profile.nik'));

        $admin = $this->createUser('admin');
        $admin->update(['role' => 'admin']);
        $this->actingAs($admin)->put(route('settings.sppd-service.update'), ['is_active' => '0'])->assertRedirect();
        $this->actingAs($owner)->get(route('sppd.index'))->assertOk()
            ->assertSee('Pengajuan SPPD baru sedang ditutup.')->assertSee('Bandung');

        $first = $ticket->approvals()->sole();
        $this->post(route('sppd.decide', [$ticket, $first]), ['decision' => 'approved'])->assertForbidden();
        $manager = $first->approver;
        $this->actingAs($manager)->get(route('ticket.approvals'))->assertOk()->assertSee('Tinjau SPPD');
        $this->get(route('sppd.show', $ticket))->assertOk()->assertSee('Konfirmasi Mengetahui');
        $this->postJson(route('sppd.decide', [$ticket, $first]), ['decision' => 'revision_requested'])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->post(route('sppd.decide', [$ticket, $first]), ['decision' => 'revision_requested', 'note' => 'Lengkapi agenda.'])
            ->assertRedirect(route('sppd.show', $ticket));

        $this->actingAs($owner)->get(route('sppd.edit', $ticket))->assertOk()
            ->assertSee('Ajukan Ulang')->assertSee('Biaya parkir')->assertSee('Bagasi tambahan');
        $revision = array_replace($data, [
            'purpose' => 'Koordinasi lapangan dan pembahasan agenda',
            'version' => 1,
            'other_costs' => [1 => ['label' => 'Bagasi tambahan', 'amount' => '15000.25', 'note' => 'Peralatan kerja']],
        ]);
        $this->put(route('sppd.update', $ticket), $this->withPreview($revision, $ticket))->assertRedirect(route('sppd.show', $ticket));
        $this->assertSame(2, data_get($ticket->refresh()->payload, 'sppd.version'));
        $this->assertSame(57500125, data_get($ticket->payload, 'sppd.total_cents'));
        $this->assertSame(4500000, data_get($ticket->payload, 'sppd.employee_cents'));
        $this->assertSame([['label' => 'Bagasi tambahan', 'cents' => 1500025, 'note' => 'Peralatan kerja']], data_get($ticket->payload, 'sppd.other_costs'));
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
        $this->actingAs($owner)->get(route('sppd.show', $ticket))->assertOk()->assertSee('Unduh PDF SPPD')
            ->assertSeeInOrder(['Lain-lain', '6.1', 'Bagasi tambahan', '15.000,25', 'Peralatan kerja', 'Rp575.001,25'])
            ->assertDontSee('Biaya parkir');
        $response = $this->get(route('sppd.pdf', $ticket))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        foreach (Sppd::STAGES as [, , $role]) {
            $this->assertStringContainsString('https://portal.example.test/verify/sppd-'.$role, $response->getContent());
        }
        $unrelated = $this->createUser('unrelated');
        $this->actingAs($unrelated)->get(route('sppd.pdf', $ticket))->assertForbidden();
        $this->actingAs($owner)->patch(route('ticket.update', $ticket->id), ['title' => 'Ubah lewat tiket'])->assertNotFound();
    }

    public function test_old_other_cost_can_be_removed_on_revision_without_reappearing_after_validation(): void
    {
        $owner = $this->createUser('owner');
        $data = $this->input();
        unset($data['other_costs']);
        $this->actingAs($owner);
        $this->post(route('sppd.store'), $this->withPreview($data))->assertRedirect();
        $ticket = Ticket::sole();
        $payload = $ticket->payload;
        unset($payload['sppd']['other_costs']);
        $payload['sppd']['costs'][38] = ['cents' => 4000000, 'note' => 'Catatan lama'];
        $payload['sppd']['total_cents'] = 60000100;
        $ticket->update(['payload' => $payload]);
        $this->get(route('sppd.show', $ticket))->assertOk()
            ->assertSeeInOrder(['Lain-lain', '6.1', 'Bantuan Perlengkapan Musim Dingin', '40.000', 'Catatan lama']);

        $approval = $ticket->approvals()->sole();
        $this->actingAs($approval->approver)->post(route('sppd.decide', [$ticket, $approval]), [
            'decision' => 'revision_requested',
            'note' => 'Periksa biaya tambahan.',
        ])->assertRedirect();

        $edit = route('sppd.edit', $ticket);
        $this->actingAs($owner)->get($edit)->assertOk()
            ->assertSee('Bantuan Perlengkapan Musim Dingin')->assertSee('Catatan lama');
        $this->from($edit)->put(route('sppd.update', $ticket), array_replace($data, [
            '_token' => 'test-token', 'purpose' => '', 'version' => 1,
        ]))->assertRedirect($edit)->assertSessionHasErrors('purpose');
        $this->get($edit)->assertOk()->assertDontSee('Bantuan Perlengkapan Musim Dingin');

        $this->put(route('sppd.update', $ticket), $this->withPreview($data + ['version' => 1], $ticket))->assertRedirect();
        $this->assertSame([], data_get($ticket->refresh()->payload, 'sppd.other_costs'));
        $this->assertSame(56000100, data_get($ticket->payload, 'sppd.total_cents'));
    }

    public function test_only_admin_can_toggle_new_sppd_submissions(): void
    {
        $admin = $this->createUser('admin');
        $admin->update(['role' => 'admin']);
        $employee = $this->createUser('employee');
        $settings = route('settings.sppd-service.index');
        $update = route('settings.sppd-service.update');

        $this->actingAs($employee)->get($settings)->assertForbidden();
        $this->put($update, ['is_active' => '0'])->assertForbidden();
        $this->assertDatabaseHas('service_domains', ['code' => 'sppd', 'is_active' => true]);

        $this->actingAs($admin)->get(route('sppd.index'))->assertSee($settings);
        $this->get($settings)->assertOk()->assertSee('Nonaktifkan Pengajuan Baru');
        $this->put($update, ['is_active' => '0'])->assertRedirect($settings);
        $this->get($settings)->assertOk()->assertSee('Aktifkan Pengajuan Baru');
        $this->assertDatabaseHas('service_domains', ['code' => 'sppd', 'is_active' => false]);

        $this->actingAs($employee)->get(route('sppd.index'))->assertOk()
            ->assertSee('Pengajuan SPPD baru sedang ditutup.')->assertDontSee('Ajukan SPPD');
        $this->get(route('sppd.create'))->assertRedirect(route('sppd.index'));
        $this->postJson(route('sppd.preview'), $this->input())->assertStatus(423);
        $this->post(route('sppd.store'), $this->input())->assertRedirect(route('sppd.index'));
        $this->postJson(route('sppd.store'), $this->input())->assertStatus(423);
        $this->assertDatabaseCount('requests', 0);
        Http::assertNothingSent();

        $this->actingAs($admin)->put($update, ['is_active' => '1'])->assertRedirect($settings);
        $this->actingAs($employee)->get(route('sppd.create'))->assertOk()->assertSee('Pratinjau PDF');
        $this->post(route('sppd.store'), $this->withPreview($this->input()))->assertRedirect();
        $this->assertDatabaseCount('requests', 1);
    }

    public function test_a_portal_failure_leaves_submission_or_approval_available_for_retry(): void
    {
        $owner = $this->createUser('owner');
        $data = $this->input();
        $this->signatureUnavailable = true;
        $this->actingAs($owner);
        $data = $this->withPreview($data);
        $this->postJson(route('sppd.store'), $data)
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

    public function test_submission_requires_a_preview_of_the_same_data_by_the_requester(): void
    {
        $owner = $this->createUser('owner');
        $data = $this->input();
        $this->actingAs($owner)->postJson(route('sppd.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('preview_token');

        $reviewed = $this->withPreview($data);
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('approvals', 0);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => data_get($request->data(), 'metadata.purpose') === 'organization_sync');

        $changed = $reviewed;
        $changed['other_costs'][0]['amount'] = '25000';
        $this->postJson(route('sppd.store'), $changed)->assertUnprocessable()->assertJsonValidationErrors('preview_token');
        $this->actingAs($this->createUser('unrelated'))->postJson(route('sppd.store'), $reviewed)
            ->assertUnprocessable()->assertJsonValidationErrors('preview_token');
        $this->assertDatabaseCount('requests', 0);

        $this->actingAs($owner)->post(route('sppd.store'), $reviewed)->assertRedirect();
        $this->assertDatabaseCount('requests', 1);
    }

    private function withPreview(array $data, ?Ticket $ticket = null): array
    {
        $response = $this->postJson($ticket ? route('sppd.preview-revision', $ticket) : route('sppd.preview'), $data)
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Sppd-Preview-Token');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringNotContainsString('https://portal.example.test/verify/', $response->getContent());

        return ['preview_token' => $response->headers->get('X-Sppd-Preview-Token')] + $data;
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
            ],
            'other_costs' => [
                ['label' => 'Biaya parkir', 'amount' => '24000'],
                ['label' => 'Bagasi tambahan', 'amount' => '16000'],
            ],
            'bank' => ['name' => 'Bank DKI', 'account' => '0012345678', 'holder' => 'Pemohon Contoh'],
        ];
    }
}
