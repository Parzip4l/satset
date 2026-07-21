<?php

namespace Database\Seeders;

use App\Services\Organization\SignalOrganizationSyncService;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(SignalOrganizationSyncService $syncService): void
    {
        $result = $syncService->sync();
        $this->command?->info("Signal organization synced: {$result['divisions']} divisions, {$result['departments']} departments.");
    }
}
