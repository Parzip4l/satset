<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_domains')->insert([
            'code' => 'sppd',
            'name' => 'Perjalanan Dinas',
            'description' => 'Pengajuan Surat Permohonan Perjalanan Dinas.',
            'icon' => 'bi-briefcase',
            'sort_order' => 4,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('service_domains')->where('code', 'sppd')->delete();
    }
};
