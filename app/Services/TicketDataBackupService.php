<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class TicketDataBackupService
{
    public const FORMAT = 'satset-ticket-backup';

    public const VERSION = 1;

    /** @var array<string, string> */
    private const TABLE_FOREIGN_KEYS = [
        'requests' => 'id',
        'assignments' => 'request_id',
        'approvals' => 'request_id',
        'approval_audits' => 'ticket_id',
        'status_logs' => 'request_id',
        'attachments' => 'request_id',
        'comments' => 'request_id',
        'watchers' => 'request_id',
        'ticket_histories' => 'ticket_id',
    ];

    public function statistics(): array
    {
        return collect(array_keys(self::TABLE_FOREIGN_KEYS))
            ->filter(fn (string $table) => Schema::hasTable($table))
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
            ->all();
    }

    public function createArchive(string $destination): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZIP belum tersedia di server.');
        }

        $directory = dirname($destination);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder backup tidak dapat dibuat.');
        }

        $tables = [];
        foreach (array_keys(self::TABLE_FOREIGN_KEYS) as $table) {
            if (Schema::hasTable($table)) {
                $tables[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            }
        }

        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'ticket_count' => count($tables['requests'] ?? []),
            'tables' => array_map('count', $tables),
        ];

        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File ZIP backup tidak dapat dibuat.');
        }

        try {
            $zip->addFromString('manifest.json', $this->encodeJson($manifest));
            $zip->addFromString('data.json', $this->encodeJson(['tables' => $tables]));

            foreach ($tables['attachments'] ?? [] as $attachment) {
                $path = $this->safeRelativePath((string) ($attachment['file_path'] ?? ''));
                if ($path === null || ! Storage::disk('public')->exists($path)) {
                    continue;
                }

                $absolutePath = Storage::disk('public')->path($path);
                if (is_file($absolutePath)) {
                    $zip->addFile($absolutePath, 'files/'.$path);
                }
            }
        } finally {
            $zip->close();
        }

        return $manifest;
    }

    public function clearAll(): int
    {
        $attachmentPaths = $this->attachmentPaths();
        $count = Schema::hasTable('requests') ? DB::table('requests')->count() : 0;

        DB::transaction(function (): void {
            $this->deleteTicketRows();
        });

        foreach ($attachmentPaths as $path) {
            Storage::disk('public')->delete($path);
        }

        return $count;
    }

    public function inspectArchive(string $archive): array
    {
        $zip = $this->openArchive($archive);

        try {
            $manifest = $this->decodeEntry($zip, 'manifest.json');
            $payload = $this->decodeEntry($zip, 'data.json');
        } finally {
            $zip->close();
        }

        if (($manifest['format'] ?? null) !== self::FORMAT || (int) ($manifest['version'] ?? 0) !== self::VERSION) {
            throw new RuntimeException('Format atau versi file backup tidak didukung.');
        }

        if (! isset($payload['tables']['requests']) || ! is_array($payload['tables']['requests'])) {
            throw new RuntimeException('Backup tidak memiliki data tiket yang valid.');
        }

        return compact('manifest', 'payload');
    }

    public function restoreArchive(string $archive): int
    {
        ['payload' => $payload] = $this->inspectArchive($archive);
        $tables = $payload['tables'];
        $oldAttachmentPaths = $this->attachmentPaths();

        DB::transaction(function () use ($tables): void {
            $this->deleteTicketRows();

            foreach (array_keys(self::TABLE_FOREIGN_KEYS) as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $rows = $tables[$table] ?? [];
                if (! is_array($rows)) {
                    throw new RuntimeException("Data tabel {$table} tidak valid.");
                }

                $columns = Schema::getColumnListing($table);
                $cleanRows = collect($rows)->map(function ($row) use ($columns, $table) {
                    if (! is_array($row)) {
                        throw new RuntimeException("Baris data tabel {$table} tidak valid.");
                    }

                    return array_intersect_key($row, array_flip($columns));
                })->values();

                foreach ($cleanRows->chunk(250) as $chunk) {
                    $this->insertRows($table, $chunk->all());
                }
            }
        });

        foreach ($oldAttachmentPaths as $path) {
            Storage::disk('public')->delete($path);
        }
        $this->restoreFiles($archive, $tables['attachments'] ?? []);
        $this->synchronizeSequences();

        return count($tables['requests']);
    }

    private function deleteTicketRows(): void
    {
        foreach (array_reverse(array_keys(self::TABLE_FOREIGN_KEYS)) as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    private function attachmentPaths(): array
    {
        if (! Schema::hasTable('attachments')) {
            return [];
        }

        return DB::table('attachments')->pluck('file_path')
            ->map(fn ($path) => $this->safeRelativePath((string) $path))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function restoreFiles(string $archive, array $attachments): void
    {
        $zip = $this->openArchive($archive);

        try {
            foreach ($attachments as $attachment) {
                $path = $this->safeRelativePath((string) ($attachment['file_path'] ?? ''));
                if ($path === null) {
                    continue;
                }

                $stream = $zip->getStream('files/'.$path);
                if ($stream === false) {
                    continue;
                }

                try {
                    if (! Storage::disk('public')->put($path, $stream)) {
                        throw new RuntimeException("Lampiran {$path} gagal dipulihkan.");
                    }
                } finally {
                    fclose($stream);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function openArchive(string $archive): ZipArchive
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZIP belum tersedia di server.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('File backup bukan ZIP yang valid.');
        }

        if ($zip->numFiles > 10000) {
            $zip->close();
            throw new RuntimeException('File backup memiliki terlalu banyak entri.');
        }

        $totalSize = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            $size = (int) ($stat['size'] ?? 0);

            if (
                $name === ''
                || str_starts_with($name, '/')
                || Str::contains($name, ['../', '/..', "\0"])
                || (! in_array($name, ['manifest.json', 'data.json'], true) && ! str_starts_with($name, 'files/'))
            ) {
                $zip->close();
                throw new RuntimeException('File backup memiliki path yang tidak aman.');
            }

            if ($size > 536870912 || ($totalSize += $size) > 2147483648) {
                $zip->close();
                throw new RuntimeException('Isi file backup terlalu besar untuk dipulihkan.');
            }
        }

        return $zip;
    }

    private function decodeEntry(ZipArchive $zip, string $name): array
    {
        $stat = $zip->statName($name);
        if ($stat === false || ($stat['size'] ?? 0) > 268435456) {
            throw new RuntimeException("{$name} tidak ditemukan atau terlalu besar.");
        }

        $content = $zip->getFromName($name);
        if ($content === false) {
            throw new RuntimeException("{$name} tidak dapat dibaca.");
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("{$name} bukan JSON yang valid.");
        }

        return $decoded;
    }

    private function safeRelativePath(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || Str::contains($path, ['../', '/..', "\0"])) {
            return null;
        }

        return $path;
    }

    private function encodeJson(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $json;
    }

    private function insertRows(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("SET IDENTITY_INSERT [{$table}] ON");
            try {
                DB::table($table)->insert($rows);
            } finally {
                DB::statement("SET IDENTITY_INSERT [{$table}] OFF");
            }

            return;
        }

        DB::table($table)->insert($rows);
    }

    private function synchronizeSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(self::TABLE_FOREIGN_KEYS) as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM {$table}");
            }
        }
    }
}
