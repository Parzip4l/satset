<?php

namespace App\Services;

use App\Models\Master\ConsumableItem;
use App\Models\Master\ConsumableUom;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class ConsumableItemImportService
{
    public function __construct(private readonly ConsumableStockService $stockService)
    {
    }

    public function import(UploadedFile|string $file, array $options = [], ?int $userId = null): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $extension = strtolower($file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($path, PATHINFO_EXTENSION));

        if (!$path || !is_file($path)) {
            throw new InvalidArgumentException('File upload tidak ditemukan.');
        }

        $rows = match ($extension) {
            'xlsx' => $this->readXlsx($path, $options['sheet_name'] ?? 'Catalog Master mini'),
            'csv' => $this->readCsv($path),
            default => throw new InvalidArgumentException('Format file harus .xlsx atau .csv.'),
        };

        $records = $this->mapRows($rows);
        $stockMode = $options['stock_mode'] ?? 'keep';
        if (!in_array($stockMode, ['keep', 'big', 'small', 'both'], true)) {
            $stockMode = 'keep';
        }

        return DB::transaction(function () use ($records, $stockMode, $userId) {
            $summary = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'stock_adjusted' => 0,
                'errors' => [],
            ];

            foreach ($records as $record) {
                if ($record['error']) {
                    $summary['skipped']++;
                    $summary['errors'][] = $record['error'];
                    continue;
                }

                $this->ensureUom($record['large_uom']);
                $this->ensureUom($record['small_uom']);

                $item = ConsumableItem::where('code', $record['code'])->first();
                $isNew = !$item;
                $attributes = [
                    'code' => $record['code'],
                    'name' => $record['name'],
                    'category' => $record['category'],
                    'unit' => $record['small_uom'],
                    'large_uom' => $record['large_uom'],
                    'small_uom' => $record['small_uom'],
                    'conversion_qty' => $record['conversion_qty'],
                    'unit_price' => $record['unit_price'],
                    'minimum_stock' => $record['minimum_stock'],
                    'buffer_stock' => $record['buffer_stock'],
                    'location' => $record['location'],
                    'is_active' => true,
                ];

                if ($isNew) {
                    $item = ConsumableItem::create($attributes + [
                        'current_stock' => 0,
                        'small_stock' => 0,
                    ]);
                    $summary['created']++;
                } else {
                    $item->update($attributes);
                    $summary['updated']++;
                }

                $fresh = $item->fresh();
                if ($record['stock'] !== null && in_array($stockMode, ['big', 'both'], true)) {
                    $summary['stock_adjusted'] += $this->overwriteStock($fresh, 'big_warehouse', $record['stock'], $userId);
                    $fresh = $fresh->fresh();
                }

                if ($record['stock'] !== null && in_array($stockMode, ['small', 'both'], true)) {
                    $summary['stock_adjusted'] += $this->overwriteStock($fresh, 'small_warehouse', $record['stock'], $userId);
                }
            }

            return $summary;
        });
    }

    private function overwriteStock(ConsumableItem $item, string $stockLocation, int $target, ?int $userId): int
    {
        $column = $stockLocation === 'small_warehouse' ? 'small_stock' : 'current_stock';
        $delta = $target - (int) $item->{$column};
        if ($delta === 0) {
            return 0;
        }

        $notes = 'Overwrite stok dari upload master barang';
        if ($stockLocation === 'small_warehouse') {
            $this->stockService->adjustmentSmall($item, $delta, 'master_item_upload', $item->id, $notes, $userId);
        } else {
            $this->stockService->adjustment($item, $delta, 'master_item_upload', $item->id, $notes, $userId);
        }

        return 1;
    }

    private function mapRows(array $rows): array
    {
        $headerIndex = $this->findHeaderIndex($rows);
        if ($headerIndex === null) {
            throw new RuntimeException('Header file tidak ditemukan. Pastikan ada kolom Kode Barang dan Nama Barang.');
        }

        $columns = $this->detectColumns($rows[$headerIndex], $rows[$headerIndex + 1] ?? []);
        $records = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $code = $this->text($row[$columns['code'] ?? -1] ?? null);
            $name = $this->text($row[$columns['name'] ?? -1] ?? null);
            $category = strtoupper($this->text($row[$columns['category'] ?? -1] ?? null));

            if ($code === '' && $name === '') {
                continue;
            }

            if (in_array(strtoupper($code), ['I', 'II', 'III'], true) || strtolower($code) === 'kode barang') {
                continue;
            }

            $error = null;
            if ($code === '' || $name === '') {
                $error = 'Baris ' . ($i + 1) . ' dilewati: kode atau nama kosong.';
            }

            if (!in_array($category, ['ATK', 'RTK'], true)) {
                $error = 'Baris ' . ($i + 1) . " dilewati: kategori {$category} tidak valid.";
            }

            $largeUom = $this->normalizeUom($row[$columns['large_uom'] ?? -1] ?? null);
            $smallUom = $this->normalizeUom($row[$columns['small_uom'] ?? -1] ?? null);
            $conversionQty = $this->integer($row[$columns['conversion_qty'] ?? -1] ?? null);
            if ($largeUom === '') {
                $largeUom = $smallUom ?: 'pcs';
            }
            if ($smallUom === '') {
                $smallUom = $largeUom ?: 'pcs';
            }
            if ($conversionQty <= 0 || !is_numeric($row[$columns['conversion_qty'] ?? -1] ?? null)) {
                $conversionQty = $largeUom === $smallUom ? 1 : 1;
            }

            $minimumStock = $this->integer($row[$columns['minimum_stock'] ?? -1] ?? null);
            $stock = $this->integer($row[$columns['stock'] ?? -1] ?? $row[$columns['opening_stock'] ?? -1] ?? null);

            $records[] = [
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'large_uom' => $largeUom,
                'small_uom' => $smallUom,
                'conversion_qty' => max(1, $conversionQty),
                'unit_price' => $this->number($row[$columns['unit_price'] ?? -1] ?? null),
                'minimum_stock' => max(0, $minimumStock),
                'buffer_stock' => max(0, $minimumStock),
                'stock' => $stock < 0 ? 0 : $stock,
                'location' => $this->nullableText($row[$columns['location'] ?? -1] ?? null),
                'error' => $error,
            ];
        }

        return $records;
    }

    private function detectColumns(array $header, array $subHeader): array
    {
        $columns = [];
        foreach ($header as $index => $label) {
            $normalized = $this->normalizeHeader($label);
            match ($normalized) {
                'kategori', 'category' => $columns['category'] = $index,
                'kode_barang', 'kode', 'code', 'itemcode' => $columns['code'] = $index,
                'nama_barang', 'description', 'name' => $columns['name'] = $index,
                'satuan', 'unit', 'large_uom' => $columns['large_uom'] ??= $index,
                'binloc', 'location', 'lokasi' => $columns['location'] = $index,
                'harga', 'unit_price', 'value' => $columns['unit_price'] = $index,
                'stok_minimum', 'minimum_stock' => $columns['minimum_stock'] = $index,
                'saldo_awal', 'opening_stock' => $columns['opening_stock'] = $index,
                'soh_saldo_berjalan', 'soh', 'current_stock' => $columns['stock'] = $index,
                'conversion_qty', 'jumlah_konversi' => $columns['conversion_qty'] = $index,
                'small_uom', 'satuan_kecil' => $columns['small_uom'] = $index,
                default => null,
            };
        }

        foreach ($subHeader as $index => $label) {
            $normalized = $this->normalizeHeader($label);
            if ($normalized === 'jumlah_konversi') {
                $columns['conversion_qty'] = $index;
            }
            if ($normalized === 'satuan' && isset($columns['large_uom'], $columns['location']) && $index > $columns['large_uom'] && $index < $columns['location']) {
                $columns['small_uom'] = $index;
            }
        }

        return $columns;
    }

    private function findHeaderIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $headers = array_map(fn ($value) => $this->normalizeHeader($value), $row);
            if (in_array('kode_barang', $headers, true) && in_array('nama_barang', $headers, true)) {
                return $index;
            }
            if (in_array('code', $headers, true) && in_array('name', $headers, true)) {
                return $index;
            }
        }

        return null;
    }

    private function ensureUom(string $code): void
    {
        ConsumableUom::firstOrCreate(
            ['code' => $code],
            ['name' => ucfirst($code), 'is_active' => true, 'sort_order' => 999]
        );
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('File CSV tidak bisa dibuka.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path, string $preferredSheet): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File XLSX tidak bisa dibuka.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetPath = $this->resolveSheetPath($zip, $preferredSheet);
        $xml = simplexml_load_string($zip->getFromName($sheetPath));
        if (!$xml || !isset($xml->sheetData)) {
            throw new RuntimeException('Sheet XLSX tidak bisa dibaca.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowXml) {
            $row = [];
            foreach ($rowXml->c as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $match);
                $index = $this->columnIndex($match[0] ?? 'A') - 1;
                $row[$index] = $this->cellValue($cell, $sharedStrings);
            }
            if ($row !== []) {
                ksort($row);
                $rows[] = $row + array_fill(0, max(array_keys($row)) + 1, null);
            }
        }

        $zip->close();

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        $strings = [];
        foreach ($xml->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function resolveSheetPath(ZipArchive $zip, string $preferredSheet): string
    {
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $relations = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        if (!$workbook || !$relations) {
            throw new RuntimeException('Struktur workbook XLSX tidak valid.');
        }

        $relationTargets = [];
        foreach ($relations->Relationship as $relation) {
            $relationTargets[(string) $relation['Id']] = (string) $relation['Target'];
        }

        $fallback = null;
        foreach ($workbook->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationId = (string) $attributes['id'];
            $target = $relationTargets[$relationId] ?? null;
            if (!$target) {
                continue;
            }

            $path = 'xl/' . ltrim($target, '/');
            $fallback ??= $path;
            if (strcasecmp((string) $sheet['name'], $preferredSheet) === 0) {
                return $path;
            }
        }

        if ($fallback) {
            return $fallback;
        }

        throw new RuntimeException('Sheet XLSX tidak ditemukan.');
    }

    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string|float|int|null
    {
        $type = (string) $cell['t'];
        if ($type === 's') {
            return $sharedStrings[(int) $cell->v] ?? null;
        }
        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        $value = (string) $cell->v;
        if ($value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : $value;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index;
    }

    private function normalizeHeader(mixed $value): string
    {
        return trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', strtolower($this->text($value)))), '_');
    }

    private function normalizeUom(mixed $value): string
    {
        $text = strtolower($this->text($value));
        return trim(preg_replace('/[^a-z0-9_-]+/', '-', $text), '-') ?: '';
    }

    private function nullableText(mixed $value): ?string
    {
        $text = $this->text($value);
        return $text === '' ? null : $text;
    }

    private function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) ($value ?? '')));
    }

    private function integer(mixed $value): int
    {
        return (int) round((float) (is_numeric($value) ? $value : 0));
    }

    private function number(mixed $value): float
    {
        return round((float) (is_numeric($value) ? $value : 0), 2);
    }
}
