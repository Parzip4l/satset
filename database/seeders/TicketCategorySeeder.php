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
        foreach ([
            ['name' => 'Incident', 'code' => 'INC'],
            ['name' => 'Service Request', 'code' => 'SR'],
            ['name' => 'Change Request', 'code' => 'CR'],
            [
                'name' => 'Layanan BUM',
                'code' => 'BUM',
                'description' => 'Layanan Bagian Umum untuk ATK/RTK, konsumsi rapat, dan QR Permintaan & Temuan.',
            ],
        ] as $category) {
            DB::table('ticket_categories')->updateOrInsert(
                ['code' => $category['code']],
                $category
            );
        }
    }
}
