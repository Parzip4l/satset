<?php

namespace App\Console\Commands;

use App\Services\It\OperationHealth;
use Illuminate\Console\Command;

class ItOperationHealth extends Command
{
    protected $signature = 'satset:it-health {--component=} {--max-age=180}';

    protected $description = 'Status heartbeat scheduler/worker dan backlog kegagalan IT';

    public function handle(OperationHealth $health): int
    {
        $report = $health->report(max(60, (int) $this->option('max-age')));
        $this->line(json_encode($report));
        $component = $this->option('component');

        return ($component ? ($report['components'][$component]['healthy'] ?? false) : $report['ready']) ? self::SUCCESS : self::FAILURE;
    }
}
