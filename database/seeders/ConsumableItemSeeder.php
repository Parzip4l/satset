<?php

namespace Database\Seeders;

use App\Models\Master\ConsumableItem;
use Illuminate\Database\Seeder;

class ConsumableItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'ATK-PEN-001', 'name' => 'Pulpen Hitam', 'category' => 'ATK', 'unit' => 'pcs', 'minimum_stock' => 20, 'buffer_stock' => 50, 'current_stock' => 0, 'location' => 'ATK-01'],
            ['code' => 'ATK-PPR-001', 'name' => 'Kertas A4', 'category' => 'ATK', 'unit' => 'rim', 'minimum_stock' => 10, 'buffer_stock' => 25, 'current_stock' => 0, 'location' => 'ATK-02'],
            ['code' => 'RTK-TIS-001', 'name' => 'Tisu Ruangan', 'category' => 'RTK', 'unit' => 'pack', 'minimum_stock' => 15, 'buffer_stock' => 30, 'current_stock' => 0, 'location' => 'RTK-01'],
        ] as $item) {
            ConsumableItem::updateOrCreate(['code' => $item['code']], $item + ['is_active' => true]);
        }
    }
}
