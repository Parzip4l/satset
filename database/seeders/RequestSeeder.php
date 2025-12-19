<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Ticket;

class RequestSeeder extends Seeder
{
    public function run(): void
    {
        Ticket::insert([
            [
                'user_id' => 2,
                'problem_category_id' => 1,
                'description' => 'Internet not working in office.',
                'status' => 'Open',
            ],
            [
                'user_id' => 2,
                'problem_category_id' => 3,
                'description' => 'Bug in payroll system.',
                'status' => 'Open',
            ],
        ]);
    }
}
