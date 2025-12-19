<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Divisions;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        Divisions::insert([
            ['name' => 'Corporate'],
            ['name' => 'IT'],
            ['name' => 'Finance'],
            ['name' => 'HR'],
        ]);
    }
}
