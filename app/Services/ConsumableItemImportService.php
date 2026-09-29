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
    public static function templateFilename(): string
    {
        return 'template-master-barang.xlsx';
    }

    public static function templateRows(): array
    {
        return [
            ['No', 'Kategori', 'Kode Barang', 'Nama Barang', 'Satuan Gudang Besar', 'Jumlah Konversi', 'UOM Gudang Kecil', 'Binloc', 'Harga', 'Stok Minimum', 'Saldo Awal', 'Januari', null, 'Februari', null, 'Maret', null, 'April', null, 'Mei', null, 'Juni', null, 'Juli', null, 'Agustus', null, 'September', null, 'Oktober', null, 'November', null, 'Desember', null, 'SOH/Saldo Berjalan', 'Stok Gudang Kecil', 'Total Harga', 'Keterangan', 'Referensi PO Terakhir'],
            [null, null, null, null, null, null, null, null, null, null, null, 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', null, null, null, null, null],
            [1, 'ATK', 'ATK-001', 'Pulpen Hitam', 'Box', 12, 'Pcs', 'GA-ATK-01', 3500, 24, 120, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 120, 12, 420000, null, null],
            [2, 'RTK', 'RTK-001', 'Tisu Toilet', 'Dus', 48, 'Roll', 'GA-RTK-01', 6500, 96, 240, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 240, 24, 1560000, null, null],
        ];
    }

    public static function writeTemplateXlsx(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Template XLSX tidak bisa dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', self::xlsxContentTypes());
        $zip->addFromString('_rels/.rels', self::xlsxRootRels());
        $zip->addFromString('docProps/app.xml', self::xlsxAppProps());
        $zip->addFromString('docProps/core.xml', self::xlsxCoreProps());
        $zip->addFromString('xl/workbook.xml', self::xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::xlsxWorkbookRels());
        $zip->addFromString('xl/styles.xml', self::xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::xlsxCatalogMasterSheet());
        $zip->close();
    }

    private static function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function xlsxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets><sheet name="Catalog Master" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9EAF7"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function xlsxAppProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Satset</Application></Properties>';
    }

    private static function xlsxCoreProps(): string
    {
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Template Master Barang</dc:title><dc:creator>Satset</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private static function xlsxCatalogMasterSheet(): string
    {
        $rows = self::templateRows();
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:AN5"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols><col min="1" max="1" width="6" customWidth="1"/><col min="2" max="3" width="16" customWidth="1"/><col min="4" max="4" width="36" customWidth="1"/><col min="5" max="7" width="18" customWidth="1"/><col min="8" max="10" width="14" customWidth="1"/><col min="11" max="37" width="12" customWidth="1"/><col min="38" max="40" width="22" customWidth="1"/></cols>'
            . '<sheetData>';

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $xml .= '<row r="' . $rowNumber . '">';
            foreach ($row as $columnIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $reference = self::xlsxColumnName($columnIndex + 1) . $rowNumber;
                $style = $rowNumber === 2 ? 1 : ($rowNumber === 3 ? 3 : 2);
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $reference . '" s="' . $style . '"><v>' . $value . '</v></c>';
                    continue;
                }

                $xml .= '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t>' . self::xmlEscape((string) $value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData><autoFilter ref="A2:AN5"/></worksheet>';
    }

    private static function xlsxColumnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private static function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

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
            'xlsx' => $this->readXlsx($path, $options['sheet_name'] ?? ['Catalog Master', 'Catalog Master mini']),
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
                if ($record['big_stock'] !== null && in_array($stockMode, ['big', 'both'], true)) {
                    $summary['stock_adjusted'] += $this->overwriteStock($fresh, 'big_warehouse', $record['big_stock'], $userId);
                    $fresh = $fresh->fresh();
                }

                $smallStock = $record['small_stock'] ?? $record['big_stock'];
                if ($smallStock !== null && in_array($stockMode, ['small', 'both'], true)) {
                    $summary['stock_adjusted'] += $this->overwriteStock($fresh, 'small_warehouse', $smallStock, $userId);
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
            $bigStock = $this->nullableInteger($row[$columns['stock'] ?? -1] ?? $row[$columns['opening_stock'] ?? -1] ?? null);
            $smallStock = $this->nullableInteger($row[$columns['small_stock'] ?? -1] ?? null);

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
                'big_stock' => $bigStock === null ? null : max(0, $bigStock),
                'small_stock' => $smallStock === null ? null : max(0, $smallStock),
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
                'satuan', 'unit', 'large_uom', 'satuan_gudang_besar', 'uom_gudang_besar' => $columns['large_uom'] ??= $index,
                'binloc', 'location', 'lokasi' => $columns['location'] = $index,
                'harga', 'unit_price', 'value' => $columns['unit_price'] = $index,
                'stok_minimum', 'minimum_stock' => $columns['minimum_stock'] = $index,
                'saldo_awal', 'opening_stock' => $columns['opening_stock'] = $index,
                'soh_saldo_berjalan', 'soh', 'current_stock', 'stok_gudang_besar', 'gudang_besar' => $columns['stock'] = $index,
                'stok_gudang_kecil', 'gudang_kecil', 'small_stock' => $columns['small_stock'] = $index,
                'conversion_qty', 'jumlah_konversi' => $columns['conversion_qty'] = $index,
                'small_uom', 'satuan_kecil', 'satuan_disarankan', 'uom_gudang_kecil', 'satuan_gudang_kecil' => $columns['small_uom'] = $index,
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

    private function readXlsx(string $path, string|array $preferredSheet): array
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

    private function resolveSheetPath(ZipArchive $zip, string|array $preferredSheet): string
    {
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $relations = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        if (!$workbook || !$relations) {
            throw new RuntimeException('Struktur workbook XLSX tidak valid.');
        }

        $preferredSheets = array_map('strtolower', (array) $preferredSheet);
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
            if (in_array(strtolower((string) $sheet['name']), $preferredSheets, true)) {
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

    private function nullableInteger(mixed $value): ?int
    {
        $text = $this->text($value);
        if ($text === '') {
            return null;
        }

        return is_numeric($text) ? (int) round((float) $text) : null;
    }

    private function number(mixed $value): float
    {
        return round((float) (is_numeric($value) ? $value : 0), 2);
    }
}
