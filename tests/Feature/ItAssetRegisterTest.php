<?php

namespace Tests\Feature;

use App\Models\It\Asset;
use App\Models\It\AssetEmployee;
use App\Models\It\AssetHistory;
use App\Models\User;
use App\Services\It\AssetRegister;
use App\Services\It\AssetWorkbook;
use App\Services\It\PortalEmployeeDirectory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ItAssetRegisterTest extends TestCase
{
    private string $andiPortalId;

    private string $budiPortalId;

    private User $operator;

    private User $andi;

    private User $budi;

    private array $workbookFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->workbookFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

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

    public function test_it_manager_manages_assets_and_inherits_requester_navigation_in_both_layouts(): void
    {
        $this->operator->update(['role' => \App\Support\ItAccess::ROLE]);
        $asset = $this->create();
        $this->get(route('it.assets.index'))->assertOk()->assertSee('Pengajuan Satset')->assertSee('Antrean Tiket IT')->assertDontSee('Operasional GA');
        $this->get(route('it.assets.template'))->assertOk()->assertDownload('template-aset-it.xlsx');
        $this->get(route('it.assets.import.form'))->assertOk();
        $this->post(route('it.assets.move', $asset), $this->move($asset))->assertRedirect()->assertSessionHasNoErrors();
        Schema::table('menus', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->string('url')->nullable();
            $table->string('type')->nullable();
            $table->string('icon')->nullable();
            $table->json('role_id')->nullable();
        });
        $parent = DB::table('menus')->insertGetId(['title' => 'Menu Pemohon', 'url' => '#', 'type' => 'menu', 'role_id' => json_encode(['pelapor'])]);
        DB::table('menus')->insert(['parent_id' => $parent, 'title' => 'Pengajuan Saya', 'url' => 'ticket.index', 'type' => 'menu', 'role_id' => json_encode(['pelapor'])]);
        $this->get(route('it.assets.index'))->assertOk()->assertSee('Menu Pemohon')->assertSee('Pengajuan Saya')->assertDontSee('Pengajuan Satset');
    }

    public function test_admin_role_forms_offer_it_manager_and_ordinary_users_do_not_receive_the_option(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        $this->actingAs($this->operator)->get(route('user.create'))->assertOk()->assertSee('value="it_manager"', false)->assertSee('Pengelola IT');
        $this->operator->update(['role' => \App\Support\ItAccess::ROLE]);
        $this->get(route('user.index'))->assertOk()->assertSee('Pengelola IT')->assertDontSee('name="role"', false);
        $this->actingAs($this->andi)->get(route('user.create'))->assertOk()->assertDontSee('value="it_manager"', false);
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

    private function workbook(array $rows, ?array $headers = null): UploadedFile
    {
        $headers ??= array_merge(array_keys(AssetWorkbook::COLUMNS), ['Spesifikasi: RAM']);
        $path = tempnam(sys_get_temp_dir(), 'asset-import-test-');
        $this->workbookFiles[] = $path;
        copy(resource_path('templates/it-assets.xlsx'), $path);
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([1 => $headers] + $rows as $number => $values) {
            $xml .= '<row r="'.$number.'">';
            foreach (array_values($values) as $index => $value) {
                $column = '';
                for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
                    $column = chr(65 + ($n - 1) % 26).$column;
                }
                $xml .= '<c r="'.$column.$number.'" t="inlineStr"><is><t>'.htmlspecialchars((string) ($value ?? ''), ENT_XML1, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        $zip->close();

        return new UploadedFile($path, 'aset.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function importRow(array $changes = []): array
    {
        return array_values(array_replace([
            'asset_tag' => 'IMPORT-001', 'name' => 'Laptop impor', 'type_name' => 'Hardware', 'location_name' => 'Gudang IT',
            'status' => 'Tersedia', 'employee_email' => '', 'brand' => 'Lenovo', 'model' => 'T14', 'serial_number' => 'SN-IMPORT',
            'acquired_on' => '', 'purchase_price' => '', 'expires_on' => '', 'notes' => '', 'ram' => '16 GB',
        ], $changes));
    }

    public function test_asset_template_and_import_page_require_it_permission_and_template_has_no_example_assets(): void
    {
        $this->actingAs($this->operator)->get(route('it.assets.template'))->assertOk()->assertDownload('template-aset-it.xlsx');
        $this->get(route('it.assets.import.form'))->assertOk()->assertSee('Email pemegang')->assertSee('Spesifikasi: Nama');
        try {
            app(AssetWorkbook::class)->read(resource_path('templates/it-assets.xlsx'));
            $this->fail('Template kosong harus meminta pengguna mengisi data.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('Belum ada data', $exception->errors()['file'][0]);
        }
        $this->actingAs($this->andi)->get(route('it.assets.template'))->assertForbidden();
        $this->get(route('it.assets.import.form'))->assertForbidden();
        $this->post(route('it.assets.import'))->assertForbidden();
    }

    public function test_xlsx_import_creates_assets_specs_and_histories_and_checks_repeated_portal_email_once(): void
    {
        app(PortalEmployeeDirectory::class)->shouldReceive('findByEmail')->once()->with('andi@portal.test')->andReturn([
            'id' => $this->andiPortalId, 'name' => 'Andi Portal', 'email' => 'andi@portal.test', 'status' => 'active',
        ]);
        $file = $this->workbook([
            2 => $this->importRow(['acquired_on' => '45292', 'purchase_price' => '0']),
            3 => $this->importRow(['asset_tag' => 'IMPORT-002', 'status' => 'Digunakan', 'employee_email' => 'ANDI@portal.test', 'type_name' => 'Perangkat jaringan', 'location_name' => 'Ruang Server']),
            5 => $this->importRow(['asset_tag' => 'IMPORT-003', 'status' => 'in_use', 'employee_email' => 'andi@portal.test']),
        ]);
        $this->actingAs($this->operator)->post(route('it.assets.import'), ['file' => $file])->assertRedirect(route('it.assets.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $first = Asset::where('asset_tag', 'IMPORT-001')->firstOrFail();
        $this->assertSame('2024-01-01', $first->acquired_on->toDateString());
        $this->assertSame('0.00', $first->purchase_price);
        $this->assertNull($first->assigned_employee_id);
        $this->assertSame([['name' => 'RAM', 'value' => '16 GB']], $first->specifications);
        $second = Asset::where('asset_tag', 'IMPORT-002')->firstOrFail();
        $this->assertSame('Andi Portal', $second->assignee->name);
        $this->assertSame('Perangkat jaringan', $second->type->name);
        $this->assertNull($second->purchase_price);
        $this->assertDatabaseCount('it_assets', 3);
        $this->assertDatabaseCount('it_asset_histories', 3);
        $this->assertDatabaseCount('users', 3);
        $this->assertSame('Pencatatan awal aset melalui impor XLSX.', $first->histories()->first()->reason);
    }

    public function test_xlsx_invalid_rows_report_excel_row_numbers_and_save_nothing(): void
    {
        $file = $this->workbook([2 => $this->importRow(), 6 => $this->importRow(['asset_tag' => 'IMPORT-002', 'purchase_price' => '-100'])]);
        $this->actingAs($this->operator)->from(route('it.assets.import.form'))->post(route('it.assets.import'), ['file' => $file])->assertRedirect(route('it.assets.import.form'))->assertSessionHasErrors('rows.6.purchase_price');
        $this->get(route('it.assets.import.form'))->assertOk()->assertSee('Baris 6: Harga perolehan tidak boleh kurang dari 0.')->assertSee('Impor belum disimpan');
        $this->assertDatabaseCount('it_assets', 0);
        $this->assertDatabaseCount('it_asset_histories', 0);
        $this->assertDatabaseCount('it_asset_locations', 0);
    }

    public function test_xlsx_duplicate_codes_in_file_and_existing_assets_are_rejected_without_overwriting_history(): void
    {
        $asset = $this->create();
        $this->actingAs($this->operator)->post(route('it.assets.import'), ['file' => $this->workbook([2 => $this->importRow(), 3 => $this->importRow(['asset_tag' => ' import-001 '])])])->assertSessionHasErrors('rows.3.asset_tag');
        $this->post(route('it.assets.import'), ['file' => $this->workbook([2 => $this->importRow(), 3 => $this->importRow(['asset_tag' => $asset->asset_tag])])])->assertSessionHasErrors('rows.3.asset_tag');
        $this->assertDatabaseCount('it_assets', 1);
        $this->assertSame(1, $asset->fresh()->lock_version);
        $this->assertSame(1, $asset->histories()->count());
    }

    public function test_xlsx_portal_outage_prevents_partial_import_and_spoofed_employee_names_are_rejected(): void
    {
        app(PortalEmployeeDirectory::class)->shouldReceive('findByEmail')->once()->andThrow(\Illuminate\Validation\ValidationException::withMessages(['employee_directory' => 'Portal sedang tidak dapat dihubungi.']));
        $this->actingAs($this->operator)->post(route('it.assets.import'), ['file' => $this->workbook([2 => $this->importRow(), 3 => $this->importRow(['asset_tag' => 'IMPORT-002', 'status' => 'Digunakan', 'employee_email' => 'andi@portal.test'])])])->assertSessionHasErrors('rows.3.employee_directory');
        $this->post(route('it.assets.import'), ['file' => $this->workbook([2 => $this->importRow(['status' => 'Digunakan', 'employee_email' => 'Andi'])])])->assertSessionHasErrors('rows.2.employee_email');
        $this->assertDatabaseCount('it_assets', 0);
        $this->assertDatabaseCount('it_asset_employees', 0);
    }

    public function test_xlsx_rejects_invalid_formats_headers_formula_cells_and_more_than_100_assets(): void
    {
        $this->actingAs($this->operator);
        $this->post(route('it.assets.import'), ['file' => UploadedFile::fake()->create('aset.csv', 1, 'text/csv')])->assertSessionHasErrors('file');
        $this->post(route('it.assets.import'), ['file' => UploadedFile::fake()->create('aset.xlsx', 1, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])->assertSessionHasErrors('file');
        $this->post(route('it.assets.import'), ['file' => $this->workbook([2 => ['test']], ['Judul salah'])])->assertSessionHasErrors('file');
        $file = $this->workbook([2 => $this->importRow()]);
        $zip = new ZipArchive;
        $zip->open($file->getRealPath());
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('<c r="A2" t="inlineStr">', '<c r="A2" t="inlineStr"><f>1+1</f>', $zip->getFromName('xl/worksheets/sheet1.xml')));
        $zip->close();
        $this->post(route('it.assets.import'), ['file' => $file])->assertSessionHasErrors('file');
        $rows = [];
        for ($i = 2; $i <= 102; $i++) {
            $rows[$i] = $this->importRow(['asset_tag' => 'IMPORT-'.$i]);
        }
        $this->post(route('it.assets.import'), ['file' => $this->workbook($rows)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('it_assets', 0);
    }

    public function test_xlsx_history_failure_rolls_back_every_asset_master_and_employee_in_batch(): void
    {
        $this->withoutExceptionHandling();
        app(PortalEmployeeDirectory::class)->shouldReceive('findByEmail')->once()->andReturn([
            'id' => $this->andiPortalId, 'name' => 'Andi Portal', 'email' => 'andi@portal.test', 'status' => 'active',
        ]);
        $count = 0;
        AssetHistory::creating(function () use (&$count) {
            if (++$count === 2) {
                throw new \RuntimeException('Batch history failure');
            }
        });
        try {
            $this->actingAs($this->operator)->post(route('it.assets.import'), ['file' => $this->workbook([2 => $this->importRow(['type_name' => 'Jenis baru', 'status' => 'Digunakan', 'employee_email' => 'andi@portal.test']), 3 => $this->importRow(['asset_tag' => 'IMPORT-002'])])]);
            $this->fail('Impor harus dibatalkan jika riwayat gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Batch history failure', $exception->getMessage());
        } finally {
            AssetHistory::flushEventListeners();
        }
        $this->assertDatabaseCount('it_assets', 0);
        $this->assertDatabaseCount('it_asset_histories', 0);
        $this->assertDatabaseCount('it_asset_locations', 0);
        $this->assertDatabaseCount('it_asset_types', 3);
        $this->assertDatabaseCount('it_asset_employees', 0);
    }

    public function test_portal_import_email_matching_is_exact_active_and_unique_across_pages(): void
    {
        $this->realDirectory();
        Http::fakeSequence()
            ->push($this->portalPayload(['id' => $this->budiPortalId, 'name' => 'Budi', 'email' => 'andi@portal.test.other', 'status' => 'active'], 1, true))
            ->push($this->portalPayload(['id' => $this->andiPortalId, 'name' => 'Andi', 'email' => 'andi@portal.test', 'status' => 'active'], 2))
            ->push($this->portalPayload(['id' => $this->andiPortalId, 'name' => 'Andi', 'email' => 'andi@portal.test', 'status' => 'inactive']))
            ->push(['success' => true, 'data' => [
                ['id' => $this->andiPortalId, 'name' => 'Andi', 'email' => 'andi@portal.test', 'status' => 'active'],
                ['id' => $this->budiPortalId, 'name' => 'Nama lain', 'email' => 'andi@portal.test', 'status' => 'active'],
            ], 'meta' => ['pagination' => ['current_page' => 1, 'has_more' => false]]]);
        $this->assertSame($this->andiPortalId, app(PortalEmployeeDirectory::class)->findByEmail('ANDI@portal.test')['id']);
        try {
            app(PortalEmployeeDirectory::class)->findByEmail('andi@portal.test');
            $this->fail('Karyawan nonaktif tidak boleh dipilih.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('employee_email', $exception->errors());
        }
        try {
            app(PortalEmployeeDirectory::class)->findByEmail('andi@portal.test');
            $this->fail('Email karyawan yang ambigu tidak boleh dipilih.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('employee_email', $exception->errors());
        }
    }

    public function test_xlsx_handles_reordered_columns_dynamic_specs_and_1904_excel_dates(): void
    {
        $headers = ['Status', 'Lokasi', 'Nama aset', 'Kode aset', 'Tipe aset', 'Spesifikasi: Prosesor', 'Spesifikasi: RAM', 'Tanggal perolehan', 'Harga perolehan'];
        $file = $this->workbook([2 => ['Tersedia', 'Depo', 'Laptop Excel', 'DYNAMIC-001', 'Hardware', 'Intel i7', '32 GB', '43830', '1.50000005E7']], $headers);
        $zip = new ZipArchive;
        $zip->open($file->getRealPath());
        $zip->addFromString('xl/workbook.xml', str_replace('<x:sheets>', '<x:workbookPr date1904="1"/><x:sheets>', $zip->getFromName('xl/workbook.xml')));
        // Use a numeric Excel cell rather than an inline text date.
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('<c r="H2" t="inlineStr"><is><t>43830</t></is></c>', '<c r="H2"><v>43830</v></c>', $zip->getFromName('xl/worksheets/sheet1.xml')));
        $zip->close();
        $this->actingAs($this->operator)->post(route('it.assets.import'), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();
        $asset = Asset::firstOrFail();
        $this->assertSame('2024-01-01', $asset->acquired_on->toDateString());
        $this->assertSame('15000000.50', $asset->purchase_price);
        $this->assertSame([['name' => 'Prosesor', 'value' => 'Intel i7'], ['name' => 'RAM', 'value' => '32 GB']], $asset->specifications);
    }

    public function test_xlsx_rejects_external_entities_and_duplicate_specification_names(): void
    {
        $file = $this->workbook([2 => $this->importRow()]);
        $zip = new ZipArchive;
        $zip->open($file->getRealPath());
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('<?xml version="1.0"?>', '<?xml version="1.0"?><!DOCTYPE worksheet [<!ENTITY data SYSTEM "file:///etc/passwd">]>', $zip->getFromName('xl/worksheets/sheet1.xml')));
        $zip->close();
        $this->actingAs($this->operator)->post(route('it.assets.import'), ['file' => $file])->assertSessionHasErrors('file');
        $headers = array_merge(array_keys(AssetWorkbook::COLUMNS), ['Spesifikasi: RAM', 'Spesifikasi:ram']);
        $this->post(route('it.assets.import'), ['file' => $this->workbook([2 => array_merge($this->importRow(), ['32 GB'])], $headers)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('it_assets', 0);
    }

    public function test_xlsx_template_can_be_filled_without_replacing_exported_shared_strings_sheet(): void
    {
        $file = $this->workbook([]);
        $original = new ZipArchive;
        $original->open(resource_path('templates/it-assets.xlsx'));
        $xml = $original->getFromName('xl/worksheets/sheet1.xml');
        $original->close();
        $row = '<x:row r="2"><x:c r="A2" t="inlineStr"><x:is><x:t>FILLED-001</x:t></x:is></x:c><x:c r="B2" t="inlineStr"><x:is><x:t>Laptop dari template</x:t></x:is></x:c><x:c r="C2" t="inlineStr"><x:is><x:t>Hardware</x:t></x:is></x:c><x:c r="D2" t="inlineStr"><x:is><x:t>Depo</x:t></x:is></x:c><x:c r="E2" t="inlineStr"><x:is><x:t>Tersedia</x:t></x:is></x:c></x:row>';
        $xml = preg_replace('~<x:row r="2"[^>]*>.*?</x:row>~s', $row, $xml);
        $zip = new ZipArchive;
        $zip->open($file->getRealPath());
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $this->actingAs($this->operator)->post(route('it.assets.import'), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('it_assets', ['asset_tag' => 'FILLED-001', 'name' => 'Laptop dari template']);
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
