<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\It\ConfigurationVersion;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

if (! str_starts_with(DB::connection()->getDatabaseName(), 'satset_it_verify_')) {
    throw new RuntimeException('Mutation verification is restricted to the isolated restore database.');
}
$app->instance('env', 'testing'); // Only this isolated CLI process; permits test requests through the CSRF middleware.
Http::preventStrayRequests();
config(['satset.lrtj_space_notifications.enabled' => false, 'satset.ticket_email_notifications.enabled' => false]);
$checks = [];
$assert = function ($condition, $name) use (&$checks) {
    if (! $condition) {
        throw new RuntimeException('Failed: '.$name);
    }$checks[$name] = true;
};
$kernel = $app->make(Kernel::class);
$actor = User::findOrFail(1);
$send = function ($method, $uri, $data = [], $expected = 200) use ($kernel, $actor) {
    Auth::guard('web')->setUser($actor);
    $request = Request::create($uri, $method, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($data));
    $request->setUserResolver(fn () => $actor);
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== $expected) {
        throw new RuntimeException($method.' '.$uri.' expected '.$expected.' received '.$response->getStatusCode().': '.substr($response->getContent(), 0, 2000));
    }
    $kernel->terminate($request, $response);

    return json_decode($response->getContent(), true);
};
$originalCount = DB::table('requests')->count();
$first = ConfigurationVersion::where('state', 'draft')->firstOrFail();
$assert(DB::table('it_configuration_sets')->value('active_version_id') === null, 'seed_is_draft_only');
$send('GET', '/it/configuration');
$checks['configuration_index_http_200'] = true;
$send('GET', '/it/configuration/versions/'.$first->id);
$checks['editor_http_200'] = true;
$configuration = $first->configuration;
$configuration['categories'][] = ['key' => 'wifi', 'name' => 'Wi-Fi', 'parent_key' => 'network', 'is_active' => true];
$configuration['forms'][0]['fields'][] = ['key' => 'floor_room', 'label' => 'Lantai / ruang', 'type' => 'text', 'visible_roles' => ['requester', 'technician', 'approver'], 'required' => false, 'max_length' => 100];
$send('PUT', '/it/configuration/versions/'.$first->id, ['configuration' => $configuration, 'lock_version' => 0, 'reason' => 'MySQL integration category and field']);
$checks['new_category_and_field_saved'] = true;
$send('PUT', '/it/configuration/versions/'.$first->id, ['configuration' => $configuration, 'lock_version' => 0, 'reason' => 'Stale update must fail'], 409);
$checks['stale_save_rejected'] = true;
foreach (['requester', 'technician', 'approver'] as $role) {
    $result = $send('POST', '/it/configuration/versions/'.$first->id.'/preview', ['form_key' => 'incident_general', 'role' => $role, 'validate' => true, 'answers' => ['subject' => 'Wi-Fi problem', 'details' => 'Sample issue', 'connection' => 'wifi', 'ssid' => 'Office']]);
    $assert($result['valid'] === true && $result['persisted'] === false, 'preview_valid_'.$role);
    $keys = array_column($result['fields'], 'key');
    $assert(in_array('internal_note', $keys, true) === ($role !== 'requester'), 'role_projection_'.$role);
}
$send('POST', '/it/configuration/versions/'.$first->id.'/preview', ['form_key' => 'incident_general', 'role' => 'requester', 'validate' => true, 'answers' => ['subject' => 'Valid', 'details' => 'Details', 'connection' => 'lan', 'ssid' => 'forged']], 422);
$checks['hidden_input_rejected'] = true;
$send('POST', '/it/configuration/versions/'.$first->id.'/publish', ['set_revision' => 0, 'lock_version' => 1, 'reason' => 'MySQL verified publication']);
$checks['publish_first_version'] = true;
$oldHash = $first->fresh()->checksum;
$send('POST', '/it/configuration/drafts', ['source_version_id' => $first->id, 'reason' => 'Create second MySQL version'], 302);
$next = ConfigurationVersion::orderByDesc('id')->firstOrFail();
$nextConfig = $next->configuration;
$nextConfig['forms'][0]['fields'][0]['label'] = 'Updated label';
$send('PUT', '/it/configuration/versions/'.$next->id, ['configuration' => $nextConfig, 'lock_version' => 0, 'reason' => 'Update second MySQL version']);
$send('POST', '/it/configuration/versions/'.$next->id.'/publish', ['set_revision' => 1, 'lock_version' => 1, 'reason' => 'Publish second MySQL version']);
$checks['publish_second_version'] = true;
$send('POST', '/it/configuration/versions/'.$first->id.'/rollback', ['set_revision' => 2, 'lock_version' => $first->fresh()->lock_version, 'reason' => 'MySQL rollback verification']);
$assert((int) DB::table('it_configuration_sets')->value('active_version_id') === $first->id, 'rollback_switches_active_pointer');
$assert($first->fresh()->checksum === $oldHash, 'published_checksum_immutable');
$send('PUT', '/it/configuration/versions/'.$first->id, ['configuration' => $configuration, 'lock_version' => $first->fresh()->lock_version, 'reason' => 'Attempt mutation of publication'], 409);
$checks['published_edit_rejected'] = true;
$send('POST', '/it/configuration/drafts', ['source_version_id' => $first->id, 'reason' => 'Create invalid configuration check'], 302);
$invalid = ConfigurationVersion::orderByDesc('id')->firstOrFail();
$bad = $invalid->configuration;
$bad['categories'][0]['parent_key'] = $bad['categories'][0]['key'];
$send('PUT', '/it/configuration/versions/'.$invalid->id, ['configuration' => $bad, 'lock_version' => 0, 'reason' => 'Cycle must fail validation'], 422);
$checks['category_cycle_rejected'] = true;
$assert(DB::table('requests')->count() === $originalCount, 'ticket_count_unchanged');
$assert(DB::table('it_ticket_details')->whereNotNull('configuration_version_id')->count() === 0, 'legacy_ticket_versions_unchanged');
$assert(config('satset.it.requests_enabled') === false, 'submission_stays_closed');
$assert(DB::table('it_configuration_events')->where('action','rollback_activated')->count() === 1,'rollback_audited');
echo json_encode(['driver' => DB::getDriverName(), 'checks' => $checks, 'total_checks' => count($checks)],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
