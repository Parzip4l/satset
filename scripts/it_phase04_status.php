<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\It\OperationHealth;
use Illuminate\Support\Facades\DB;

$counts = [];
foreach (['it_sla_clocks', 'it_sla_events', 'it_ticket_links', 'it_delivery_outbox', 'it_operation_notifications'] as $t) {
    $counts[$t] = DB::table($t)->count();
}
echo json_encode(['migration_applied' => DB::table('migrations')->where('migration', '2026_10_28_000005_create_it_sla_operations')->exists(),
    'requests_enabled' => config('satset.it.requests_enabled'), 'requests_count' => DB::table('requests')->count(),
    'active_version_id' => DB::table('it_configuration_sets')->where('code', 'it')->value('active_version_id'),
    'draft_versions' => DB::table('it_configuration_versions')->where('state', 'draft')->count(),
    'capabilities' => DB::table('it_user_capabilities')->whereIn('user_id', [1, 31])->orderBy('user_id')->orderBy('capability')->get(['user_id', 'capability']),
    'runtime_counts' => $counts, 'health' => app(OperationHealth::class)->report()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
