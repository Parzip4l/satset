<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = ['it_ticket_events', 'it_ticket_comments', 'it_ticket_attachments', 'it_ticket_tasks', 'it_ticket_approvals', 'it_ticket_notifications', 'it_team_events'];
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
}
echo json_encode([
    'phase03_migration_applied' => DB::table('migrations')->where('migration', '2026_10_28_000004_create_it_workflow')->exists(),
    'requests_enabled' => config('satset.it.requests_enabled'), 'requests_count' => DB::table('requests')->count(),
    'runtime_it_tickets' => Schema::hasColumn('it_ticket_details', 'catalog_key') ? DB::table('it_ticket_details')->whereNotNull('catalog_key')->count() : null,
    'active_configuration_id' => DB::table('it_configuration_sets')->where('code', 'it')->value('active_version_id'),
    'versions' => DB::table('it_configuration_versions')->orderBy('id')->get(['id', 'number', 'state', 'lock_version', 'checksum']),
    'capabilities' => DB::table('it_user_capabilities')->whereIn('user_id', [1, 31])->orderBy('user_id')->orderBy('capability')->get(['user_id', 'capability']),
    'teams' => DB::table('support_teams')->orderBy('id')->get(['id', 'code', 'is_active']),
    'team_members_count' => DB::table('support_team_members')->count(), 'workflow_tables' => $counts,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
