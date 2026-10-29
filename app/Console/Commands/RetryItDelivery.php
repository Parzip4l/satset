<?php

namespace App\Console\Commands;

use App\Services\It\OperationDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryItDelivery extends Command
{
    protected $signature = 'satset:it-retry {id} {--reason=}';

    protected $description = 'Ulangi satu outbox IT yang gagal, dengan alasan operator';

    public function handle(OperationDelivery $delivery): int
    {
        if (mb_strlen(trim((string) $this->option('reason'))) < 5) {
            $this->error('Alasan minimal 5 karakter.');

            return self::FAILURE;
        }
        $count = DB::table('it_delivery_outbox')->where('id', $this->argument('id'))->where('status', 'failed')->update(['status' => 'pending', 'dispatched_at' => null, 'updated_at' => now('UTC')]);
        if (! $count) {
            $this->error('Outbox gagal tidak ditemukan.');

            return self::FAILURE;
        }
        Log::notice('IT delivery manually retried', ['id' => $this->argument('id'), 'reason' => $this->option('reason')]);
        $delivery->enqueue();

        return self::SUCCESS;
    }
}
