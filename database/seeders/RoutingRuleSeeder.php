<?php

namespace Database\Seeders;

use App\Models\Master\RoutingRule;
use Illuminate\Database\Seeder;

class RoutingRuleSeeder extends Seeder
{
    public function run(): void
    {
        // Use real category/department references rather than invented role names or numeric IDs.
        foreach (\App\Models\Master\DepartmentCategory::all() as $mapping) {
            RoutingRule::firstOrCreate(['category_id' => $mapping->category_id, 'assignee_department_id' => $mapping->department_id], ['default_priority' => 'MED']);
        }
    }
}
