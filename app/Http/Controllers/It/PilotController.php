<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Services\It\PilotGate;
use App\Support\ItAccess;
use Illuminate\Http\Request;

class PilotController extends Controller
{
    public function __invoke(Request $request, PilotGate $gate)
    {
        abort_unless(ItAccess::can($request->user(), 'manage_it_configuration') || ItAccess::can($request->user(), 'publish_it_configuration'), 403);
        $result = $gate->readiness();
        $enabled = (bool) config('satset.it.requests_enabled');

        return $request->expectsJson() ? response()->json($result + ['feature_flag' => $enabled]) : view('it.configuration.pilot', compact('result', 'enabled'));
    }
}
