<?php

namespace Database\Seeders;

use App\Models\Master\ConsumableItem;
use Illuminate\Database\Seeder;
use RuntimeException;

class CatalogGaItemSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/catalog_ga_items.csv');

        if (!is_file($path)) {
            throw new RuntimeException('File katalog GA tidak ditemukan: ' . $path);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('File katalog GA tidak bisa dibuka: ' . $path);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('File katalog GA kosong: ' . $path);
        }

        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if ($data === false || empty($data['code']) || empty($data['name'])) {
                continue;
            }

            $category = strtoupper(trim($data['category'] ?? ''));
            if (!in_array($category, ['ATK', 'RTK'], true)) {
                continue;
            }

            ConsumableItem::updateOrCreate(
                ['code' => trim($data['code'])],
                [
                    'name' => trim($data['name']),
                    'category' => $category,
                    'unit' => trim($data['unit'] ?: 'pcs'),
                    'unit_price' => $this->number($data['unit_price'] ?? 0),
                    'minimum_stock' => $this->integer($data['minimum_stock'] ?? 0),
                    'buffer_stock' => $this->integer($data['buffer_stock'] ?? 0),
                    'current_stock' => $this->integer($data['current_stock'] ?? 0),
                    'location' => trim($data['location'] ?? '') ?: null,
                    'is_active' => (bool) $this->integer($data['is_active'] ?? 1),
                ]
            );

            $imported++;
        }

        fclose($handle);
        $this->command?->info("Catalog GA imported: {$imported} items.");
    }

    private function integer(mixed $value): int
    {
        return (int) round((float) ($value ?: 0));
    }

    private function number(mixed $value): float
    {
        return round((float) ($value ?: 0), 2);
    }
}
