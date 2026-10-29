<?php

// Mutations are strictly confined to an isolated restore database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/it_phase04_fixture.php';
use App\Models\It\ConfigurationVersion;
use App\Models\User;
use App\Services\It\ConfigurationStore;
use App\Services\It\OperationalProfile;
use App\Services\It\OperationHealth;
use App\Services\It\PilotGate;
use App\Services\It\TicketWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

if (! str_starts_with(DB::connection()->getDatabaseName(), 'satset_it_verify_')) {
    throw new RuntimeException('Database verifikasi terpisah wajib.');
}
$app->instance('env', 'testing');
Http::preventStrayRequests();
$app->instance(OperationHealth::class, new class extends OperationHealth
{
    public function report(int $maxAge = 180): array
    {
        return ['ready' => true];
    }
});
$set = DB::table('it_configuration_sets')->where('code', 'it')->first();
$maxVersion = ConfigurationVersion::max('id');
$users = [];
$team = null;
$tickets = [];
$checks = [];
$assert = function ($condition, $name) use (&$checks) {
    if (! $condition) {
        throw new RuntimeException('UAT gagal: '.$name);
    }$checks[$name] = true;
};
try {
    $manager = User::findOrFail(1);
    foreach (['Pemohon', 'Teknisi', 'Approver', 'Bukan peserta'] as $label) {
        $u = new User;
        $u->forceFill(['name' => 'Pilot test '.$label, 'username' => 'pilot_'.Str::random(12), 'email' => Str::uuid().'@example.test', 'password' => bcrypt(Str::random(32)), 'phone' => '00000000', 'role' => 'pelapor'])->save();
        $users[] = $u;
    }
    [$requester,$tech,$approver,$outsider] = $users;
    $team = DB::table('support_teams')->insertGetId(['code' => 'pilot_'.Str::lower(Str::random(10)), 'name' => 'Tim UAT terisolasi', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('support_team_members')->insert(['support_team_id' => $team, 'user_id' => $tech->id, 'role' => 'coordinator', 'created_at' => now(), 'updated_at' => now()]);
    $store = app(ConfigurationStore::class);
    $v = $store->draft($manager, 'UAT pilot di database terisolasi');
    $cfg = $v->configuration;
    $cfg['schema_version'] = 3;
    $cfg['profiles'] = OperationalProfile::defaults();
    $cfg['profiles']['sla'] = phase04TestSla();
    foreach ($cfg['services'] as &$service) {
        $service['owner_id'] = $manager->id;
        $service['fallback_team_id'] = $team;
    }unset($service);
    $cfg['profiles']['approval'] = [['key' => 'pilot_manager', 'record_type' => 'service_request', 'resolver' => 'user', 'approver_id' => $approver->id]];
    $v = $store->save($v->id, $cfg, 0, $manager, 'Profil SLA sintetis untuk UAT');
    $store->activate($v->id, $set->revision, $v->lock_version, $manager, 'Publikasi simulasi pada restore');
    config(['satset.it.requests_enabled' => true, 'satset.it.pilot.required' => true, 'satset.it.pilot.version_id' => $v->id, 'satset.it.pilot.user_ids' => [$requester->id],
        'satset.it.pilot.catalog_keys' => ['network_issue', 'access_request'], 'satset.it.pilot.business_approved' => true, 'satset.it.pilot.uat_approved' => true,
        'satset.it.pilot.restore_verified' => true, 'satset.it.pilot.support_ready' => true]);
    $gate = app(PilotGate::class);
    $workflow = app(TicketWorkflow::class);
    $assert($gate->readiness()['ready'], 'scope_and_schema_ready');
    $assert($gate->allowed($requester, 'network_issue'), 'requester_and_catalog_allowed');
    $assert(! $gate->allowed($outsider, 'network_issue') && ! $gate->allowed($requester, 'application_issue'), 'participant_and_catalog_restricted');
    config(['satset.it.pilot.uat_approved' => false]);
    $assert(! $gate->allowed($requester, 'network_issue'), 'uat_signoff_required');
    config(['satset.it.pilot.uat_approved' => true]);
    config(['satset.it.pilot.version_id' => $v->id + 1]);
    $assert(! $gate->allowed($requester, 'network_issue'), 'version_drift_fails_closed');
    config(['satset.it.pilot.version_id' => $v->id]);
    foreach (['network_issue', 'access_request'] as $catalog) {
        $request = $catalog === 'access_request';
        $answers = ['subject' => 'Pilot terisolasi', 'details' => 'Uji layanan IT'];
        if ($request) {
            $answers['business_reason'] = 'Simulasi kebutuhan';
        }
        $data = ['submission_token' => (string) Str::uuid(), 'catalog_key' => $catalog, 'title' => 'Pilot terisolasi', 'description' => 'UAT sintetis', 'impact' => 'medium', 'urgency' => 'high', 'answers' => $answers];
        $ticket = $workflow->submit($requester, $data);
        $tickets[] = $ticket->id;
        if ($request) {
            $approval = DB::table('it_ticket_approvals')->where('request_id', $ticket->id)->first();
            $workflow->decide($approver, $approval->id, ['lock_version' => 1, 'decision' => 'approved', 'reason' => 'UAT disetujui']);
        }
        foreach (['claim', 'start', 'resolve'] as $action) {
            $ticket = $workflow->action($tech, $ticket, $action, ['lock_version' => $ticket->fresh()->itDetail->lock_version, 'reason' => 'UAT penanganan']);
        }
        $ticket = $workflow->action($requester, $ticket, 'confirm', ['lock_version' => $ticket->fresh()->itDetail->lock_version]);
        $assert($ticket->itDetail->status === 'closed' && DB::table('it_sla_clocks')->where('request_id', $ticket->id)->count() === 2, $catalog.'_closed_with_sla');
    }
    config(['satset.it.requests_enabled' => false]);
    $assert(! $gate->allowed($requester, 'network_issue'), 'kill_switch_closes_new_submissions');
    $assert(app(\App\Support\ItAccess::class)::canRead($requester, $ticket), 'existing_ticket_remains_visible_when_closed');
} finally {
    DB::transaction(function () use ($tickets, $team, $users, $set, $maxVersion) {
        $clocks = DB::table('it_sla_clocks')->whereIn('request_id', $tickets)->orderByDesc('id')->pluck('id');
        DB::table('it_operation_notifications')->whereIn('request_id', $tickets)->delete();
        DB::table('it_delivery_outbox')->whereIn('request_id', $tickets)->delete();
        DB::table('it_sla_events')->whereIn('request_id', $tickets)->delete();
        DB::table('it_sla_spans')->whereIn('clock_id', $clocks)->delete();
        foreach ($clocks as $clock) {
            DB::table('it_sla_clocks')->where('id', $clock)->delete();
        }
        $events = DB::table('it_ticket_events')->whereIn('request_id', $tickets)->pluck('id');
        DB::table('it_ticket_notifications')->whereIn('event_id', $events)->delete();
        foreach (['it_ticket_approvals', 'it_ticket_tasks', 'it_ticket_attachments', 'it_ticket_comments', 'it_ticket_events', 'it_ticket_details'] as $table) {
            DB::table($table)->whereIn('request_id', $tickets)->delete();
        }
        DB::table('requests')->whereIn('id', $tickets)->delete();
        DB::table('it_configuration_sets')->where('id', $set->id)->update((array) $set);
        $ids = ConfigurationVersion::where('id', '>', $maxVersion)->orderByDesc('id')->pluck('id');
        DB::table('it_configuration_events')->whereIn('version_id', $ids)->delete();
        foreach ($ids as $id) {
            DB::table('it_configuration_versions')->where('id', $id)->delete();
        }
        if ($team) {
            DB::table('support_team_members')->where('support_team_id', $team)->delete();
            DB::table('support_teams')->where('id', $team)->delete();
        }
        DB::table('users')->whereIn('id', array_map(fn ($u) => $u->id, $users))->delete();
    });
}
echo json_encode(['driver' => DB::getDriverName(), 'health_simulated' => true, 'checks' => $checks, 'total_checks' => count($checks), 'fixture_rows_removed' => true],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
