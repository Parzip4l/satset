<?php

namespace Tests\Feature;

use App\Models\It\ConfigurationVersion;
use App\Models\It\TicketDetail;
use App\Models\User;
use App\Services\It\ConfigurationSchema;
use App\Services\It\ConfigurationStore;
use App\Services\It\FormEngine;
use Database\Seeders\ItConfigurationSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItConfigurationTest extends TestCase
{
    private User $manager;

    private ConfigurationVersion $draft;

    protected function setUp(): void
    {
        parent::setUp();
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
        });
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->boolean('is_active');
            $table->integer('order');
        });
        (require database_path('migrations/2026_10_28_000001_create_it_service_foundation.php'))->up();
        (require database_path('migrations/2026_10_28_000003_create_it_configuration_versions.php'))->up();
        $this->manager = $this->user();
        foreach (['manage_it_configuration', 'publish_it_configuration'] as $capability) {
            $this->grant($this->manager, $capability);
        }
        $this->seed(ItConfigurationSeeder::class);
        $this->draft = ConfigurationVersion::firstOrFail();
    }

    private function user(string $role = 'employee'): User
    {
        return User::create(['name' => 'Config test', 'email' => uniqid().'@example.test', 'password' => 'password', 'role' => $role]);
    }

    private function grant(User $user, string $capability): void
    {
        DB::table('it_user_capabilities')->insert(['user_id' => $user->id, 'capability' => $capability]);
    }

    private function update(array $configuration, int $lock = 0)
    {
        return $this->actingAs($this->manager)->putJson(route('it.configuration.update', $this->draft), ['configuration' => $configuration, 'lock_version' => $lock, 'reason' => 'Update configuration for testing']);
    }

    private function activate(ConfigurationVersion $version, int $revision = 0, bool $rollback = false)
    {
        return $this->actingAs($this->manager)->postJson(route($rollback ? 'it.configuration.rollback' : 'it.configuration.publish', $version), ['set_revision' => $revision, 'lock_version' => $version->fresh()->lock_version, 'reason' => 'Publish/rollback configuration testing']);
    }

    public function test_seed_is_draft_only_and_configuration_screens_are_accessible_to_capability_holder(): void
    {
        $this->assertNull(DB::table('it_configuration_sets')->value('active_version_id'));
        $this->assertSame('draft', $this->draft->state);
        $this->assertCount(7, $this->draft->configuration['catalog']);
        $this->actingAs($this->manager)->get(route('it.configuration.index'))->assertOk()->assertSee('Konfigurasi IT');
        $this->get(route('it.configuration.show', $this->draft))->assertOk()->assertSee('Formulir');
        $this->assertFalse(config('satset.it.requests_enabled'));
    }

    public function test_production_seed_requires_a_verified_actor_and_records_the_selected_user(): void
    {
        DB::table('it_configuration_events')->delete();
        DB::table('it_configuration_versions')->delete();
        DB::table('it_configuration_sets')->delete();
        $actor = $this->user();
        $previousEnvironment = app()->environment();
        app()->instance('env', 'production');
        config(['satset.it.bootstrap_actor_id' => null]);
        try {
            try {
                app(ItConfigurationSeeder::class)->run();
                $this->fail('Production must not infer the bootstrap actor from ID 1.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
            $this->assertSame(0, DB::table('it_configuration_sets')->count());
            config(['satset.it.bootstrap_actor_id' => $actor->id]);
            app(ItConfigurationSeeder::class)->run();
            $this->assertSame($actor->id, DB::table('it_configuration_events')->value('actor_id'));
            $this->assertSame($actor->id, DB::table('it_configuration_versions')->value('created_by'));
        } finally {
            app()->instance('env', $previousEnvironment);
            config(['satset.it.bootstrap_actor_id' => null]);
        }
    }

    public function test_admin_role_and_unauthorized_api_cannot_manage_configuration(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('it.configuration.index'))->assertForbidden();
        $this->putJson(route('it.configuration.update', $this->draft), [])->assertForbidden();
        $this->postJson(route('it.configuration.preview', $this->draft), ['form_key' => 'incident_general', 'role' => 'technician'])->assertForbidden();
    }

    public function test_manager_without_publish_permission_can_save_but_cannot_activate(): void
    {
        DB::table('it_user_capabilities')->where('user_id', $this->manager->id)->where('capability', 'publish_it_configuration')->delete();
        $this->update($this->draft->configuration)->assertOk()->assertJsonPath('lock_version', 1);
        $audit = json_decode(DB::table('it_configuration_events')->where('action', 'draft_saved')->value('metadata'), true);
        $this->assertSame($this->draft->configuration, $audit['before_configuration']);
        $this->assertArrayHasKey('after_configuration', $audit);
        $this->activate($this->draft)->assertForbidden();
    }

    public function test_publisher_without_manage_permission_can_preview_and_publish_but_not_edit(): void
    {
        DB::table('it_user_capabilities')->where('user_id', $this->manager->id)->where('capability', 'manage_it_configuration')->delete();
        $this->actingAs($this->manager)->get(route('it.configuration.show', $this->draft))->assertOk();
        $this->update($this->draft->configuration)->assertForbidden();
        $this->activate($this->draft)->assertOk()->assertJsonPath('submission_enabled', false);
    }

    public function test_schema_rejects_reserved_keys_unknown_properties_and_unbounded_file_types(): void
    {
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][0]['key'] = 'requester_id';
        $this->update($config)->assertUnprocessable();
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][0]['script'] = 'alert(1)';
        $this->update($config)->assertUnprocessable();
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][5]['allowed_mimes'] = ['php'];
        $this->update($config)->assertUnprocessable();
    }

    public function test_category_and_condition_cycles_are_rejected(): void
    {
        $config = $this->draft->configuration;
        $config['categories'][0]['parent_key'] = 'hardware';
        $this->update($config)->assertUnprocessable();
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][0]['visible_when'] = ['field' => 'ssid', 'operator' => 'is_empty'];
        $config['forms'][0]['fields'][3]['visible_when'] = ['field' => 'subject', 'operator' => 'is_empty'];
        $this->update($config)->assertUnprocessable();
    }

    public function test_references_mismatched_record_types_and_duplicate_options_are_rejected(): void
    {
        $config = $this->draft->configuration;
        $config['catalog'][0]['service_key'] = 'missing';
        $this->update($config)->assertUnprocessable();
        $config = $this->draft->configuration;
        $config['catalog'][0]['form_key'] = 'request_general';
        $this->update($config)->assertUnprocessable();
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][2]['options'][] = ['value' => 'wifi', 'label' => 'Duplicate'];
        $this->update($config)->assertUnprocessable();
    }

    public function test_condition_may_not_leak_internal_field_or_reference_deleted_option(): void
    {
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][0]['visible_when'] = ['field' => 'internal_note', 'operator' => 'is_empty'];
        $this->update($config)->assertUnprocessable();
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][3]['visible_when']['value'] = 'removed';
        $this->update($config)->assertUnprocessable();
    }

    public function test_web_preview_and_mobile_api_use_the_same_validation_and_role_projection(): void
    {
        $input = ['form_key' => 'incident_general', 'role' => 'requester', 'validate' => true, 'answers' => ['subject' => 'Wi-Fi', 'details' => 'No connection', 'connection' => 'wifi', 'ssid' => 'Office']];
        $this->actingAs($this->manager)->postJson(route('it.configuration.preview', $this->draft), $input)->assertOk()->assertJsonPath('valid', true)->assertJsonMissing(['key' => 'internal_note']);
        (require database_path('migrations/2026_08_11_000001_create_mobile_satset_tokens_table.php'))->up();
        \App\Models\MobileSatsetToken::create(['user_id' => $this->manager->id, 'token_hash' => hash('sha256', 'config-token'), 'expires_at' => now()->addHour()]);
        $this->withHeader('Authorization', 'Bearer config-token')->postJson('/api/mobile/v1/satset/it/configuration/versions/'.$this->draft->id.'/preview', $input)->assertOk()->assertJsonPath('valid', true);
        $input['answers']['status_id'] = 4;
        $this->postJson(route('it.configuration.preview', $this->draft), $input)->assertUnprocessable()->assertJsonPath('persisted', false);
        $this->postJson('/api/mobile/v1/satset/it/configuration/versions/'.$this->draft->id.'/preview', $input)->assertUnprocessable();
    }

    public function test_hidden_read_only_unknown_and_wrong_choice_inputs_are_rejected(): void
    {
        $input = ['form_key' => 'incident_general', 'role' => 'requester', 'validate' => true, 'answers' => ['subject' => 'Valid', 'details' => 'Valid details', 'connection' => 'lan', 'ssid' => 'injected', 'internal_note' => 'injected']];
        $this->actingAs($this->manager)->postJson(route('it.configuration.preview', $this->draft), $input)->assertUnprocessable()->assertJsonStructure(['errors' => ['answers.ssid', 'answers.internal_note']]);
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][0]['default'] = 'Server controlled';
        $config['forms'][0]['fields'][0]['read_only_roles'] = ['requester'];
        $this->update($config)->assertOk();
        $this->postJson(route('it.configuration.preview', $this->draft), $input)->assertUnprocessable()->assertJsonStructure(['errors' => ['answers.subject']]);
        unset($input['answers']['subject'],$input['answers']['ssid'],$input['answers']['internal_note']);
        $input['answers']['connection'] = 'bad_choice';
        $this->postJson(route('it.configuration.preview', $this->draft), $input)->assertUnprocessable()->assertJsonStructure(['errors' => ['answers.connection']]);
    }

    public function test_nested_conditions_and_conditional_required_use_resolved_values(): void
    {
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][3]['required_when'] = ['mode' => 'all', 'rules' => [['field' => 'connection', 'operator' => 'in', 'value' => ['wifi']], ['mode' => 'any', 'rules' => [['field' => 'subject', 'operator' => 'not_equals', 'value' => 'skip']]]]];
        $this->update($config)->assertOk();
        $this->actingAs($this->manager)->postJson(route('it.configuration.preview', $this->draft), ['form_key' => 'incident_general', 'role' => 'requester', 'validate' => true, 'answers' => ['subject' => 'Required', 'details' => 'Details', 'connection' => 'wifi']])->assertUnprocessable()->assertJsonStructure(['errors' => ['answers.ssid']]);
    }

    public function test_file_validation_inspects_mime_count_and_size_without_persisting_uploads(): void
    {
        $engine = app(FormEngine::class);
        $config = $this->draft->configuration;
        $valid = UploadedFile::fake()->createWithContent('evidence.pdf', '%PDF-1.4 sample');
        $result = $engine->inspect($config, 'incident_general', 'requester', ['subject' => 'Subject', 'details' => 'Details'], ['evidence' => [$valid]], true);
        $this->assertTrue($result['valid']);
        $this->assertFalse($result['persisted']);
        $bad = UploadedFile::fake()->create('bad.jpg', 10, 'application/x-httpd-php');
        $this->assertFalse($engine->inspect($config, 'incident_general', 'requester', ['subject' => 'Subject', 'details' => 'Details'], ['evidence' => [$bad]], true)['valid']);
        $tooBig = UploadedFile::fake()->create('large.pdf', 3000, 'application/pdf');
        $this->assertFalse($engine->inspect($config, 'incident_general', 'requester', ['subject' => 'Subject', 'details' => 'Details'], ['evidence' => [$tooBig]], true)['valid']);
        $this->assertFalse($engine->inspect($config, 'incident_general', 'requester', ['subject' => 'Subject', 'details' => 'Details'], ['evidence' => [$valid, $valid, $valid, $valid]], true)['valid']);
    }

    public function test_optimistic_locks_prevent_stale_saves_and_publication(): void
    {
        $this->update($this->draft->configuration)->assertOk();
        $this->update($this->draft->configuration)->assertConflict();
        $this->actingAs($this->manager)->postJson(route('it.configuration.publish', $this->draft), ['set_revision' => 0, 'lock_version' => 0, 'reason' => 'Stale publication attempt'])->assertConflict();
        $this->assertNull(DB::table('it_configuration_sets')->value('active_version_id'));
    }

    public function test_publication_is_immutable_and_rollback_preserves_tickets_and_snapshots(): void
    {
        $this->activate($this->draft)->assertOk();
        $old = $this->draft->fresh();
        $oldChecksum = $old->checksum;
        $id = DB::table('requests')->insertGetId([]);
        DB::table('it_ticket_details')->insert(['request_id' => $id, 'record_type' => 'incident', 'configuration_version_id' => $old->id, 'form_key' => 'incident_general', 'form_answers' => json_encode(['subject' => 'Old answer'])]);
        $next = app(ConfigurationStore::class)->draft($this->manager, 'Create second version', $old->id);
        $config = $next->configuration;
        $config['forms'][0]['fields'][0]['label'] = 'New label';
        app(ConfigurationStore::class)->save($next->id, $config, 0, $this->manager, 'Update second version');
        $this->activate($next, 1)->assertOk();
        $this->activate($old, 2, true)->assertOk();
        $this->assertSame($old->id, DB::table('it_configuration_sets')->value('active_version_id'));
        $this->assertSame($oldChecksum, $old->fresh()->checksum);
        $detail = TicketDetail::where('request_id', $id)->firstOrFail();
        $this->assertSame('Old answer', $detail->form_answers['subject']);
        $this->assertSame('Ringkasan kebutuhan', $detail->configurationVersion->configuration['forms'][0]['fields'][0]['label']);
        $this->update($config, 1)->assertConflict();
        $this->assertDatabaseHas('it_configuration_events', ['action' => 'rollback_activated']);
    }

    public function test_reusing_a_published_field_key_with_another_type_is_rejected(): void
    {
        $this->activate($this->draft)->assertOk();
        $next = app(ConfigurationStore::class)->draft($this->manager, 'Second version draft');
        $config = $next->configuration;
        $config['forms'][0]['fields'][0]['type'] = 'textarea';
        $this->actingAs($this->manager)->putJson(route('it.configuration.update', $next), ['configuration' => $config, 'lock_version' => 0, 'reason' => 'Change published field type'])->assertUnprocessable();
    }

    public function test_process_profiles_and_submission_gate_cannot_be_overridden(): void
    {
        $config = $this->draft->configuration;
        $config['profiles']['submission_enabled'] = true;
        $this->update($config)->assertUnprocessable();
        $this->assertFalse(config('satset.it.requests_enabled'));
    }

    public function test_required_read_only_field_requires_a_valid_server_default(): void
    {
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][0]['read_only_roles'] = ['requester'];
        $this->update($config)->assertUnprocessable();
        $config['forms'][0]['fields'][0]['default'] = 'Controlled summary';
        $this->update($config)->assertOk();
    }

    public function test_numeric_overflow_and_fractional_integer_are_not_silently_coerced(): void
    {
        $config = $this->draft->configuration;
        $config['forms'][0]['fields'][] = ['key' => 'affected_users', 'label' => 'Jumlah pengguna', 'type' => 'integer', 'visible_roles' => ['requester'], 'required' => true];
        app(ConfigurationSchema::class)->validate($config);
        foreach (['1e100', '1.5'] as $value) {
            $result = app(FormEngine::class)->inspect($config, 'incident_general', 'requester', ['subject' => 'Issue', 'details' => 'Details', 'affected_users' => $value], [], true);
            $this->assertFalse($result['valid']);
            $this->assertArrayHasKey('answers.affected_users', $result['errors']);
        }
    }

    public function test_stale_active_revision_cannot_replace_another_publishers_version(): void
    {
        $other = app(ConfigurationStore::class)->draft($this->manager, 'Prepare independent draft', $this->draft->id);
        $this->activate($this->draft)->assertOk();
        $this->activate($other, 0)->assertConflict();
        $this->assertSame($this->draft->id, DB::table('it_configuration_sets')->value('active_version_id'));
    }
}
