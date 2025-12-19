<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Assignment;

class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        Assignment::insert([
            ['request_id' => 1, 'assigned_to' => 4, 'assigned_by' => 3],
            ['request_id' => 2, 'assigned_to' => 4, 'assigned_by' => 1],
        ]);
    }
}
