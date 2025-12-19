<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\ProblemCategory;

class ProblemCategorySeeder extends Seeder
{
    public function run(): void
    {
        ProblemCategory::insert([
            ['name' => 'Network Issue', 'CODE' => 'Network'],
            ['name' => 'Hardware Issue', 'CODE' => 'Hardware'],
            ['name' => 'Software Bug', 'CODE' => 'Software'],
            ['name' => 'Access Request', 'CODE' => 'Access'],
        ]);
    }
}
