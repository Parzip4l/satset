<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::insert([
            [
                'division_id' => 61, // IT Support
                'name' => 'Admin System',
                'email' => 'admin@system.com',
                'phone' => null,
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'division_id' => 61, // Finance
                'name' => 'Request User',
                'email' => 'requester@company.com',
                'phone' => null,
                'password' => Hash::make('password'),
                'role' => 'requester',
            ],
            [
                'division_id' => 61, // Corporate Strategy
                'name' => 'Approver Manager',
                'email' => 'approver@company.com',
                'phone' => null,
                'password' => Hash::make('password'),
                'role' => 'approver',
            ],
            [
                'division_id' => 61, // IT Support
                'name' => 'IT Staff',
                'email' => 'staff@company.com',
                'phone' => null,
                'password' => Hash::make('password'),
                'role' => 'staff',
            ],
        ]);
    }
}
