<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItFoundationSeeder extends Seeder
{
    public function run(): void
    {
        // Draft teams only. Membership, service owners and operational authority are configured later.
        foreach (['service-desk' => 'IT Service Desk', 'infrastructure' => 'IT Network & Infrastructure', 'application' => 'IT Application Support'] as $code => $name) {
            if (! DB::table('support_teams')->where('code', $code)->exists()) {
                DB::table('support_teams')->insert(['code' => $code, 'name' => $name, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
