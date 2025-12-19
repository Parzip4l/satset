<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\RoutingRule;

class RoutingRuleSeeder extends Seeder
{
    public function run(): void
    {
        RoutingRule::insert([
            ['problem_category_id' => 1, 'assigned_role' => 'staff'],
            ['problem_category_id' => 2, 'assigned_role' => 'staff'],
            ['problem_category_id' => 3, 'assigned_role' => 'developer'],
        ]);
    }
}
