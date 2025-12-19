<?php

// database/seeders/TicketMasterSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketMasterSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('priorities')->insert([
            ['name' => 'Low', 'code' => 'LOW'],
            ['name' => 'Medium', 'code' => 'MED'],
            ['name' => 'High', 'code' => 'HIGH'],
            ['name' => 'Critical', 'code' => 'CRIT'],
        ]);

        DB::table('statuses')->insert([
            ['name' => 'Open', 'code' => 'OPEN'],
            ['name' => 'In Progress', 'code' => 'INPROG'],
            ['name' => 'Resolved', 'code' => 'RESOLVED'],
            ['name' => 'Closed', 'code' => 'CLOSED'],
            ['name' => 'Cancelled', 'code' => 'CANCEL'],
        ]);

        DB::table('impacts')->insert([
            ['name' => 'Low', 'code' => 'LOW'],
            ['name' => 'Medium', 'code' => 'MED'],
            ['name' => 'High', 'code' => 'HIGH'],
        ]);

        DB::table('urgencies')->insert([
            ['name' => 'Low', 'code' => 'LOW'],
            ['name' => 'Medium', 'code' => 'MED'],
            ['name' => 'High', 'code' => 'HIGH'],
        ]);
    }
}
