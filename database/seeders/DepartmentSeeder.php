<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::insert([
            ['division_id' => 61, 'name' => 'Corporate Strategy', 'code' => 'CS'],
            ['division_id' => 61, 'name' => 'IT Support', 'code' => 'ITS'],
            ['division_id' => 61, 'name' => 'IT Development','code' => 'ITD'],
            ['division_id' => 61, 'name' => 'Accounting','code' => 'ACT'],
            ['division_id' => 61, 'name' => 'Recruitment','code' => 'RCT'],
        ]);
    }
}
