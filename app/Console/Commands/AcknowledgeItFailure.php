<?php

namespace App\Console\Commands;

use App\Services\It\OperationHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcknowledgeItFailure extends Command
{
    protected $signature = 'satset:it-ack-failure {uuid} {--reason=}';

    protected $description = 'Tandai kegagalan antrean IT yang sudah ditangani; bukti tetap disimpan';

    public function handle(OperationHealth $health): int
    {
        if (mb_strlen(trim((string) $this->option('reason'))) < 5 || ! ($health->report()['components']['worker']['healthy'] ?? false)) {
            $this->error('Worker harus sehat dan alasan minimal 5 karakter.');

            return self::FAILURE;
        }
        $changed = DB::table('it_queue_failed_jobs')->where('uuid', $this->argument('uuid'))->where('queue', 'it')->whereNull('resolved_at')->update(['resolved_at' => now('UTC')]);
        if (! $changed) {
            $this->error('Kegagalan IT belum terselesaikan tidak ditemukan.');

            return self::FAILURE;
        }
        Log::notice('IT failed job acknowledged', ['uuid' => $this->argument('uuid'), 'reason' => $this->option('reason')]);

        return self::SUCCESS;
    }
}
