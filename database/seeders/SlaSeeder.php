<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Sla;

class SlaSeeder extends Seeder
{
    public function run(): void
    {
        Sla::insert([
            ['problem_category_id' => 1, 'response_time' => 30, 'resolution_time' => 120],
            ['problem_category_id' => 2, 'response_time' => 60, 'resolution_time' => 240],
            ['problem_category_id' => 3, 'response_time' => 45, 'resolution_time' => 180],
        ]);
    }
}
