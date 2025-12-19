<?php

namespace Database\Seeders;

use App\Models\General\Menu;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuItems = [
            ['title' => 'Dashboard', 'icon' => 'solar:widget-5-bold-duotone', 'url' => 'dashboard.index', 'parent_id' => null, 'order' => 1],
            ['title' => 'Products', 'icon' => 'mdi:bucket-minus', 'url' => null, 'parent_id' => null, 'order' => 2],
            ['title' => 'List', 'icon' => null, 'url' => 'product.index', 'parent_id' => 2, 'order' => 1],
            ['title' => 'Create', 'icon' => null, 'url' => 'product.create', 'parent_id' => 2, 'order' => 2],
        ];

        foreach ($menuItems as $menu) {
            Menu::create($menu);
        }
    }
}
