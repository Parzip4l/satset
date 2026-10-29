<?php

namespace App\Console\Commands;

use App\Support\ItAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GrantItCapability extends Command
{
    protected $signature = 'satset:it-capability {user_id} {capability} {--revoke} {--reason= : Alasan perubahan untuk audit operator}';

    protected $description = 'Bootstrap capability IT secara eksplisit oleh operator server; role admin tidak otomatis mendapat akses';

    public function handle(): int
    {
        $id = filter_var($this->argument('user_id'), FILTER_VALIDATE_INT);
        $capability = $this->argument('capability');
        if (! $id || ! in_array($capability, ItAccess::CAPABILITIES, true) || ! trim((string) $this->option('reason'))) {
            $this->error('Gunakan user_id valid, capability terdaftar, dan --reason. Capability: '.implode(', ', ItAccess::CAPABILITIES));

            return self::FAILURE;
        }
        if (! Schema::hasTable('it_user_capabilities') || ! DB::table('users')->where('id', $id)->exists()) {
            $this->error('Migration belum tersedia atau pengguna tidak ditemukan.');

            return self::FAILURE;
        }
        $key = ['user_id' => $id, 'capability' => $capability];
        if ($this->option('revoke')) {
            DB::table('it_user_capabilities')->where($key)->delete();
        } else {
            DB::table('it_user_capabilities')->insertOrIgnore($key + ['created_at' => now(), 'updated_at' => now()]);
        }
        Log::notice('IT capability changed by server operator', $key + ['revoked' => (bool) $this->option('revoke'), 'reason' => $this->option('reason')]);
        $this->info('Capability IT diperbarui. Tidak ada hak tambahan yang diberikan dari nama role.');

        return self::SUCCESS;
    }
}
