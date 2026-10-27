<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('requests') || ! Schema::hasTable('statuses')) {
            return;
        }

        $closedStatusId = DB::table('statuses')
            ->whereRaw('LOWER(name) = ?', ['closed'])
            ->value('id');

        if (! $closedStatusId) {
            return;
        }

        DB::table('requests')
            ->select(['id', 'payload', 'status_id', 'resolved_at', 'closed_at'])
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use ($closedStatusId): void {
                foreach ($tickets as $ticket) {
                    $payload = is_string($ticket->payload)
                        ? json_decode($ticket->payload, true)
                        : (array) $ticket->payload;

                    if (
                        data_get($payload, 'request_type') !== 'ga_request_finding'
                        || strtoupper((string) data_get($payload, 'workflow_status')) !== 'CLOSED'
                    ) {
                        continue;
                    }

                    DB::table('requests')->where('id', $ticket->id)->update([
                        'status_id' => $closedStatusId,
                        'resolved_at' => $ticket->resolved_at ?: now(),
                        'closed_at' => $ticket->closed_at ?: now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data normalization is intentionally not reverted.
    }
};
