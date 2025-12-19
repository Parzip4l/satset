<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\EscalationRule;

class EscalationRuleSeeder extends Seeder
{
    public function run(): void
    {
        EscalationRule::insert([
            ['sla_id' => 1, 'escalate_to_role' => 'manager', 'time_limit' => 120],
            ['sla_id' => 2, 'escalate_to_role' => 'admin', 'time_limit' => 240],
        ]);
    }
}
