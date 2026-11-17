<?php

namespace App\Http\Middleware;

use App\Support\GaSubmissionTiming;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TraceGaSubmission
{
    public function handle(Request $request, Closure $next, ?string $requestType = null): Response
    {
        $requestType ??= $request->input('request_type');
        if (! in_array($requestType, ['consumption', 'atk_rtk'], true)) {
            return $next($request);
        }

        $timing = new GaSubmissionTiming;
        $request->attributes->set(GaSubmissionTiming::class, $timing);
        $started = hrtime(true);
        $response = null;
        try {
            return $response = $next($request);
        } finally {
            $request->attributes->remove(GaSubmissionTiming::class);
            $elapsed = round((hrtime(true) - $started) / 1e6, 1);
            $stages = $timing->stages();
            $header = [sprintf('satset_submit;dur=%.1F', $elapsed)];
            foreach ($stages as $stage => $sample) {
                $header[] = sprintf('%s;dur=%.1F;desc="%d calls"', $stage, $sample['ms'], $sample['calls']);
            }
            $response?->headers->set('Server-Timing', implode(', ', $header), false);
            Log::info('SatSet GA submission timing', [
                'request_type' => $requestType,
                'route' => $request->route()?->uri(),
                'http_status' => $response?->getStatusCode(),
                'total_ms' => $elapsed,
                'stages' => $stages,
            ]);
        }
    }
}
