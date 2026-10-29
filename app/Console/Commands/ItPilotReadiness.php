<?php

namespace App\Console\Commands;

use App\Services\It\PilotGate;
use Illuminate\Console\Command;

class ItPilotReadiness extends Command
{
    protected $signature = 'satset:it-pilot-readiness';

    protected $description = 'Periksa kesiapan dan cakupan pilot IT tanpa membuka pengajuan';

    public function handle(PilotGate $pilot): int
    {
        $result = $pilot->readiness();
        $result['feature_flag'] = (bool) config('satset.it.requests_enabled');
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
