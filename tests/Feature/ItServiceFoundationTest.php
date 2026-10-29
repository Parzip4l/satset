<?php

namespace Tests\Feature;

use App\Models\It\SupportTeam;
use App\Models\It\TicketDetail;
use App\Models\Master\ServiceDomain;
use App\Models\Master\Ticket;
use App\Models\MobileSatsetToken;
use App\Models\User;
use App\Services\LrtjSpaceMobileNotificationService;
use App\Services\MobileSatsetTicketService;
use App\Support\ItAccess;
use App\Support\ServiceDomains;
use App\Support\TicketServiceDomain;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItServiceFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['satset.lrtj_space_notifications.enabled' => false]);
        Http::preventStrayRequests();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('assigned_user_id')->nullable();
            $table->foreignId('assigned_department_id')->nullable();
            $table->foreignId('status_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id');
            $table->foreignId('approver_id');
            $table->string('status')->default('Pending');
        });
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->boolean('is_active');
            $table->integer('order');
        });
        (require database_path('migrations/2026_10_28_000001_create_it_service_foundation.php'))->up();
    }

    private function user(string $role = 'employee'): User
    {
        return User::create(['name' => 'Test User', 'email' => uniqid().'@example.test', 'password' => 'password', 'role' => $role]);
    }

    private function ticket(User $user, ?string $type = 'it', bool $restricted = false): Ticket
    {
        $id = DB::table('requests')->insertGetId([
            'ticket_no' => uniqid('TCK-'), 'title' => 'Test ticket', 'description' => 'Description',
            'requester_id' => $user->id, 'payload' => json_encode($type ? ['request_type' => $type] : []),
            'created_at' => now(), 'updated_at' => now(),
            'service_domain_id' => $type === 'it' ? ServiceDomain::where('code', 'it')->value('id') : null,
        ]);
        if ($type === 'it') {
            TicketDetail::create(['request_id' => $id, 'record_type' => 'incident', 'is_restricted' => $restricted]);
        }

        return Ticket::findOrFail($id);
    }

    private function grant(User $user, string $capability): void
    {
        DB::table('it_user_capabilities')->insert(['user_id' => $user->id, 'capability' => $capability, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_migration_separates_four_domains_and_preserves_existing_ticket_identity(): void
    {
        $this->assertSame(['atk_rtk', 'consumption', 'ga_request_finding', 'it'], ServiceDomain::orderBy('sort_order')->pluck('code')->all());
        $this->assertFalse(ServiceDomain::where('code', 'it')->first()->is_active);
        $this->assertFalse(collect(ServiceDomains::catalog())->firstWhere('code', 'it')['accepting_requests']);
        $this->assertFalse(config('satset.it.requests_enabled'));
        $user = $this->user();
        $legacy = $this->ticket($user, 'general');
        $this->assertNull(TicketServiceDomain::code($legacy));
        $this->assertFalse(ItAccess::isIt($legacy));
    }

    public function test_known_ga_domains_are_assigned_on_create_without_reclassifying_general(): void
    {
        $user = $this->user();
        $this->mock(LrtjSpaceMobileNotificationService::class)->shouldReceive('notifyTicketCreated')->once();
        $ticket = Ticket::create(['requester_id' => $user->id, 'ticket_no' => 'AUTO-GA-001', 'title' => 'GA request', 'payload' => ['request_type' => 'atk_rtk']]);
        $this->assertSame(ServiceDomain::where('code', 'atk_rtk')->value('id'), $ticket->fresh()->service_domain_id);
        $this->assertSame('atk_rtk', TicketServiceDomain::code($this->ticket($user, 'atk_rtk')));
    }

    public function test_admin_ga_and_superadmin_do_not_implicitly_read_it_or_manage_configuration(): void
    {
        $ticket = $this->ticket($this->user());
        foreach (['admin', 'ga', 'superadmin'] as $role) {
            $user = $this->user($role);
            $this->assertFalse(ItAccess::canRead($user, $ticket));
            $this->assertFalse(Gate::forUser($user)->allows('manage_it_configuration'));
            $this->actingAs($user)->get(route('it.show', $ticket))->assertForbidden();
            $this->actingAs($user)->get(route('ticket.show', $ticket))->assertForbidden();
        }
    }

    public function test_configuration_and_publishing_capabilities_do_not_grant_ticket_read_access(): void
    {
        $user = $this->user('superadmin');
        $this->grant($user, 'manage_it_configuration');
        $this->assertTrue(Gate::forUser($user)->allows('manage_it_configuration'));
        $this->assertFalse(Gate::forUser($user)->allows('publish_it_configuration'));
        $this->assertFalse(ItAccess::canRead($user, $this->ticket($this->user())));
    }

    public function test_active_team_members_read_their_queue_and_restrictions_require_explicit_permission(): void
    {
        $user = $this->user();
        $owner = $this->user();
        $ticket = $this->ticket($owner);
        $restricted = $this->ticket($owner, 'it', true);
        $team = SupportTeam::create(['code' => 'network', 'name' => 'Network']);
        $team->members()->attach($user);
        TicketDetail::whereIn('request_id', [$ticket->id, $restricted->id])->update(['support_team_id' => $team->id]);
        $this->assertTrue(ItAccess::canRead($user, $ticket));
        $this->assertFalse(ItAccess::canRead($user, $restricted));
        $this->assertTrue(ItAccess::canRead($owner, $restricted));
        $this->grant($user, 'view_restricted_it');
        $this->assertTrue(ItAccess::canRead($user, $restricted));
        $team->update(['is_active' => false]);
        $this->assertFalse(ItAccess::canRead($user, $ticket));
    }

    public function test_list_scopes_preserve_unmapped_legacy_tickets_but_exclude_unrelated_it(): void
    {
        $owner = $this->user();
        $ga = $this->user('ga');
        $legacy = $this->ticket($owner, null);
        $atk = $this->ticket($owner, 'atk_rtk');
        $it = $this->ticket($owner);
        $this->assertEqualsCanonicalizing([$legacy->id, $atk->id], ItAccess::constrainVisible(Ticket::query(), $ga)->pluck('id')->all());
        $this->assertSame([$it->id], TicketServiceDomain::filter(ItAccess::constrainVisible(Ticket::query(), $owner), 'it')->pluck('id')->all());
        $this->assertSame([$atk->id], TicketServiceDomain::filter(Ticket::query(), 'atk_rtk')->pluck('id')->all());
        $this->assertSame(0, TicketServiceDomain::filter(Ticket::query(), 'unknown')->count());
    }

    public function test_legacy_email_api_cannot_read_update_or_list_it_even_with_owner_email(): void
    {
        $owner = $this->user();
        $ticket = $this->ticket($owner);
        $this->getJson('/api/v1/tickets/'.$ticket->id.'?email='.urlencode($owner->email))->assertNotFound();
        $this->getJson('/api/v1/tickets/'.$ticket->id.'/history?email='.urlencode($owner->email))->assertNotFound();
        $this->putJson('/api/v1/tickets/'.$ticket->id, ['email' => $owner->email, 'title' => 'Changed'])->assertNotFound();
        $this->getJson('/api/v1/tickets?email='.urlencode($owner->email))->assertOk()->assertJsonCount(0, 'data');
        // Check the exclusion query without depending on unrelated resource/master schemas.
        $query = Ticket::where('requester_id', $owner->id)->whereNot(fn ($it) => ItAccess::constrainIt($it));
        $this->assertSame(0, $query->count());
    }

    public function test_requester_reads_it_landing_and_legacy_show_redirects_but_writes_stay_closed(): void
    {
        $owner = $this->user();
        $ticket = $this->ticket($owner);
        $this->actingAs($owner)->get(route('it.index'))->assertOk()->assertSee('Pengajuan IT belum dibuka')->assertSee($ticket->ticket_no);
        $this->get(route('it.show', $ticket))->assertOk()->assertSee($ticket->title);
        $this->get(route('ticket.show', $ticket))->assertRedirect(route('it.show', $ticket));
        $this->postJson(route('ticket.comment', $ticket), ['message' => 'test'])->assertConflict();
        $this->postJson(route('ticket.assign', $ticket), ['assigned_user_id' => $owner->id])->assertConflict();
        $this->putJson(route('ticket.updateStatus', $ticket), ['status_id' => 1])->assertConflict();
        $this->deleteJson('/ticket/'.$ticket->getRouteKey())->assertConflict();
    }

    public function test_it_markers_and_domain_identifiers_cannot_be_forged_through_legacy_create(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/ticket', ['request_type' => 'it'])->assertUnprocessable();
        $this->postJson('/ticket', ['request_type' => 'general', 'payload' => ['request_type' => 'it']])->assertUnprocessable();
        $this->postJson('/api/v1/tickets', ['service_domain_id' => ServiceDomain::where('code', 'it')->value('id')])->assertUnprocessable();
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_ga_access_service_and_it_serialization_do_not_bypass_it_isolation(): void
    {
        $owner = $this->user();
        $ticket = $this->ticket($owner);
        $ticket->forceFill(['payload' => ['request_type' => 'it', 'internal_secret' => 'never expose']]);
        $service = app(MobileSatsetTicketService::class);
        $this->assertFalse($service->canAccess($this->user('ga'), $ticket));
        $serialized = $service->serializeTicket($ticket);
        $this->assertTrue($serialized['read_only']);
        $this->assertArrayNotHasKey('payload', $serialized);
        $this->assertArrayNotHasKey('attachments', $serialized);
    }

    public function test_it_notifications_never_broadcast_to_ga_or_admin_roles(): void
    {
        $owner = $this->user();
        $this->user('ga');
        $this->user('admin');
        $ticket = $this->ticket($owner);
        $ticket->setRelation('requester', $owner)->setRelation('status', null);
        config(['satset.lrtj_space_notifications.enabled' => true, 'satset.lrtj_space_notifications.shared_secret' => 'test-secret', 'satset.lrtj_space_notifications.base_url' => 'https://portal.example.test']);
        Http::fake(['*' => Http::response(['success' => true])]);
        app(LrtjSpaceMobileNotificationService::class)->notifyTicketCreated($ticket);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['recipient_email'] === $owner->email);
    }

    public function test_audit_and_backfill_are_safe_for_unknown_records_and_idempotent(): void
    {
        $owner = $this->user();
        $atk = $this->ticket($owner, 'atk_rtk');
        $general = $this->ticket($owner, 'general');
        $unknown = $this->ticket($owner, null);
        $before = DB::table('requests')->orderBy('id')->get()->toJson();
        $this->artisan('satset:it-audit', ['--json' => true])->assertSuccessful();
        $this->artisan('satset:backfill-service-domains')->assertSuccessful();
        $this->assertSame($before, DB::table('requests')->orderBy('id')->get()->toJson());
        $this->artisan('satset:backfill-service-domains', ['--apply' => true])->assertSuccessful();
        $this->assertSame('atk_rtk', TicketServiceDomain::code($atk->fresh()));
        $this->assertNull($general->fresh()->service_domain_id);
        $this->assertNull($unknown->fresh()->service_domain_id);
        $this->assertSame($atk->updated_at->toDateTimeString(), $atk->fresh()->updated_at->toDateTimeString());
        $after = DB::table('requests')->orderBy('id')->get()->toJson();
        $this->artisan('satset:backfill-service-domains', ['--apply' => true])->assertSuccessful();
        $this->assertSame($after, DB::table('requests')->orderBy('id')->get()->toJson());
    }

    public function test_capability_bootstrap_requires_reason_and_supports_revocation(): void
    {
        $user = $this->user('superadmin');
        $this->artisan('satset:it-capability', ['user_id' => $user->id, 'capability' => 'manage_it_configuration'])->assertFailed();
        $args = ['user_id' => $user->id, 'capability' => 'manage_it_configuration', '--reason' => 'Test bootstrap'];
        $this->artisan('satset:it-capability', $args)->assertSuccessful();
        $this->assertTrue(ItAccess::can($user, 'manage_it_configuration'));
        $this->artisan('satset:it-capability', $args + ['--revoke' => true])->assertSuccessful();
        $this->assertFalse(ItAccess::can($user, 'manage_it_configuration'));
    }

    public function test_guest_cannot_access_it(): void
    {
        $this->get('/it')->assertRedirect(route('login'));
    }

    public function test_mobile_it_access_checks_run_after_bearer_authentication(): void
    {
        (require database_path('migrations/2026_08_11_000001_create_mobile_satset_tokens_table.php'))->up();
        $owner = $this->user();
        $ga = $this->user('ga');
        $ticket = $this->ticket($owner);
        foreach ([$owner, $ga] as $user) {
            $plain = 'test-token-'.$user->id;
            MobileSatsetToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addHour()]);
        }
        $url = '/api/mobile/v1/satset/tickets/'.$ticket->getRouteKey();
        $this->withHeader('Authorization', 'Bearer test-token-'.$owner->id)->getJson($url)->assertOk()->assertJsonPath('data.read_only', true);
        $this->getJson('/api/mobile/v1/satset/tickets?service=it')->assertOk()->assertJsonPath('data.total', 1);
        $this->withHeader('Authorization', 'Bearer test-token-'.$ga->id)->getJson($url)->assertForbidden();
        $this->getJson('/api/mobile/v1/satset/tickets?service=it')->assertOk()->assertJsonPath('data.total', 0);
        $this->withHeader('Authorization', 'Bearer test-token-'.$owner->id)->postJson($url.'/comments', ['message' => 'test'])->assertConflict();
    }

    public function test_legacy_zip_management_cannot_export_or_clear_it_through_admin_role(): void
    {
        $this->ticket($this->user());
        $this->actingAs($this->user('admin'))->postJson('/settings/ticket-data/backup')->assertConflict();
        $this->deleteJson('/settings/ticket-data/clear')->assertConflict();
        $this->assertDatabaseCount('requests', 1);
        $this->expectException(\RuntimeException::class);
        app(\App\Services\TicketDataBackupService::class)->clearAll();
    }

    public function test_it_detail_takes_precedence_over_a_conflicting_ga_marker(): void
    {
        $owner = $this->user();
        $ticket = $this->ticket($owner, 'atk_rtk');
        TicketDetail::create(['request_id' => $ticket->id, 'record_type' => 'incident']);
        $this->assertTrue(ItAccess::isIt($ticket));
        $this->assertFalse(ItAccess::canRead($this->user('ga'), $ticket));
        $this->artisan('satset:backfill-service-domains', ['--apply' => true])->assertSuccessful();
        $this->assertNull($ticket->fresh()->service_domain_id);
        $this->assertSame(0, TicketServiceDomain::filter(Ticket::query(), 'atk_rtk')->count());
    }

    public function test_additive_migration_can_be_rolled_back_without_deleting_legacy_requests(): void
    {
        $legacy = $this->ticket($this->user(), 'general');
        (require database_path('migrations/2026_10_28_000001_create_it_service_foundation.php'))->down();
        $this->assertDatabaseHas('requests', ['id' => $legacy->id, 'ticket_no' => $legacy->ticket_no]);
        $this->assertFalse(Schema::hasColumn('requests', 'service_domain_id'));
        $this->assertFalse(Schema::hasTable('it_ticket_details'));
        $this->assertCount(4, ServiceDomains::catalog());
    }

    public function test_request_center_has_four_service_cards_and_keeps_history_and_approval_separate(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        $this->actingAs($this->user())->get(route('ticket.index'))->assertOk()
            ->assertSee('ATK/RTK')->assertSee('Konsumsi')->assertSee('Permintaan &amp; Temuan', false)
            ->assertSee('Layanan IT')->assertSee('Persetujuan Saya')->assertSee('Lihat tiket saya');
    }
}
