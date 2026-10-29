<?php

namespace Database\Seeders;

use App\Models\Master\EscalationRule;
use Illuminate\Database\Seeder;

class EscalationRuleSeeder extends Seeder
{
    public function run(): void
    {
        // No user or recipient authority is inferred from a demonstration policy.
        foreach (\App\Models\Master\Sla::where('name', 'like', 'Demo %')->get() as $sla) {
            EscalationRule::firstOrCreate(['sla_id' => $sla->id, 'threshold_minutes' => $sla->resolve_time_minutes]);
        }
    }
}
