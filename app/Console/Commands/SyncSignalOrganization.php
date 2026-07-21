<?php

namespace App\Console\Commands;

use App\Services\Organization\SignalOrganizationSyncService;
use Illuminate\Console\Command;

class SyncSignalOrganization extends Command
{
    protected $signature = 'organization:sync-signal';

    protected $description = 'Sync divisions and departments from Signal organization API';

    public function handle(SignalOrganizationSyncService $syncService): int
    {
        $result = $syncService->sync();

        $this->info("Signal organization synced: {$result['divisions']} divisions, {$result['departments']} departments.");

        return self::SUCCESS;
    }
}
