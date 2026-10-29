<?php

// Operator-only GET smoke verification. Mutation tests run only on isolated test databases.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Master\Ticket;
use App\Models\User;
use App\Support\ItAccess;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

$sourceReadOnly = in_array('--source-read-only', $argv, true);
if (! $sourceReadOnly && ! str_starts_with(DB::connection()->getDatabaseName(), 'satset_it_verify_')) {
    throw new RuntimeException('Verification must run on the isolated restore database.');
}
config(['satset.lrtj_space_notifications.enabled' => false, 'satset.ticket_email_notifications.enabled' => false]);
Http::preventStrayRequests();
$checks = [];
$assert = function (bool $passed, string $name) use (&$checks) {
    $checks[$name] = $passed;
    if (! $passed) {
        throw new RuntimeException('Verification failed: '.$name);
    }
};
$assert(DB::table('requests')->count() === 15, 'original_ticket_count');
$assert(DB::table('service_domain_reviews')->count() === 15, 'all_legacy_records_have_review_decision');
$assert(DB::table('service_domain_reviews')->where('decision', 'retained_legacy')->count() === 2, 'two_ambiguous_ga_records_retained');
$assert(DB::table('it_ticket_details')->count() === 6, 'only_explicit_record_types_materialized');
$assert(DB::table('support_teams')->count() === 3 && DB::table('support_team_members')->count() === 0, 'draft_teams_without_unapproved_members');
$assert(Schema::hasColumn('requests', 'submission_token') && Schema::hasTable('approval_audits'), 'supporting_migrations_applied');
$operator = User::findOrFail(1);
$assert(ItAccess::can($operator, 'manage_it_configuration') && ItAccess::can($operator, 'publish_it_configuration'), 'chosen_operator_has_configuration_capabilities');
$assert(! ItAccess::can($operator, 'view_it_queue') && ! ItAccess::can($operator, 'view_restricted_it'), 'configuration_does_not_grant_general_ticket_read');

$kernel = $app->make(Kernel::class);
foreach (['/ticket', '/ticket/tiket-saya', '/ticket/approvals', '/ticket/ga-requests', '/ticket/permintaan-konsumsi/create', '/ticket/atk-rtk/create', '/ticket/ga-permintaan-temuan/create', '/ticket/gudang-atk-rtk', '/bum/dashboard', '/it'] as $uri) {
    Auth::guard('web')->setUser($operator);
    $request = Request::create($uri, 'GET');
    $request->setUserResolver(fn () => $operator);
    $response = $kernel->handle($request);
    $assert($response->getStatusCode() === 200, 'http_get_'.$uri);
    $kernel->terminate($request, $response);
}
$owner = User::findOrFail(DB::table('requests')->where('id', 16)->value('requester_id'));
$ticket = Ticket::findOrFail(16);
$assert(ItAccess::canRead($owner, $ticket), 'legacy_it_requester_retains_read');
foreach (User::whereIn('id', [26, 31])->get() as $otherAdmin) {
    if ((int) $otherAdmin->id !== (int) $ticket->requester_id && (int) $otherAdmin->id !== (int) $ticket->assigned_user_id) {
        $assert(! ItAccess::canRead($otherAdmin, $ticket), 'other_admin_no_implicit_it_read_'.$otherAdmin->id);
        Auth::guard('web')->setUser($otherAdmin);
        $request = Request::create('/it/tickets/'.$ticket->getRouteKey(), 'GET');
        $request->setUserResolver(fn () => $otherAdmin);
        $response = $kernel->handle($request);
        $assert($response->getStatusCode() === 403, 'http_admin_it_detail_denied_'.$otherAdmin->id);
        $kernel->terminate($request, $response);
    }
}
$assert(config('satset.it.requests_enabled') === false, 'submission_stays_closed');
echo json_encode(['mode' => $sourceReadOnly ? 'source_get_only' : 'isolated_mysql', 'database_driver' => DB::getDriverName(), 'checks' => $checks, 'total_checks' => count($checks)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
