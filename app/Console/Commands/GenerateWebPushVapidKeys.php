<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateWebPushVapidKeys extends Command
{
    protected $signature = 'webpush:generate-vapid';

    protected $description = 'Generate VAPID keys for SatSet browser push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('Tambahkan nilai berikut ke .env (private key jangan dibagikan):');
        $this->newLine();
        $this->line('SATSET_WEB_PUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('SATSET_WEB_PUSH_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
