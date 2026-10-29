<?php

// Prepare a proposed local draft. Never publish, grant access or enable submission.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\It\ConfigurationVersion;
use App\Models\User;
use App\Services\It\ConfigurationSchema;
use App\Services\It\ConfigurationStore;
use App\Services\It\OperationalProfile;
use App\Services\It\SlaProfile;
use Illuminate\Support\Facades\DB;

if (! in_array(app()->environment(), ['development', 'local', 'testing'], true)) {
    throw new RuntimeException('Draft usulan hanya boleh dibuat pada lingkungan development/local.');
}
if (! in_array('--apply', $argv, true)) {
    throw new RuntimeException('Gunakan --apply untuk membuat draft lokal.');
}
$reason = 'Usulan pilot jaringan dan akun; target dan tim perlu UAT pengguna';
$existing = DB::table('it_configuration_events')->where('reason', $reason)->where('action', 'draft_saved')->first();
if ($existing) {
    echo json_encode(['created' => false, 'draft_id' => $existing->version_id, 'reason' => 'already_exists'])."\n";
    exit;
}
$source = ConfigurationVersion::where('state', 'draft')->orderByDesc('id')->firstOrFail();
$config = $source->configuration;
$config['schema_version'] = 3;
$config['profiles'] = OperationalProfile::defaults();
$sla = SlaProfile::draft();
foreach (['incident', 'service_request'] as $type) {
    $kind = $type === 'incident' ? 'restore' : 'fulfil';
    $targets = [];
    foreach (['P1' => [15, 60], 'P2' => [60, 240], 'P3' => [240, 1440], 'P4' => [480, 2880]] as $priority => $minutes) {
        $targets[$priority] = ['response' => $minutes[0], $kind => $minutes[1]];
    }
    $sla['policies'][] = ['key' => $type.'_pilot', 'name' => 'Usulan pilot '.$type, 'record_type' => $type, 'calendar_key' => 'office', 'targets' => $targets,
        'pause' => ['response' => [], 'restore' => ['waiting_customer'], 'fulfil' => ['waiting_customer', 'awaiting_approval', 'needs_revision']],
        'warning_percent' => 80, 'escalations' => [['key' => 'warning', 'at_percent' => 80, 'repeat_minutes' => null, 'recipients' => ['coordinators']], ['key' => 'breach', 'at_percent' => 100, 'repeat_minutes' => null, 'recipients' => ['coordinators']]]];
    $sla['fallback'][$type] = $type.'_pilot';
}
$config['profiles']['sla'] = $sla;
$config['profiles']['approval'] = [['key' => 'account_manager', 'catalog_key' => 'access_request', 'record_type' => 'service_request', 'resolver' => 'portal', 'approver_id' => null]];
app(ConfigurationSchema::class)->validate($config);
$actor = User::findOrFail(31);
$store = app(ConfigurationStore::class);
$draft = $store->draft($actor, 'Buat draft usulan pilot dari versi '.$source->number, $source->id);
$draft = $store->save($draft->id, $config, 0, $actor, $reason);
echo json_encode(['created' => true, 'draft_id' => $draft->id, 'draft_number' => $draft->number, 'source_version_id' => $source->id, 'state' => $draft->state, 'checksum' => $draft->checksum, 'active_version_id' => DB::table('it_configuration_sets')->value('active_version_id'), 'submission_enabled' => config('satset.it.requests_enabled')], JSON_THROW_ON_ERROR)."\n";
