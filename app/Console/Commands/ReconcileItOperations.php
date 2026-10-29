<?php

namespace App\Console\Commands;

use App\Jobs\It\WorkerHeartbeat;
use App\Services\It\OperationDelivery;
use App\Services\It\OperationHealth;
use App\Services\It\SlaEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileItOperations extends Command
{
    protected $signature = 'satset:it-reconcile';

    protected $description = 'Rekonsiliasi SLA/komunikasi IT dan outbox; tidak memproses tiket GA';

    public function handle(SlaEngine $sla, OperationDelivery $delivery, OperationHealth $health): int
    {
        if (! $sla->available()) {
            $this->warn('Migration SLA IT belum diterapkan.');

            return self::FAILURE;
        }
        $health->beat('scheduler', 'running');
        $count = 0;
        $errors = 0;
        try {
            DB::table('it_ticket_details')->whereNotNull('catalog_key')->where(fn ($q) => $q->whereIn('status', ['triage', 'queued', 'awaiting_approval', 'approval_exception', 'needs_revision', 'in_progress', 'pending'])->orWhere('is_major', true))->orderBy('id')->chunkById(100, function ($rows) use ($sla, &$count, &$errors) {
                foreach ($rows as $row) {
                    try {
                        $sla->reconcileTicket($row->request_id);
                        $count++;
                    } catch (\Throwable $error) {
                        $errors++;
                        report($error);
                    }
                }
            });
            $queued = $delivery->enqueue();
            WorkerHeartbeat::dispatch()->onConnection('it_database')->onQueue('it');
            $health->beat('scheduler', $errors ? 'failed' : 'ok', ['errors' => $errors, 'tickets' => $count, 'dispatched' => $queued, 'runtime' => getenv('IT_RUNTIME_COMPONENT') ?: 'cli', 'pid' => getmypid()]);
            $this->info(json_encode(['reconciled' => $count, 'dispatched' => $queued, 'errors' => $errors]));

            return $errors ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $health->beat('scheduler', 'failed', ['error_class' => get_class($e)]);
            report($e);
            throw $e;
        }
    }
}
