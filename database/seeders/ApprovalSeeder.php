<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Master\Approval;

class ApprovalSeeder extends Seeder
{
    public function run(): void
    {
        Approval::insert([
            ['request_id' => 1, 'approver_id' => 3, 'status' => 'Pending', 'note' => null],
            ['request_id' => 2, 'approver_id' => 3, 'status' => 'Approved', 'note' => 'Proceed'],
        ]);
    }
}
