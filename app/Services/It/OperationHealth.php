<?php

namespace App\Services\It;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationHealth
{
    public function beat(string $component, string $status, array $data = []): void
    {
        DB::table('it_worker_heartbeats')->updateOrInsert(['component' => $component], ['status' => $status, 'last_seen_at' => CarbonImmutable::now('UTC'), 'metadata' => json_encode($data)]);
    }

    public function report(int $maxAge = 180): array
    {
        if (! Schema::hasTable('it_worker_heartbeats')) {
            return ['ready' => false, 'reason' => 'Migration operasi IT belum diterapkan.'];
        }
        $components = [];
        $ok = true;
        foreach (['scheduler', 'worker'] as $name) {
            $row = DB::table('it_worker_heartbeats')->where('component', $name)->first();
            $age = $row ? (int) CarbonImmutable::parse($row->last_seen_at, 'UTC')->diffInSeconds(CarbonImmutable::now('UTC'), false) : null;
            $healthy = $row && $row->status === 'ok' && $age >= 0 && $age <= $maxAge;
            $components[$name] = ['healthy' => (bool) $healthy, 'age_seconds' => $age, 'last_seen_at' => $row?->last_seen_at, 'status' => $row?->status ?? 'missing', 'origin' => $row ? (json_decode($row->metadata, true)['runtime'] ?? null) : null];
            $ok = $ok && $healthy;
        }
        $failed = DB::table('it_delivery_outbox')->where('status', 'failed')->count();
        $pending = DB::table('it_delivery_outbox')->whereIn('status', ['pending', 'queued'])->count();
        $stale = DB::table('it_delivery_outbox')->whereIn('status', ['pending', 'queued'])->where('updated_at', '<', CarbonImmutable::now('UTC')->subMinutes(10))->count();
        $failedJobs = DB::table('it_queue_failed_jobs')->where('queue', 'it')->whereNull('resolved_at')->count();

        return ['ready' => (bool) ($ok && $failed === 0 && $stale === 0 && $failedJobs === 0), 'components' => $components, 'outbox_pending' => $pending, 'outbox_failed' => $failed, 'outbox_stale' => $stale, 'queued_jobs' => DB::table('it_queue_jobs')->where('queue', 'it')->count(), 'failed_jobs' => $failedJobs];
    }
}
