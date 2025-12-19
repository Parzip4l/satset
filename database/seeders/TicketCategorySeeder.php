<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('ticket_categories')->insert([
            ['name' => 'Incident', 'code' => 'INC'],
            ['name' => 'Service Request', 'code' => 'SR'],
            ['name' => 'Change Request', 'code' => 'CR'],
        ]);
    }
}
