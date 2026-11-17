<?php

namespace App\Http\Middleware;

use App\Support\GaSubmissionTiming;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $phpStarted = $request->server('REQUEST_TIME_FLOAT');
        $beforeSubmit = is_numeric($phpStarted) ? max(0, (microtime(true) - (float) $phpStarted) * 1000) : null;
        $beforeSql = $this->databaseQueryDuration();
        $response = null;
        try {
            return $response = $next($request);
        } finally {
            $request->attributes->remove(GaSubmissionTiming::class);
            $elapsed = round((hrtime(true) - $started) / 1e6, 1);
            $sql = round($this->databaseQueryDuration() - $beforeSql, 1);
            $stages = $timing->stages();
            $header = [sprintf('satset_submit;dur=%.1F', $elapsed), sprintf('satset_sql;dur=%.1F', $sql)];
            if ($beforeSubmit !== null) {
                $header[] = sprintf('php_before_submit;dur=%.1F', $beforeSubmit);
            }
            foreach ($stages as $stage => $sample) {
                $header[] = sprintf('%s;dur=%.1F;desc="%d calls"', $stage, $sample['ms'], $sample['calls']);
            }
            $logStarted = hrtime(true);
            Log::info('SatSet GA submission timing', [
                'request_type' => $requestType,
                'route' => $request->route()?->uri(),
                'http_status' => $response?->getStatusCode(),
                'total_ms' => $elapsed,
                'sql_ms' => $sql,
                'php_before_submit_ms' => $beforeSubmit === null ? null : round($beforeSubmit, 1),
                'stages' => $stages,
            ]);
            $header[] = sprintf('satset_log;dur=%.1F', (hrtime(true) - $logStarted) / 1e6);
            $response?->headers->set('Server-Timing', implode(', ', $header), false);
        }
    }

    private function databaseQueryDuration(): float
    {
        return array_sum(array_map(fn ($connection) => $connection->totalQueryDuration(), DB::getConnections()));
    }
}
