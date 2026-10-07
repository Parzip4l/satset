@extends('partials.layouts.master')

@section('title', 'Backup & Data Tiket')
@section('title-sub', 'Settings')
@section('pagetitle', 'Backup & Data Tiket')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1">Backup & Data Tiket</h4>
            <p class="text-muted mb-0">Kelola backup, restore, dan pembersihan seluruh transaksi tiket.</p>
        </div>
        <span class="badge {{ app()->environment('production') ? 'bg-danger' : 'bg-warning text-dark' }} fs-6 px-3 py-2">
            Environment: {{ strtoupper(app()->environment()) }}
        </span>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>Permintaan tidak dapat diproses.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3"><span class="rounded-circle bg-primary bg-opacity-10 text-primary p-3"><i class="bi bi-database fs-4"></i></span><div><div class="text-muted small">Total tiket aktif</div><div class="fs-3 fw-bold">{{ number_format($statistics['requests'] ?? 0) }}</div></div></div>
                    <div class="small text-muted">Termasuk {{ number_format($statistics['attachments'] ?? 0) }} lampiran, {{ number_format($statistics['comments'] ?? 0) }} komentar, dan {{ number_format($statistics['ticket_histories'] ?? 0) }} histori.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold"><i class="bi bi-cloud-arrow-down text-primary me-2"></i>Backup</h5>
                    <p class="text-muted small">Unduh ZIP berisi seluruh data tiket dan file lampirannya.</p>
                    <form method="POST" action="{{ route('settings.ticket-data.backup') }}">@csrf<button class="btn btn-primary w-100"><i class="bi bi-download me-1"></i> Download Backup</button></form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-danger"><i class="bi bi-trash3 me-2"></i>Clear Semua Tiket</h5>
                    <p class="text-muted small">Sistem otomatis membuat recovery backup sebelum data dihapus.</p>
                    <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#clearModal" @disabled(($statistics['requests'] ?? 0) === 0)>Clear Data Tiket</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white p-4"><h5 class="mb-1 fw-bold">Restore dari File</h5><p class="text-muted small mb-0">Restore akan membuat recovery backup, lalu mengganti seluruh data tiket saat ini dengan isi file.</p></div>
        <div class="card-body p-4">
            <form method="POST" action="{{ route('settings.ticket-data.restore') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                @csrf
                <div class="col-lg-4"><label class="form-label">File backup ZIP</label><input type="file" name="backup_file" class="form-control" accept=".zip,application/zip" required></div>
                <div class="col-lg-3"><label class="form-label">Password Anda</label><input type="password" name="password" class="form-control" autocomplete="current-password" required></div>
                <div class="col-lg-3"><label class="form-label">Ketik RESTORE TIKET</label><input type="text" name="confirmation" class="form-control" autocomplete="off" required></div>
                <div class="col-lg-2"><button class="btn btn-warning w-100" onclick="return confirm('Restore akan mengganti seluruh data tiket saat ini. Lanjutkan?')"><i class="bi bi-arrow-counterclockwise me-1"></i> Restore</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white p-4"><h5 class="mb-1 fw-bold">Recovery Backup di Server</h5><p class="text-muted small mb-0">Backup otomatis yang dibuat sebelum operasi clear atau restore.</p></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-4">File</th><th>Ukuran</th><th>Dibuat</th><th class="text-end pe-4">Aksi</th></tr></thead>
                <tbody>
                @forelse($savedBackups as $item)
                    <tr>
                        <td class="ps-4 fw-semibold">{{ $item['name'] }}</td>
                        <td>{{ number_format($item['size'] / 1024 / 1024, 2) }} MB</td>
                        <td>{{ \Carbon\Carbon::createFromTimestamp($item['updated_at'])->format('d M Y H:i') }}</td>
                        <td class="text-end pe-4"><a class="btn btn-sm btn-outline-primary" href="{{ route('settings.ticket-data.backups.download', $item['name']) }}"><i class="bi bi-download"></i> Download</a><button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#restoreSavedModal" data-filename="{{ $item['name'] }}"><i class="bi bi-arrow-counterclockwise"></i> Restore</button></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada recovery backup otomatis.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="clearModal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('settings.ticket-data.clear') }}" class="modal-content">@csrf @method('DELETE')<div class="modal-header"><h5 class="modal-title text-danger">Clear Semua Data Tiket</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-warning small">Seluruh tiket beserta approval, histori, komentar, dan lampiran akan dihapus. Recovery backup dibuat otomatis terlebih dahulu.</div><label class="form-label">Password Anda</label><input type="password" name="password" class="form-control mb-3" autocomplete="current-password" required><label class="form-label">Ketik HAPUS SEMUA TIKET</label><input type="text" name="confirmation" class="form-control" autocomplete="off" required></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger">Backup & Hapus Semua</button></div></form></div></div>

<div class="modal fade" id="restoreSavedModal" tabindex="-1"><div class="modal-dialog"><form method="POST" id="restoreSavedForm" class="modal-content">@csrf<div class="modal-header"><h5 class="modal-title">Restore Recovery Backup</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="small text-muted">File: <strong id="restoreSavedFilename"></strong></p><label class="form-label">Password Anda</label><input type="password" name="password" class="form-control mb-3" autocomplete="current-password" required><label class="form-label">Ketik RESTORE TIKET</label><input type="text" name="confirmation" class="form-control" autocomplete="off" required></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-warning">Restore</button></div></form></div></div>
@endsection

@section('script')
<script>
document.getElementById('restoreSavedModal')?.addEventListener('show.bs.modal', function (event) {
    const filename = event.relatedTarget.getAttribute('data-filename');
    document.getElementById('restoreSavedFilename').textContent = filename;
    document.getElementById('restoreSavedForm').action = @json(url('/settings/ticket-data/backups')) + '/' + encodeURIComponent(filename) + '/restore';
});
</script>
@endsection
