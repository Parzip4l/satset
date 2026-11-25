<?php

namespace App\Services\It;

use App\Support\ItAssetAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

final class AssetWorkbook
{
    public const MAX_ROWS = 100;

    public const COLUMNS = [
        'Kode aset' => 'asset_tag', 'Nama aset' => 'name', 'Tipe aset' => 'type_name',
        'Lokasi' => 'location_name', 'Status' => 'status', 'Email pemegang' => 'employee_email',
        'Merek / penerbit' => 'brand', 'Model / versi' => 'model', 'Nomor seri / referensi' => 'serial_number',
        'Tanggal perolehan' => 'acquired_on', 'Harga perolehan' => 'purchase_price',
        'Garansi / lisensi berakhir' => 'expires_on', 'Catatan' => 'notes',
    ];

    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('File XLSX tidak dapat dibaca. Gunakan template Aset IT.');
        }
        try {
            $size = 0;
            if ($zip->numFiles > 2000) {
                $this->fail('Workbook terlalu besar. Maksimal 100 aset per file.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $size += $entry['size'];
                if ($size > 20 * 1024 * 1024 || str_ends_with(strtolower($entry['name']), 'vbaproject.bin')) {
                    $this->fail('Workbook terlalu besar atau mengandung macro. Gunakan template XLSX tanpa macro.');
                }
            }
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $properties = $workbook->xpath('/*[local-name()="workbook"]/*[local-name()="workbookPr"]')[0] ?? null;
            $date1904 = in_array((string) ($properties['date1904'] ?? ''), ['1', 'true'], true);
            $relations = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $target = null;
            foreach ($workbook->xpath('/*[local-name()="workbook"]/*[local-name()="sheets"]/*[local-name()="sheet"]') as $sheet) {
                if (strcasecmp((string) $sheet['name'], 'Aset IT') !== 0) {
                    continue;
                }
                $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($relations->xpath('/*[local-name()="Relationships"]/*[local-name()="Relationship"]') as $relation) {
                    if ((string) $relation['Id'] === $id && (string) $relation['TargetMode'] !== 'External') {
                        $target = (string) $relation['Target'];
                    }
                }
            }
            if (! $target) {
                $this->fail('Sheet "Aset IT" tidak ditemukan. Jangan mengubah nama sheet pada template.');
            }
            $parts = [];
            foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target) as $part) {
                if ($part === '..') {
                    array_pop($parts);
                } elseif ($part !== '.' && $part !== '') {
                    $parts[] = $part;
                }
            }
            $sheetPath = implode('/', $parts);
            if (! preg_match('~^xl/worksheets/[^/]+\.xml$~', $sheetPath)) {
                $this->fail('Referensi sheet XLSX tidak valid.');
            }
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->xpath('/*[local-name()="sst"]/*[local-name()="si"]') as $item) {
                    $strings[] = $this->text($item);
                }
            }
            $rows = [];
            foreach ($this->xml($zip, $sheetPath)->xpath('/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $number = (int) $row['r'];
                if ($number < 1 || $number > 1048576 || isset($rows[$number])) {
                    $this->fail('Nomor baris XLSX tidak valid.');
                }
                $cells = [];
                foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                    if (! preg_match('/^([A-Z]{1,3})([1-9][0-9]*)$/', (string) $cell['r'], $match) || (int) $match[2] !== $number) {
                        $this->fail('Referensi sel XLSX tidak valid.');
                    }
                    $column = 0;
                    foreach (str_split($match[1]) as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    if ($column > count(self::COLUMNS) + 100 || isset($cells[$column - 1])) {
                        $this->fail('Kolom terlalu banyak atau berulang. Maksimal 100 kolom spesifikasi.');
                    }
                    if ($cell->xpath('./*[local-name()="f"]') || (string) $cell['t'] === 'e') {
                        $this->fail('Baris '.$number.': gunakan nilai biasa, bukan formula atau sel error.');
                    }
                    $type = (string) $cell['t'];
                    $raw = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');
                    if ($type === 's' && (! ctype_digit($raw) || ! array_key_exists((int) $raw, $strings))) {
                        $this->fail('Referensi teks XLSX tidak valid.');
                    }
                    $value = match ($type) {
                        's' => $strings[(int) $raw],
                        'inlineStr' => $this->text($cell),
                        default => $raw,
                    };
                    if (mb_strlen($value) > 10000) {
                        $this->fail('Baris '.$number.': isi sel terlalu panjang.');
                    }
                    $cells[$column - 1] = trim($value);
                }
                if (array_filter($cells, fn ($value) => $value !== '')) {
                    $rows[$number] = $cells;
                }
                if (count($rows) > self::MAX_ROWS + 1) {
                    $this->fail('Maksimal 100 aset per file. Pecah data menjadi beberapa file.');
                }
            }

            return $this->map($rows, $date1904);
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $data = $zip->getFromName($name);
        if ($data === false || strlen($data) > 5 * 1024 * 1024 || stripos($data, '<!DOCTYPE') !== false || stripos($data, '<!ENTITY') !== false) {
            $this->fail('Struktur XLSX tidak valid. Unduh ulang template.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($data, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                $this->fail('Struktur XML dalam XLSX tidak valid.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(SimpleXMLElement $node): string
    {
        return implode('', array_map(fn ($text) => (string) $text, $node->xpath('.//*[local-name()="t"]')));
    }

    private function map(array $rows, bool $date1904): array
    {
        $header = $rows[1] ?? [];
        $known = array_change_key_case(self::COLUMNS);
        $columns = $names = $specifications = [];
        foreach ($header as $index => $label) {
            $label = Str::squish($label);
            if ($label === '') {
                continue;
            }
            $key = Str::lower($label);
            if (isset($names[$key])) {
                $this->fail('Judul kolom berulang: '.$label.'.');
            }
            $names[$key] = true;
            if (isset($known[$key])) {
                $columns[$index] = ['field' => $known[$key]];
            } elseif (preg_match('/^Spesifikasi:\s*(.+)$/iu', $label, $match) && mb_strlen($match[1]) <= 100) {
                $name = Str::squish($match[1]);
                if ($name === '') {
                    $this->fail('Isi nama spesifikasi setelah "Spesifikasi:".');
                }
                if (isset($specifications[Str::lower($name)])) {
                    $this->fail('Nama kolom spesifikasi berulang: '.$name.'.');
                }
                $specifications[Str::lower($name)] = true;
                $columns[$index] = ['specification' => $name];
            } else {
                $this->fail('Kolom "'.$label.'" tidak dikenali. Gunakan judul pada template atau "Spesifikasi: Nama".');
            }
        }
        foreach (['asset_tag', 'name', 'type_name', 'location_name', 'status'] as $field) {
            if (! in_array($field, array_column($columns, 'field'), true)) {
                $this->fail('Kolom wajib belum ada: '.array_search($field, self::COLUMNS).'.');
            }
        }
        unset($rows[1]);
        if (! $rows) {
            $this->fail('Belum ada data aset pada sheet "Aset IT". Isi mulai baris 2.');
        }
        $output = [];
        foreach ($rows as $number => $cells) {
            $input = array_fill_keys(array_values(self::COLUMNS), null);
            $input['specifications'] = [];
            foreach ($cells as $index => $value) {
                if ($value !== '' && ! isset($columns[$index])) {
                    $this->fail('Baris '.$number.': ada isi pada kolom tanpa judul.');
                }
            }
            foreach ($columns as $index => $column) {
                $value = $cells[$index] ?? '';
                if (isset($column['specification'])) {
                    if ($value !== '') {
                        $input['specifications'][] = ['name' => $column['specification'], 'value' => $value];
                    }
                } else {
                    $input[$column['field']] = $value === '' ? null : $value;
                }
            }
            foreach (['acquired_on', 'expires_on'] as $field) {
                $input[$field] = $this->date($input[$field], $date1904, $number);
            }
            $input['purchase_price'] = $this->number($input['purchase_price']);
            $status = Str::lower(Str::squish($input['status'] ?? ''));
            foreach (ItAssetAccess::STATUSES as $key => $definition) {
                if ($status === Str::lower($definition['label']) || $status === $key) {
                    $input['status'] = $key;
                }
            }
            $output[$number] = $input;
        }

        return $output;
    }

    private function date(?string $value, bool $date1904, int $row): ?string
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (is_numeric($value) && (float) $value === floor((float) $value) && (float) $value >= 0 && (float) $value <= 2957000) {
            $days = (int) $value;
            if (! $date1904 && $days === 60) {
                $this->fail('Baris '.$row.': tanggal Excel 29 Februari 1900 tidak valid.');
            }

            return CarbonImmutable::parse($date1904 ? '1904-01-01' : ($days < 60 ? '1899-12-31' : '1899-12-30'))->addDays($days)->toDateString();
        }
        $this->fail('Baris '.$row.': tanggal harus berupa tanggal Excel atau YYYY-MM-DD.');
    }

    private function number(?string $value): ?string
    {
        // Excel may store ordinary numeric prices using scientific notation.
        if ($value === null || ! preg_match('/^([+-]?)(\d+)(?:\.(\d*))?[eE]([+-]?\d{1,2})$/', $value, $match)) {
            return $value;
        }
        $digits = $match[2].($match[3] ?? '');
        $position = strlen($match[2]) + (int) $match[4];
        if ($position <= 0) {
            $expanded = '0.'.str_repeat('0', -$position).$digits;
        } elseif ($position >= strlen($digits)) {
            $expanded = $digits.str_repeat('0', $position - strlen($digits));
        } else {
            $expanded = substr($digits, 0, $position).'.'.substr($digits, $position);
        }
        if (str_contains($expanded, '.')) {
            $expanded = rtrim(rtrim($expanded, '0'), '.');
        }

        return ($match[1] === '-' ? '-' : '').$expanded;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
