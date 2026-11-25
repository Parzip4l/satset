<?php

namespace Tests\Feature;

use App\Models\It\Asset;
use App\Models\It\AssetEmployee;
use App\Models\It\AssetHistory;
use App\Models\User;
use App\Services\It\AssetRegister;
use App\Services\It\PortalEmployeeDirectory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItAssetRegisterTest extends TestCase
{
    private string $andiPortalId;

    private string $budiPortalId;

    private User $operator;

    private User $andi;

    private User $budi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 25)->setTime(9, 0));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
        Schema::create('it_user_capabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('capability');
            $table->timestamps();
        });
        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('support_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_team_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role');
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_25_000001_create_it_asset_register.php'))->up();
        (require database_path('migrations/2026_11_25_000002_add_specifications_to_it_assets.php'))->up();
        $this->operator = $this->user('Petugas IT', 'superadmin');
        $this->andi = $this->user('Andi');
        $this->budi = $this->user('Budi');
        $this->andiPortalId = (string) Str::uuid();
        $this->budiPortalId = (string) Str::uuid();
        $this->mock(PortalEmployeeDirectory::class, function ($mock) {
            $mock->shouldReceive('find')->andReturnUsing(fn ($id) => ['id' => $id, 'name' => $id === $this->andiPortalId ? 'Andi' : 'Budi', 'email' => $id === $this->andiPortalId ? 'andi@portal.test' : 'budi@portal.test', 'status' => 'active']);
            $mock->shouldReceive('search')->andReturn(['employees' => [], 'page' => 1, 'has_more' => false]);
        });

    }

    private function user(string $name, string $role = 'pelapor'): User
    {
        return User::forceCreate(['name' => $name, 'email' => uniqid().'@example.test', 'password' => bcrypt('password'), 'role' => $role]);
    }

    private function data(array $changes = []): array
    {
        return array_replace([
            'asset_tag' => 'IT-001', 'name' => 'Laptop ThinkPad', 'type_name' => 'Hardware', 'location_name' => 'Kantor Pusat Lantai 2',
            'brand' => 'Lenovo', 'model' => 'T14', 'serial_number' => 'SN-001',
            'status' => 'in_use', 'assigned_employee_portal_id' => $this->andiPortalId,
            'acquired_on' => null, 'purchase_price' => null, 'expires_on' => null, 'notes' => 'RAM 16 GB',
        ], $changes);
    }

    private function create(array $changes = []): Asset
    {
        $this->actingAs($this->operator)->post(route('it.assets.store'), $this->data($changes))->assertRedirect()->assertSessionHasNoErrors();

        return Asset::latest('id')->firstOrFail();
    }

    private function move(Asset $asset, array $changes = []): array
    {
        return array_replace(['lock_version' => $asset->lock_version, 'assigned_employee_portal_id' => $this->budiPortalId, 'location_name' => 'Depo Kelapa Gading', 'status' => 'in_use', 'reason' => 'Pengalihan perangkat untuk penempatan kerja baru.'], $changes);
    }

    public function test_create_records_optional_unknown_values_and_initial_history(): void
    {
        $asset = $this->create();
        $this->assertNull($asset->purchase_price);
        $this->assertNull($asset->acquired_on);
        $this->assertSame('Belum diketahui', $asset->age_label);
        $history = $asset->histories()->firstOrFail();
        $this->assertNull($history->before_state);
        $this->assertSame('Andi', $history->after_state['assignee_name']);
        $this->assertSame('Kantor Pusat Lantai 2', $history->after_state['location_name']);
        $this->assertSame(1, $history->sequence);
    }

    public function test_types_and_locations_are_created_and_reused_case_insensitively(): void
    {
        $first = $this->create(['type_name' => 'Network Appliance', 'location_name' => 'Ruang  Server']);
        $second = $this->create(['asset_tag' => 'IT-002', 'type_name' => 'network appliance', 'location_name' => ' ruang server ']);
        $this->assertSame($first->type_id, $second->type_id);
        $this->assertSame($first->location_id, $second->location_id);
        $this->assertDatabaseCount('it_asset_types', 4);
        $this->assertDatabaseCount('it_asset_locations', 1);
    }

    public function test_asset_age_price_zero_and_license_without_acquisition_date(): void
    {
        $asset = $this->create(['acquired_on' => '2024-08-25', 'purchase_price' => '0']);
        $this->assertSame('2 tahun 3 bulan', $asset->age_label);
        $this->assertSame('0.00', $asset->purchase_price);
        $this->get(route('it.assets.show', $asset))->assertOk()->assertSee('Rp0,00')->assertSee('2 tahun 3 bulan');
        $software = $this->create(['asset_tag' => 'SW-001', 'type_name' => 'Software', 'expires_on' => '2027-01-01']);
        $this->assertSame('2027-01-01', $software->expires_on->toDateString());
        $this->assertNull($software->purchase_price);
    }

    public function test_transfer_preserves_previous_and_next_employee_location_status_and_actor(): void
    {
        $asset = $this->create(['purchase_price' => '15200000.50']);
        $this->post(route('it.assets.move', $asset), $this->move($asset))->assertRedirect()->assertSessionHasNoErrors();
        $asset->refresh();
        $this->assertSame($this->budiPortalId, $asset->assignee->portal_id);
        $this->assertSame('Depo Kelapa Gading', $asset->location->name);
        $this->assertSame(2, $asset->lock_version);
        $history = $asset->histories()->where('sequence', 2)->firstOrFail();
        $this->assertSame('transferred', $history->action);
        $this->assertSame('Andi', $history->before_state['assignee_name']);
        $this->assertSame('Budi', $history->after_state['assignee_name']);
        $this->assertSame('Kantor Pusat Lantai 2', $history->before_state['location_name']);
        $this->assertSame('Depo Kelapa Gading', $history->after_state['location_name']);
        $this->assertSame($this->operator->id, $history->actor_id);
        $this->assertSame('Petugas IT', $history->actor_name);
        AssetEmployee::where('portal_id', $this->andiPortalId)->update(['name' => 'Andi Nama Baru']);
        $this->assertSame('Andi', $history->fresh()->before_state['assignee_name']);
        $this->get(route('it.assets.show', $asset))->assertOk()->assertSee('Perpindahan pemegang')->assertSee('Andi')->assertSee('Budi');
    }

    public function test_return_to_stock_and_status_only_changes_are_logged(): void
    {
        $asset = $this->create();
        $this->post(route('it.assets.move', $asset), $this->move($asset, ['assigned_employee_portal_id' => null, 'status' => 'available']))->assertRedirect()->assertSessionHasNoErrors();
        $asset->refresh();
        $this->assertNull($asset->assigned_employee_id);
        $this->assertSame('available', $asset->status);
        $this->post(route('it.assets.move', $asset), $this->move($asset, ['assigned_employee_portal_id' => null, 'status' => 'maintenance']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('status_changed', $asset->histories()->orderByDesc('sequence')->first()->action);
        $this->assertSame('maintenance', $asset->refresh()->status);
    }

    public function test_metadata_edits_record_history_and_cannot_silently_change_placement(): void
    {
        $asset = $this->create();
        $metadata = collect($this->data(['name' => 'Laptop Operasional', 'purchase_price' => '12000000.00', 'acquired_on' => '2025-01-10']))->except(['location_name', 'status', 'assigned_employee_portal_id'])->all();
        $this->put(route('it.assets.update', $asset), $metadata + ['lock_version' => 1, 'reason' => 'Melengkapi harga dan tanggal dari invoice.'])->assertRedirect()->assertSessionHasNoErrors();
        $asset->refresh();
        $history = $asset->histories()->where('sequence', 2)->firstOrFail();
        $this->assertSame('updated', $history->action);
        $this->assertNull($history->before_state['purchase_price']);
        $this->assertSame('12000000.00', $history->after_state['purchase_price']);
        $this->put(route('it.assets.update', $asset), $metadata + ['lock_version' => 2, 'reason' => 'Tidak boleh ubah pemegang lewat metadata.', 'assigned_employee_portal_id' => $this->budiPortalId])->assertSessionHasErrors('assigned_employee_portal_id');
        $this->assertSame($this->andiPortalId, $asset->refresh()->assignee->portal_id);
    }

    public function test_stale_edits_and_repeated_transfers_do_not_overwrite_history(): void
    {
        $asset = $this->create();
        $data = $this->move($asset);
        $this->post(route('it.assets.move', $asset), $data)->assertRedirect();
        $this->post(route('it.assets.move', $asset), $data)->assertStatus(409);
        $this->assertSame(2, $asset->histories()->count());
        $this->assertSame($this->budiPortalId, $asset->refresh()->assignee->portal_id);
        $this->post(route('it.assets.move', $asset), $this->move($asset))->assertSessionHasErrors('reason');
        $this->assertSame(2, $asset->histories()->count());
    }

    public function test_validation_blocks_duplicate_tags_negative_price_future_dates_and_inconsistent_status(): void
    {
        $this->create();
        $this->post(route('it.assets.store'), $this->data(['asset_tag' => ' it-001 ']))->assertSessionHasErrors('asset_tag');
        $this->post(route('it.assets.store'), $this->data(['asset_tag' => 'IT-002', 'purchase_price' => -1]))->assertSessionHasErrors('purchase_price');
        $this->post(route('it.assets.store'), $this->data(['asset_tag' => 'IT-002', 'acquired_on' => '2026-11-26']))->assertSessionHasErrors('acquired_on');
        $this->post(route('it.assets.store'), $this->data(['asset_tag' => 'IT-002', 'status' => 'available']))->assertSessionHasErrors('assigned_employee_portal_id');
        $this->post(route('it.assets.store'), $this->data(['asset_tag' => 'IT-002', 'assigned_employee_portal_id' => null]))->assertSessionHasErrors('assigned_employee_portal_id');
        $this->assertDatabaseCount('it_assets', 1);
        $this->assertDatabaseCount('it_asset_histories', 1);
    }

    public function test_access_uses_it_membership_or_explicit_capability_and_superadmin(): void
    {
        $asset = $this->create();
        foreach (['pelapor', 'ga', 'admin'] as $role) {
            $user = $this->user(uniqid('User'), $role);
            $this->actingAs($user)->get(route('it.assets.index'))->assertForbidden();
            $this->get(route('it.assets.show', $asset))->assertForbidden();
            $this->post(route('it.assets.move', $asset), $this->move($asset))->assertForbidden();
        }
        DB::table('it_user_capabilities')->insert(['user_id' => $user->id, 'capability' => 'manage_it_assets']);
        $this->actingAs($user)->get(route('it.assets.index'))->assertOk();
        $team = DB::table('support_teams')->insertGetId(['name' => 'IT Support', 'is_active' => true]);
        DB::table('support_team_members')->insert(['support_team_id' => $team, 'user_id' => $this->budi->id, 'role' => 'technician']);
        $this->actingAs($this->budi)->get(route('it.assets.index'))->assertOk();
        DB::table('support_teams')->where('id', $team)->update(['is_active' => false]);
        $this->get(route('it.assets.index'))->assertForbidden();
    }

    public function test_all_pages_filters_and_age_sort_render_with_correct_summary(): void
    {
        $asset = $this->create(['acquired_on' => '2024-01-01', 'purchase_price' => 2000]);
        $this->create(['asset_tag' => 'IT-002', 'type_name' => 'Software', 'location_name' => 'Depo', 'purchase_price' => 3000]);
        $this->get(route('it.assets.create'))->assertOk()->assertSee('asset-location-options', false)->assertSee('Hardware');
        $this->get(route('it.assets.edit', $asset))->assertOk()->assertSee('Alasan perubahan');
        $this->get(route('it.assets.index'))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 2 && $summary['known_price_total'] == 5000);
        $this->get(route('it.assets.index', ['type_id' => $asset->type_id]))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['known_price_total'] == 2000);
        $this->get(route('it.assets.index', ['search' => 'Andi']))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
        $this->get(route('it.assets.index', ['sort' => 'oldest_asset']))->assertOk()->assertViewHas('assets', fn ($assets) => $assets->first()->id === $asset->id);
    }

    public function test_failed_history_write_rolls_back_asset_and_new_master_records(): void
    {
        AssetHistory::creating(fn () => throw new \RuntimeException('Simulasi kegagalan riwayat'));
        try {
            app(AssetRegister::class)->create($this->operator, $this->data(['type_name' => 'Jenis baru', 'location_name' => 'Lokasi baru']));
            $this->fail('Penulisan riwayat seharusnya gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi kegagalan riwayat', $exception->getMessage());
        } finally {
            AssetHistory::flushEventListeners();
        }
        $this->assertDatabaseCount('it_assets', 0);
        $this->assertDatabaseCount('it_asset_histories', 0);
        $this->assertDatabaseCount('it_asset_types', 3);
        $this->assertDatabaseCount('it_asset_locations', 0);
    }

    public function test_dynamic_specifications_are_normalized_displayed_and_audited_when_edited_or_removed(): void
    {
        $asset = $this->create(['specifications' => [3 => ['name' => ' RAM ', 'value' => ' 16 GB '], 8 => ['name' => 'Storage', 'value' => '512 GB SSD'], 9 => ['name' => '', 'value' => '']]]);
        $initial = [['name' => 'RAM', 'value' => '16 GB'], ['name' => 'Storage', 'value' => '512 GB SSD']];
        $this->assertSame($initial, $asset->specifications);
        $this->assertSame($initial, $asset->histories()->first()->after_state['specifications']);
        $this->get(route('it.assets.show', $asset))->assertOk()->assertSee('512 GB SSD');
        $input = $this->data(['specifications' => [['name' => 'RAM', 'value' => '32 GB']], 'lock_version' => 1, 'reason' => 'Upgrade RAM perangkat.']);
        unset($input['location_name'], $input['status'], $input['assigned_employee_portal_id']);
        $this->put(route('it.assets.update', $asset), $input)->assertRedirect()->assertSessionHasNoErrors();
        $history = $asset->histories()->where('sequence', 2)->firstOrFail();
        $this->assertSame($initial, $history->before_state['specifications']);
        $this->assertSame([['name' => 'RAM', 'value' => '32 GB']], $history->after_state['specifications']);
        $this->put(route('it.assets.update', $asset), array_replace($input, ['specifications' => null, 'lock_version' => 2]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $asset->fresh()->specifications);
        $this->get(route('it.assets.show', $asset))->assertOk()->assertSee('RAM: 32 GB');
        $this->assertNull($asset->fresh()->purchase_price);
    }

    public function test_incomplete_duplicate_or_malformed_specifications_do_not_save_assets(): void
    {
        $this->actingAs($this->operator);
        foreach ([
            [['name' => 'RAM', 'value' => '']],
            [['name' => '', 'value' => '16 GB']],
            [['name' => 'RAM', 'value' => '16 GB'], ['name' => ' ram ', 'value' => '32 GB']],
            [['name' => 'RAM', 'value' => '16 GB', 'script' => 'injected']],
            ['invalid'],
        ] as $specifications) {
            $this->post(route('it.assets.store'), $this->data(['specifications' => $specifications]))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('it_assets', 0);
        $this->assertDatabaseCount('it_asset_histories', 0);
    }

    public function test_portal_failure_messages_distinguish_deployment_authentication_and_rate_limits(): void
    {
        $this->realDirectory();
        Http::fakeSequence()->push([], 404)->push([], 401)->push([], 403)->push([], 429)->push([], 500);
        foreach (['HTTP 404', 'shared secret', 'shared secret', 'Tunggu sebentar', 'HTTP 500'] as $message) {
            $response = $this->actingAs($this->operator)->getJson(route('it.assets.employees', ['search' => 'Andi']))->assertStatus(503);
            $this->assertStringContainsString($message, $response->json('message'));
            $this->assertStringNotContainsString('directory-secret', $response->getContent());
        }
    }

    private function realDirectory(): void
    {
        config(['satset.employee_directory.base_url' => 'https://portal.test', 'satset.employee_directory.endpoint' => '/api/v1/satset/employees/search', 'satset.employee_directory.shared_secret' => 'directory-secret']);
        $this->app->instance(PortalEmployeeDirectory::class, new PortalEmployeeDirectory);
        Http::preventStrayRequests();
    }

    private function portalPayload(array $employee, int $page = 1, bool $hasMore = false): array
    {
        return ['success' => true, 'data' => [$employee], 'meta' => ['pagination' => ['current_page' => $page, 'has_more' => $hasMore]]];
    }

    public function test_portal_directory_signs_requests_uses_portal_names_and_never_creates_login_accounts(): void
    {
        $this->realDirectory();
        $employee = ['id' => $this->andiPortalId, 'name' => 'Andi dari Portal', 'email' => 'andi@portal.test', 'employee_number' => 'EMP-001', 'department' => 'Operasional', 'status' => 'active', 'role' => 'superadmin'];
        Http::fake(['portal.test/*' => Http::response($this->portalPayload($employee))]);
        $response = $this->actingAs($this->operator)->getJson(route('it.assets.employees', ['search' => 'Andi']))->assertOk()->assertJsonPath('employees.0.name', 'Andi dari Portal');
        $this->assertArrayNotHasKey('role', $response->json('employees.0'));
        Http::assertSent(function ($request) {
            $timestamp = $request->header('X-Satset-Timestamp')[0];

            return $request['purpose'] === 'it_asset_assignment' && $request['search'] === 'Andi'
                && $request->header('X-Satset-Signature')[0] === hash_hmac('sha256', $timestamp.'.'.$request->body(), 'directory-secret');
        });
        $asset = $this->create();
        $this->assertSame('Andi dari Portal', $asset->assignee->name);
        $this->assertSame($this->andiPortalId, $asset->assignee->portal_id);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('it_asset_employees', 1);
        $this->assertSame('pelapor', $this->andi->fresh()->role);
        $this->assertSame('Andi dari Portal', $asset->histories()->first()->after_state['assignee_name']);
    }

    public function test_inactive_portal_employee_or_local_user_id_cannot_be_assigned(): void
    {
        $this->realDirectory();
        Http::fake(['portal.test/*' => Http::response($this->portalPayload(['id' => $this->andiPortalId, 'name' => 'Andi Nonaktif', 'email' => 'andi@portal.test', 'status' => 'inactive']))]);
        $this->actingAs($this->operator)->post(route('it.assets.store'), $this->data())->assertSessionHasErrors('assigned_employee_portal_id');
        $this->getJson(route('it.assets.employees', ['search' => 'Andi']))->assertOk()->assertJsonCount(0, 'employees');
        $this->post(route('it.assets.store'), $this->data(['assigned_employee_portal_id' => $this->andi->id]))->assertSessionHasErrors('assigned_employee_portal_id');
        $this->post(route('it.assets.store'), $this->data(['assigned_user_id' => $this->andi->id]))->assertSessionHasErrors('assigned_user_id');
        $this->assertDatabaseCount('it_assets', 0);
        $this->assertDatabaseCount('it_asset_employees', 0);
    }

    public function test_portal_outage_keeps_history_readable_without_falling_back_to_local_user_list(): void
    {
        $asset = $this->create();
        $this->realDirectory();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Portal timeout'));
        $this->actingAs($this->operator)->getJson(route('it.assets.employees', ['search' => 'Andi']))->assertStatus(503)->assertJsonMissing(['name' => 'Andi']);
        $this->get(route('it.assets.show', $asset))->assertOk()->assertSee('Andi');
        $this->post(route('it.assets.move', $asset), $this->move($asset))->assertSessionHasErrors('employee_directory');
        $this->assertSame($this->andiPortalId, $asset->refresh()->assignee->portal_id);
        $this->assertSame(1, $asset->histories()->count());
        $this->post(route('it.assets.move', $asset), $this->move($asset, ['assigned_employee_portal_id' => null, 'status' => 'available']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($asset->refresh()->assigned_employee_id);
    }

    public function test_portal_pagination_invalid_responses_and_employee_filters_are_handled(): void
    {
        $asset = $this->create();
        $other = $this->create(['asset_tag' => 'IT-BUDI', 'assigned_employee_portal_id' => $this->budiPortalId]);
        $this->get(route('it.assets.index', ['assigned_employee_portal_id' => $this->budiPortalId]))->assertOk()->assertViewHas('assets', fn ($assets) => $assets->count() === 1 && $assets->first()->id === $other->id);
        $this->realDirectory();
        Http::fakeSequence()->push($this->portalPayload(['id' => $this->budiPortalId, 'name' => 'Budi Portal', 'email' => 'budi@portal.test', 'status' => 'active'], 2, true))->push(['success' => true, 'data' => [['id' => 123, 'name' => 'Identitas salah']]]);
        $this->getJson(route('it.assets.employees', ['search' => 'Budi', 'page' => 2]))->assertOk()->assertJsonPath('page', 2)->assertJsonPath('has_more', true);
        $this->getJson(route('it.assets.employees', ['search' => 'invalid']))->assertStatus(503);
        $this->actingAs($this->andi)->getJson(route('it.assets.employees', ['search' => 'Budi']))->assertForbidden();
    }
}
