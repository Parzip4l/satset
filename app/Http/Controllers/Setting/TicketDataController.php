<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Services\TicketDataBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class TicketDataController extends Controller
{
    public function index(TicketDataBackupService $backup)
    {
        $statistics = $backup->statistics();
        $savedBackups = collect(Storage::disk('local')->files('ticket-backups'))
            ->filter(fn (string $file) => str_ends_with($file, '.zip'))
            ->map(fn (string $file) => [
                'name' => basename($file),
                'size' => Storage::disk('local')->size($file),
                'updated_at' => Storage::disk('local')->lastModified($file),
            ])
            ->sortByDesc('updated_at')
            ->take(10)
            ->values();

        return view('setting.ticket-data', compact('statistics', 'savedBackups'));
    }

    public function backup(TicketDataBackupService $backup)
    {
        $filename = $this->backupFilename('manual');
        $temporary = tempnam(sys_get_temp_dir(), 'satset-ticket-backup-');

        try {
            $backup->createArchive($temporary);
            Log::notice('Admin mengunduh backup data tiket.', ['user_id' => auth()->id(), 'file' => $filename]);

            return response()->download($temporary, $filename, ['Content-Type' => 'application/zip'])
                ->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            @unlink($temporary);
            report($exception);

            return back()->with('error', 'Backup gagal dibuat: '.$exception->getMessage());
        }
    }

    public function clear(Request $request, TicketDataBackupService $backup)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['HAPUS SEMUA TIKET'])],
        ], [
            'password.current_password' => 'Password tidak sesuai.',
            'confirmation.in' => 'Ketik HAPUS SEMUA TIKET dengan tepat.',
        ]);

        $filename = $this->backupFilename('sebelum-hapus');
        $relativePath = 'ticket-backups/'.$filename;
        $absolutePath = Storage::disk('local')->path($relativePath);

        try {
            $backup->createArchive($absolutePath);
            $count = $backup->clearAll();
            Log::warning('Admin menghapus seluruh data tiket.', [
                'user_id' => $request->user()->id,
                'ticket_count' => $count,
                'recovery_backup' => $relativePath,
            ]);

            return back()->with('success', "{$count} tiket berhasil dihapus. Recovery backup otomatis: {$filename}");
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Data tiket gagal dihapus: '.$exception->getMessage());
        }
    }

    public function restore(Request $request, TicketDataBackupService $backup)
    {
        $validated = $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['RESTORE TIKET'])],
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:524288'],
        ], [
            'password.current_password' => 'Password tidak sesuai.',
            'confirmation.in' => 'Ketik RESTORE TIKET dengan tepat.',
        ]);

        return $this->performRestore($request, $backup, $validated['backup_file']->getRealPath(), $validated['backup_file']->getClientOriginalName());
    }

    public function restoreSaved(Request $request, string $filename, TicketDataBackupService $backup)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['RESTORE TIKET'])],
        ]);

        abort_unless((bool) preg_match('/\A[a-zA-Z0-9._-]+\.zip\z/', $filename), 404);
        $relativePath = 'ticket-backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($relativePath), 404);

        return $this->performRestore($request, $backup, Storage::disk('local')->path($relativePath), $filename);
    }

    public function downloadSaved(string $filename)
    {
        abort_unless((bool) preg_match('/\A[a-zA-Z0-9._-]+\.zip\z/', $filename), 404);
        $relativePath = 'ticket-backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($relativePath), 404);

        return Storage::disk('local')->download($relativePath, $filename, ['Content-Type' => 'application/zip']);
    }

    private function performRestore(Request $request, TicketDataBackupService $backup, string $path, string $source)
    {
        try {
            $recoveryFilename = $this->backupFilename('sebelum-restore');
            $recoveryPath = 'ticket-backups/'.$recoveryFilename;
            $backup->createArchive(Storage::disk('local')->path($recoveryPath));
            $count = $backup->restoreArchive($path);
            Log::warning('Admin memulihkan backup data tiket.', [
                'user_id' => $request->user()->id,
                'ticket_count' => $count,
                'source' => $source,
                'recovery_backup' => $recoveryPath,
            ]);

            return back()->with('success', "Restore selesai. {$count} tiket berhasil dipulihkan. Backup kondisi sebelumnya: {$recoveryFilename}");
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Restore gagal: '.$exception->getMessage());
        }
    }

    private function backupFilename(string $label): string
    {
        return 'tiket-'.$label.'-'.now()->format('Ymd-His-v').'.zip';
    }
}
