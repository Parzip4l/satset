<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillServiceDomains extends Command
{
    protected $signature = 'satset:backfill-service-domains {--apply : Terapkan mapping GA eksplisit; default hanya dry-run}';

    protected $description = 'Mapping layanan GA lama tanpa mengubah histori, approval atau timestamp tiket';

    public function handle(): int
    {
        if (! Schema::hasTable('service_domains') || ! Schema::hasColumn('requests', 'service_domain_id')) {
            $this->error('Migration fondasi IT belum dijalankan.');

            return self::FAILURE;
        }

        $domains = DB::table('service_domains')->pluck('id', 'code');
        $total = 0;
        foreach (['atk_rtk', 'consumption', 'ga_request_finding'] as $code) {
            if (! isset($domains[$code])) {
                $this->error('Master layanan tidak lengkap; tidak ada data yang diproses untuk layanan ini: '.$code);

                return self::FAILURE;
            }
        }
        foreach (['atk_rtk', 'consumption', 'ga_request_finding'] as $code) {
            $query = DB::table('requests')->whereNull('service_domain_id')->where('payload->request_type', $code);
            // A detail record takes precedence over a contradictory legacy GA marker.
            $query->whereNotExists(fn ($details) => $details->selectRaw('1')->from('it_ticket_details')->whereColumn('it_ticket_details.request_id', 'requests.id'));
            $count = (clone $query)->count();
            if ($this->option('apply')) {
                $count = $query->update(['service_domain_id' => $domains[$code]]);
            }
            $total += $count;
            $this->line($code.': '.$count);
        }
        $this->info(($this->option('apply') ? 'Diterapkan' : 'Dry-run').': '.$total.' tiket. general/unknown tetap memerlukan review.');

        return self::SUCCESS;
    }
}
