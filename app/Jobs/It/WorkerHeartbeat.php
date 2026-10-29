<?php

namespace App\Jobs\It;

use App\Services\It\OperationHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    public function handle(OperationHealth $health): void
    {
        $health->beat('worker', 'ok', ['pid' => getmypid(), 'runtime' => getenv('IT_RUNTIME_COMPONENT') ?: 'cli']);
    }
}
