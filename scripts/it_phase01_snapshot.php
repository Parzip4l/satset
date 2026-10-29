<?php

// Operator-only evidence: invoked inside the app container, never exposed as an HTTP endpoint.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$projectionFile = $argv[1] ?? null;
$projection = $projectionFile ? json_decode(file_get_contents($projectionFile), true, 512, JSON_THROW_ON_ERROR) : null;
$result = ['driver' => DB::getDriverName(), 'tables' => []];
$tables = $projection ? array_keys($projection['tables']) : Schema::getTableListing(Schema::getCurrentSchemaListing(), schemaQualified: false);
$tables = array_values(array_unique($tables));
sort($tables);
foreach ($tables as $table) {
    if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new RuntimeException('Unsupported table identifier');
    }
    $columns = $projection['tables'][$table]['columns'] ?? Schema::getColumnListing($table);
    if (! $columns) {
        throw new RuntimeException('No columns found in the selected database for '.$table);
    }
    $hashes = DB::table($table)->select($columns)->get()->map(fn ($row) => hash('sha256', json_encode((array) $row, JSON_THROW_ON_ERROR)))->sort()->values()->all();
    $result['tables'][$table] = ['columns' => $columns, 'count' => count($hashes), 'sha256' => hash('sha256', implode("\n", $hashes))];
}
if (! $projection) {
    $result['categories'] = DB::table('problem_categories')->get(['id', 'parent_id', 'code', 'name']);
    $result['ticket_types'] = DB::table('ticket_categories')->get(['id', 'code', 'name']);
    $result['departments'] = DB::table('departments')->get(array_values(array_intersect(['id', 'name', 'code'], Schema::getColumnListing('departments'))));
    $result['operator_candidates'] = DB::table('users')->whereIn('role', ['admin', 'superadmin', 'super_admin'])->get(['id', 'name', 'role']);
    $result['review'] = DB::table('requests')->get(['id', 'category_id', 'ticket_category_id', 'title', 'payload'])->map(function ($row) {
        $payload = json_decode($row->payload ?? '{}', true) ?: [];

        return ['id' => $row->id, 'category_id' => $row->category_id, 'ticket_category_id' => $row->ticket_category_id,
            'title' => $row->title, 'request_type' => $payload['request_type'] ?? null, 'payload_keys' => array_keys($payload)];
    });
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
