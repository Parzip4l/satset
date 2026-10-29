<?php

namespace Database\Seeders;

use App\Models\Master\Sla;
use Illuminate\Database\Seeder;

class SlaSeeder extends Seeder
{
    public function run(): void
    {
        // Demonstration only; this seeder is deliberately not called by DatabaseSeeder.
        foreach ([['Demo Network', 30, 120], ['Demo Hardware', 60, 240], ['Demo Application', 45, 180]] as [$name, $response, $resolve]) {
            Sla::firstOrCreate(['name' => $name], ['response_time_minutes' => $response, 'resolve_time_minutes' => $resolve, 'business_hours' => true]);
        }
    }
}
