<?php

// Operator-only integration and genuine concurrent-process verification. Never runs on the source DB.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\It\ConfigurationVersion;
use App\Models\Master\Ticket;
use App\Models\MobileSatsetToken;
use App\Models\User;
use App\Services\It\ConfigurationStore;
use App\Services\It\OperationalProfile;
use App\Services\It\TicketWorkflow;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

if (! str_starts_with(DB::connection()->getDatabaseName(), 'satset_it_verify_')) {
    throw new RuntimeException('Only an isolated restored database is allowed.');
}
$app->instance('env', 'testing');
Http::preventStrayRequests();
config(['satset.it.requests_enabled' => true, 'satset.it.pilot.required' => false, 'satset.lrtj_space_notifications.enabled' => false, 'satset.ticket_email_notifications.enabled' => false]);
$workflow = app(TicketWorkflow::class);
if (($argv[1] ?? null) === 'worker') {
    [$mode,$actorId,$target,$lock,$start] = array_slice($argv, 2);
    usleep((int) max(0, min(2000000, ((float) $start - microtime(true)) * 1000000)));
    try {
        $actor = User::findOrFail((int) $actorId);
        if ($mode === 'claim') {
            $workflow->action($actor, Ticket::findOrFail((int) $target), 'claim', ['lock_version' => (int) $lock]);
        } else {
            $workflow->decide($actor, (int) $target, ['lock_version' => (int) $lock, 'decision' => 'approved', 'reason' => 'Concurrent verification approval']);
        }
        echo json_encode(['status' => 200]);
    } catch (HttpExceptionInterface $e) {
        echo json_encode(['status' => $e->getStatusCode()]);
    }
    exit;
}
$checks = [];
$assert = function ($condition, string $name) use (&$checks) {
    if (! $condition) {
        throw new RuntimeException('Failed: '.$name);
    }$checks[$name] = true;
};
$set = DB::table('it_configuration_sets')->where('code', 'it')->first();
$maxVersion = ConfigurationVersion::max('id');
$createdUsers = [];
$teamId = null;
$ticketIds = [];
$root = sys_get_temp_dir().'/satset-it-phase03-'.Str::random(12);
config(['filesystems.disks.it_private.root' => $root]);
$kernel = $app->make(Kernel::class);
$send = function (User $actor, string $method, string $uri, array $data = [], int $expected = 200, ?string $token = null) use ($kernel) {
    Auth::guard('web')->setUser($actor);
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    if ($token) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    }
    $r = Request::create($uri, $method, [], [], [], $server, json_encode($data));
    $r->setUserResolver(fn () => $actor);
    $res = $kernel->handle($r);
    $kernel->terminate($r, $res);
    if ($res->getStatusCode() !== $expected) {
        throw new RuntimeException($method.' '.$uri.' expected '.$expected.', received '.$res->getStatusCode().': '.substr($res->getContent(), 0, 1200));
    }

    return json_decode($res->getContent(), true);
};
$parallel = function (string $mode, array $actors, int $target, int $lock) {
    $running = [];
    $start = (string) (microtime(true) + 1.5);
    foreach ($actors as $actor) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $mode, (string) $actor->id, (string) $target, (string) $lock, $start], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not launch concurrency worker.');
        }fclose($pipes[0]);
        $running[] = [$process, $pipes];
    }
    $statuses = [];
    foreach ($running as [$process,$pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException('Worker failed: '.substr($err, 0, 1200));
        }$statuses[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR)['status'];
    }
    sort($statuses);

    return $statuses;
};
try {
    $manager = User::findOrFail(1);
    foreach (['Requester', 'Technician A', 'Technician B', 'Approver'] as $label) {
        $u = new User;
        $u->forceFill(['name' => 'IT verification '.$label, 'email' => Str::uuid().'@example.test', 'password' => bcrypt(Str::random(32)),
            'username' => 'it_verify_'.Str::random(12), 'phone' => '00000000', 'kartu_uang_1' => 'VERIFY-'.Str::random(16), 'user_type' => 'karyawan', 'role' => 'pelapor'])->save();
        $createdUsers[] = $u;
    }
    [$requester,$tech,$other,$approver] = $createdUsers;
    $teamId = DB::table('support_teams')->insertGetId(['code' => 'verify_'.Str::lower(Str::random(12)), 'name' => 'IT isolated verification', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    foreach ([$tech, $other] as $u) {
        DB::table('support_team_members')->insert(['support_team_id' => $teamId, 'user_id' => $u->id, 'role' => $u->id === $tech->id ? 'coordinator' : 'technician', 'created_at' => now(), 'updated_at' => now()]);
    }
    $store = app(ConfigurationStore::class);
    $v = $store->draft($manager, 'Phase 3 isolated verification');
    $cfg = $v->configuration;
    $cfg['schema_version'] = 2;
    $cfg['profiles'] = OperationalProfile::defaults();
    foreach ($cfg['services'] as &$s) {
        $s['owner_id'] = $manager->id;
        $s['fallback_team_id'] = $teamId;
    }unset($s);
    $cfg['profiles']['routing'] = [['key' => 'incident_desk', 'record_type' => 'incident', 'team_id' => $teamId]];
    $cfg['profiles']['approval'] = [['key' => 'manager', 'record_type' => 'service_request', 'resolver' => 'user', 'approver_id' => $approver->id]];
    $v = $store->save($v->id, $cfg, 0, $manager, 'Save operational verification profile');
    $store->activate($v->id, $set->revision, $v->lock_version, $manager, 'Publish only in isolated copy');
    $checks['mysql_operational_profile_published'] = true;
    $make = function (string $key, string $title) use ($workflow, $requester, &$ticketIds) {
        $requestType = in_array($key, ['access_request', 'software_request']) ? 'service_request' : 'incident';
        $answers = ['subject' => $title, 'details' => 'Isolated verification'];
        if ($requestType === 'service_request') {
            $answers['business_reason'] = 'Work requirement';
        } else {
            $answers += ['connection' => 'wifi', 'ssid' => 'Test office'];
        }
        $t = $workflow->submit($requester, ['submission_token' => (string) Str::uuid(), 'catalog_key' => $key, 'title' => $title, 'description' => 'Phase 3 isolated workflow test', 'impact' => 'medium', 'urgency' => 'high', 'answers' => $answers]);
        $ticketIds[] = $t->id;

        return $t;
    };
    $act = function (Ticket $t, string $action, array $data = [], ?User $actor = null) use ($workflow, $tech) {
        return $workflow->action($actor ?? $tech, $t, $action, ['lock_version' => $t->fresh()->itDetail->lock_version, 'reason' => 'Verified operational action'] + $data);
    };
    foreach (['network_issue' => 'Wi-Fi incident', 'application_issue' => 'Application error', 'access_request' => 'New account', 'software_request' => 'Software installation'] as $key => $title) {
        $t = $make($key, $title);
        if (in_array($key, ['access_request', 'software_request'])) {
            $a = DB::table('it_ticket_approvals')->where('request_id', $t->id)->first();
            $workflow->decide($approver, $a->id, ['lock_version' => 1, 'decision' => 'approved', 'reason' => 'Required for work']);
        }
        $t = $act($t, 'claim');
        $t = $act($t, 'start');
        if ($key === 'network_issue') {
            $t = $act($t, 'task', ['title' => 'Check access point', 'assigned_to' => $tech->id, 'required' => true]);
            $task = DB::table('it_ticket_tasks')->where('request_id', $t->id)->value('id');
            $t = $act($t, 'complete_task', ['task_id' => $task]);
            $t = $act($t, 'pending', ['next_update_at' => now()->addHour()->toDateTimeString()]);
            $t = $act($t, 'resume');
        }
        $t = $act($t, 'resolve');
        $t = $act($t, 'confirm', [], $requester);
        $assert($t->itDetail->status === 'closed', $key.'_submit_to_closed');
        $view = $workflow->view($requester, $t);
        $assert(! isset($view['events']) && ! isset($view['tasks']), $key.'_private_projection');
        if ($key === 'network_issue') {
            $t = $act($t, 'reopen', [], $requester);
            $assert($t->itDetail->status === 'queued', 'mysql_reopen');
        }
    }
    $claim = $make('network_issue', 'Concurrent claim');
    $assert($parallel('claim', [$tech, $other], $claim->id, 1) === [200, 409], 'simultaneous_claim_exactly_one_winner');
    $assert((int) DB::table('it_ticket_events')->where('request_id', $claim->id)->count() === 2, 'claim_single_audit_event');
    $approvalTicket = $make('access_request', 'Concurrent approval');
    $a = DB::table('it_ticket_approvals')->where('request_id', $approvalTicket->id)->first();
    $assert($parallel('approve', [$approver, $approver], $a->id, 1) === [200, 409], 'simultaneous_approval_exactly_one_winner');
    $assert((int) DB::table('it_ticket_events')->where('request_id', $approvalTicket->id)->where('action', 'approval_approved')->count() === 1, 'approval_single_audit_event');
    $token = Str::random(64);
    MobileSatsetToken::create(['user_id' => $requester->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);
    $send($requester, 'GET', '/api/mobile/v1/satset/it/tickets/'.$claim->id, [], 200, $token);
    $checks['mobile_bearer_reads_shared_workflow'] = true;
    $send($requester, 'POST', '/api/mobile/v1/satset/it/tickets/'.$claim->id.'/actions/claim', ['lock_version' => 2, 'role' => 'technician'], 403, $token);
    $checks['mobile_forged_role_rejected'] = true;
    $send($manager, 'GET', '/it/tickets/'.$claim->id, [], 403);
    $checks['configuration_admin_has_no_operational_read'] = true;
    $send($approver, 'GET', '/it/approvals/'.$a->id);
    $checks['purpose_limited_approval_view'] = true;
    $assert(! DB::table('it_ticket_notifications')->whereIn('user_id', [1, 31])->exists(), 'no_ga_or_configuration_admin_broadcast');
    $assert($claim->fresh()->itDetail->configuration_version_id === $v->id, 'mysql_pinned_configuration');
    $assert(DB::table('it_ticket_notifications')->count() === DB::table('it_ticket_notifications')->distinct()->count(DB::raw("CONCAT(event_id, ':', user_id)")), 'notification_recipient_uniqueness');
} finally {
    // Remove only fixtures from this isolated copy, then the caller hashes all original tables again.
    DB::transaction(function () use ($ticketIds, $createdUsers, $teamId, $set, $maxVersion) {
        $events = DB::table('it_ticket_events')->whereIn('request_id', $ticketIds)->pluck('id');
        DB::table('it_ticket_notifications')->whereIn('event_id', $events)->delete();
        foreach (['it_ticket_approvals', 'it_ticket_tasks', 'it_ticket_attachments', 'it_ticket_comments', 'it_ticket_events', 'it_ticket_details'] as $table) {
            DB::table($table)->whereIn('request_id', $ticketIds)->delete();
        }
        DB::table('requests')->whereIn('id', $ticketIds)->delete();
        DB::table('it_configuration_sets')->where('id', $set->id)->update((array) $set);
        $new = ConfigurationVersion::where('id', '>', $maxVersion)->orderByDesc('id')->pluck('id');
        DB::table('it_configuration_events')->whereIn('version_id', $new)->delete();
        foreach ($new as $id) {
            DB::table('it_configuration_versions')->where('id', $id)->delete();
        }
        if ($teamId) {
            DB::table('support_team_members')->where('support_team_id', $teamId)->delete();
            DB::table('support_teams')->where('id', $teamId)->delete();
        }
        $ids = array_map(fn ($u) => $u->id, $createdUsers);
        DB::table('mobile_satset_tokens')->whereIn('user_id', $ids)->delete();
        DB::table('users')->whereIn('id', $ids)->delete();
    });
}
echo json_encode(['driver' => DB::getDriverName(), 'checks' => $checks, 'total_checks' => count($checks), 'fixture_rows_removed' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
