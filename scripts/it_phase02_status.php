<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\It\ConfigurationVersion;
use App\Models\User;
use App\Support\ItAccess;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$actor = User::findOrFail(1);
$kernel = $app->make(Kernel::class);
$version = ConfigurationVersion::orderBy('number')->firstOrFail();
$checks = [];
foreach (['/it/configuration', '/it/configuration/versions/'.$version->id] as $path) {
    Auth::guard('web')->setUser($actor);
    $request = Request::create($path, 'GET');
    $request->setUserResolver(fn () => $actor);
    $response = $kernel->handle($request);
    $checks[$path] = $response->getStatusCode();
    $kernel->terminate($request, $response);
}
$capabilities = [];
foreach ([1, 31] as $id) {
    $user = User::findOrFail($id);
    foreach (ItAccess::CAPABILITIES as $capability) {
        $capabilities[$id][$capability] = ItAccess::can($user, $capability);
    }
}
$latest = ConfigurationVersion::where('state', 'draft')->orderByDesc('number')->firstOrFail();
$bundle = $latest->configuration;
echo json_encode(['driver' => DB::getDriverName(), 'http_as_config_owner' => $checks, 'drafts' => ConfigurationVersion::where('state', 'draft')->count(), 'published_versions' => ConfigurationVersion::where('state', 'published')->count(), 'active_version_id' => DB::table('it_configuration_sets')->value('active_version_id'), 'capabilities' => $capabilities, 'submission_enabled' => config('satset.it.requests_enabled'),
    'latest_draft' => ['number' => $latest->number, 'categories' => count($bundle['categories']), 'forms' => count($bundle['forms']), 'wifi_category_created_from_ui' => collect($bundle['categories'])->contains(fn ($category) => $category['key'] === 'wifi' && $category['parent_key'] === 'network'), 'location_field_created_from_ui' => collect($bundle['forms'][0]['fields'])->contains(fn ($field) => $field['key'] === 'incident_location' && $field['label'] === 'Lokasi kejadian')]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
