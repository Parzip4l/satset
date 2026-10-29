<?php

namespace Tests\Feature;

use App\Services\It\ConfigurationStore;
use App\Services\It\OperationHealth;
use App\Services\It\PilotGate;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItPilotGateTest extends TestCase
{
    use \Tests\Support\BuildsItWorkflow { setUp as workflowSetUp; }

    protected function setUp(): void
    {
        $this->workflowSetUp();
        (require database_path('migrations/2026_10_28_000005_create_it_sla_operations.php'))->up();
        require_once base_path('scripts/it_phase04_fixture.php');
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Persiapan pilot terisolasi');
        $config = $v->configuration;
        $config['schema_version'] = 3;
        $config['profiles']['sla'] = phase04TestSla();
        $v = $store->save($v->id, $config, 0, $this->manager, 'Simpan profil pilot terisolasi');
        $this->version = $store->activate($v->id, 1, $v->lock_version, $this->manager, 'Publikasi profil uji pilot');
        config(['satset.it.pilot.required' => true, 'satset.it.requests_enabled' => true,
            'satset.it.pilot.version_id' => $this->version->id, 'satset.it.pilot.user_ids' => [$this->requester->id],
            'satset.it.pilot.catalog_keys' => ['network_issue', 'access_request'],
            'satset.it.pilot.business_approved' => true, 'satset.it.pilot.uat_approved' => true,
            'satset.it.pilot.restore_verified' => true, 'satset.it.pilot.support_ready' => true]);
        $this->mock(OperationHealth::class)->shouldReceive('report')->andReturn(['ready' => true]);
    }

    public function test_admin_can_disable_it_and_block_new_requests_without_removing_access_to_tickets(): void
    {
        $this->actingAs($this->manager)->put(route('settings.it-service.update'), ['is_active' => 0])->assertRedirect();
        $this->assertDatabaseHas('service_domains', ['code' => 'it', 'is_active' => false]);
        $this->actingAs($this->requester)->get(route('it.request'))->assertOk()->assertSee('Layanan IT segera hadir');
        $this->getJson(route('it.create'))->assertStatus(423);
        $this->postJson(route('it.store'), $this->input())->assertStatus(423);
        $this->get(route('it.index'))->assertOk();
        $this->actingAs($this->manager)->put(route('settings.it-service.update'), ['is_active' => 1])->assertRedirect();
        $this->actingAs($this->requester)->get(route('it.request'))->assertRedirect(route('it.create'));
    }

    public function test_non_admin_cannot_change_it_service_status(): void
    {
        $this->requester->update(['role' => 'employee']);
        $this->actingAs($this->requester)->put(route('settings.it-service.update'), ['is_active' => 0])->assertForbidden();
        $this->assertDatabaseHas('service_domains', ['code' => 'it', 'is_active' => true]);
    }

    public function test_only_scoped_participant_can_see_and_submit_scoped_catalog(): void
    {
        $this->actingAs($this->requester)->get(route('it.request'))->assertRedirect(route('it.create'));
        $this->actingAs($this->requester)->getJson(route('it.create'))->assertOk()->assertJsonCount(2, 'catalog');
        $this->postJson(route('it.form'), ['catalog_key' => 'application_issue'])->assertNotFound();
        $this->postJson(route('it.store'), $this->input('incident', ['catalog_key' => 'application_issue']))->assertStatus(423);
        $this->postJson(route('it.store'), $this->input('incident', ['catalog_key' => 'network_issue']))->assertCreated();
        $this->actingAs($this->other)->getJson(route('it.create'))->assertStatus(423);
        $this->get(route('it.request'))->assertOk()->assertSee('Pengajuan layanan IT belum tersedia')->assertSee('Lihat tiket IT');
        $this->postJson(route('it.store'), $this->input('incident', ['catalog_key' => 'network_issue']))->assertStatus(423);
    }

    public function test_each_approval_gate_and_worker_health_fails_closed(): void
    {
        foreach (['business_approved', 'uat_approved', 'restore_verified', 'support_ready'] as $name) {
            config(['satset.it.pilot.'.$name => false]);
            $this->assertFalse(app(PilotGate::class)->allowed($this->requester));
            config(['satset.it.pilot.'.$name => true]);
        }
        $this->instance(OperationHealth::class, new class extends OperationHealth
        {
            public function report(int $maxAge = 180): array
            {
                return ['ready' => false];
            }
        });
        $this->actingAs($this->requester)->postJson(route('it.store'), $this->input())->assertStatus(423);
    }

    public function test_active_version_change_or_old_schema_fails_closed(): void
    {
        config(['satset.it.pilot.version_id' => $this->version->id + 1]);
        $this->assertFalse(app(PilotGate::class)->allowed($this->requester));
        config(['satset.it.pilot.version_id' => $this->version->id]);
        $old = \App\Models\It\ConfigurationVersion::where('state', 'published')->where('id', '!=', $this->version->id)->firstOrFail();
        app(ConfigurationStore::class)->activate($old->id, 2, $old->lock_version, $this->manager, 'Rollback untuk uji pilot', true);
        $this->assertFalse(app(PilotGate::class)->allowed($this->requester));
    }

    public function test_invalid_scope_and_missing_incident_or_request_do_not_pass(): void
    {
        config(['satset.it.pilot.catalog_keys' => ['network_issue']]);
        $this->assertFalse(app(PilotGate::class)->readiness()['ready']);
        config(['satset.it.pilot.catalog_keys' => ['network_issue', 'access_request'], 'satset.it.pilot.user_ids' => [999999]]);
        $this->assertFalse(app(PilotGate::class)->readiness()['ready']);
    }

    public function test_feature_flag_and_configuration_permissions_remain_separate(): void
    {
        config(['satset.it.requests_enabled' => false]);
        $this->actingAs($this->requester)->postJson(route('it.store'), $this->input())->assertStatus(423);
        $this->getJson(route('it.configuration.pilot'))->assertForbidden();
        $this->actingAs($this->manager)->getJson(route('it.configuration.pilot'))->assertOk()->assertJsonPath('feature_flag', false);
        $this->assertSame(0,DB::table('requests')->count());
    }
}
