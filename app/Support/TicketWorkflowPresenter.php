<?php

namespace App\Support;

class TicketWorkflowPresenter
{
    /**
     * @return array{label: string, description: string, step: int, total: int, percentage: int, color: string, icon: string}
     */
    public static function make(array $payload, ?string $ticketStatus = null): array
    {
        $requestType = (string) data_get($payload, 'request_type', 'general');
        $workflowStatus = strtoupper((string) data_get($payload, 'workflow_status', ''));

        if ($workflowStatus === '') {
            return self::general($ticketStatus);
        }

        if (in_array($workflowStatus, ['CANCELLED', 'REJECTED', 'REJECTED_BY_MANAGER', 'REJECTED_BY_BUM'], true)) {
            return self::rejected($workflowStatus);
        }

        return match ($requestType) {
            'consumption' => self::consumption($workflowStatus),
            'atk_rtk' => self::atkRtk($workflowStatus, $payload),
            'ga_request_finding' => self::gaRequestFinding($workflowStatus),
            default => self::general($ticketStatus, $workflowStatus),
        };
    }

    private static function consumption(string $status): array
    {
        [$label, $description, $step, $color, $icon] = match ($status) {
            'SUBMITTED', 'WAITING_MANAGER_APPROVAL' => ['Menunggu approval atasan', 'Pengajuan sedang diperiksa oleh atasan pemohon.', 1, 'warning', 'bi-person-check'],
            'WAITING_BUM_REVIEW', 'WAITING_BUM_VERIFICATION' => ['Verifikasi Bagian Umum', 'Tim GA sedang memeriksa kelengkapan permintaan.', 2, 'info', 'bi-clipboard-check'],
            'APPROVED_BY_BUM' => ['Disetujui Bagian Umum', 'Permintaan siap diteruskan ke proses pemesanan.', 3, 'primary', 'bi-check2-circle'],
            'ORDERED_TO_VENDOR' => ['Dipesan ke vendor', 'Pesanan konsumsi sedang diproses oleh vendor.', 4, 'primary', 'bi-bag-check'],
            'RECEIVED' => ['Pesanan telah diterima', 'Konsumsi sudah diterima dan menunggu administrasi.', 5, 'info', 'bi-box-seam'],
            'WAITING_ACCOUNTABILITY' => ['Menunggu pertanggungjawaban', 'Pemohon perlu mengunggah dokumen kegiatan.', 6, 'warning', 'bi-file-earmark-arrow-up'],
            'ACCOUNTABILITY_SUBMITTED', 'REPORTED' => ['Verifikasi pertanggungjawaban', 'Dokumen sudah dikirim dan sedang diverifikasi.', 6, 'info', 'bi-file-earmark-check'],
            'CLOSED' => ['Selesai', 'Seluruh proses konsumsi telah diselesaikan.', 7, 'success', 'bi-check-circle-fill'],
            default => [self::humanize($status), 'Proses permintaan konsumsi sedang berjalan.', 1, 'secondary', 'bi-hourglass-split'],
        };

        return self::result($label, $description, $step, 7, $color, $icon);
    }

    private static function atkRtk(string $status, array $payload): array
    {
        $estimatedAmount = (float) data_get($payload, 'total_estimated_amount', 0);
        $approvalThreshold = data_get($payload, 'approval_threshold');
        if ($approvalThreshold === null) {
            $approvalThreshold = app()->bound('config')
                ? config('bum.atk_rtk_manager_approval_threshold', 100000)
                : 100000;
        }
        $approvalThreshold = (float) $approvalThreshold;
        $requiresManagerApproval = $status === 'WAITING_MANAGER_APPROVAL'
            || ($approvalThreshold > 0 && $estimatedAmount >= $approvalThreshold);
        $total = $requiresManagerApproval ? 5 : 4;
        $reviewStep = $requiresManagerApproval ? 2 : 1;
        $stockStep = $requiresManagerApproval ? 3 : 2;
        $handoverReadyStep = $requiresManagerApproval ? 4 : 3;

        [$label, $description, $step, $color, $icon] = match ($status) {
            'SUBMITTED', 'WAITING_MANAGER_APPROVAL' => ['Menunggu approval atasan', 'Pengajuan ATK/RTK sedang diperiksa oleh atasan.', 1, 'warning', 'bi-person-check'],
            'WAITING_BUM_REVIEW', 'WAITING_BUM_VERIFICATION' => ['Review Bagian Umum', 'Tim GA sedang memeriksa kebutuhan dan ketersediaan.', $reviewStep, 'info', 'bi-clipboard-check'],
            'STOCK_CHECKED' => ['Stok sudah diperiksa', 'Ketersediaan barang sudah dikonfirmasi oleh gudang.', $stockStep, 'info', 'bi-boxes'],
            'WAITING_PROCUREMENT' => ['Dalam proses pengadaan', 'Barang belum tersedia dan sedang diproses pengadaannya.', $stockStep, 'warning', 'bi-cart-check'],
            'READY_TO_HANDOVER' => ['Siap diserahkan', 'Barang sudah siap diambil atau diserahkan kepada pemohon.', $handoverReadyStep, 'primary', 'bi-box-arrow-up-right'],
            'HANDED_OVER', 'CLOSED' => ['Barang telah diserahkan', 'Proses ATK/RTK telah selesai.', $total, 'success', 'bi-check-circle-fill'],
            default => [self::humanize($status), 'Proses permintaan ATK/RTK sedang berjalan.', 1, 'secondary', 'bi-hourglass-split'],
        };

        return self::result($label, $description, $step, $total, $color, $icon);
    }

    private static function gaRequestFinding(string $status): array
    {
        [$label, $description, $step, $color, $icon] = match ($status) {
            'SUBMITTED', 'WAITING_BUM_REVIEW', 'WAITING_BUM_VERIFICATION' => ['Menunggu review tim GA', 'Laporan sudah diterima dan menunggu arahan tindak lanjut.', 1, 'warning', 'bi-inbox'],
            'IN_PROGRESS' => ['Sedang ditindaklanjuti', 'Petugas GA sedang menangani permintaan atau temuan.', 2, 'primary', 'bi-tools'],
            'CLOSED' => ['Selesai ditindaklanjuti', 'Evidence telah disimpan dan laporan sudah ditutup.', 3, 'success', 'bi-check-circle-fill'],
            default => [self::humanize($status), 'Proses tindak lanjut tim GA sedang berjalan.', 1, 'secondary', 'bi-hourglass-split'],
        };

        return self::result($label, $description, $step, 3, $color, $icon);
    }

    private static function rejected(string $status): array
    {
        [$label, $description] = match ($status) {
            'REJECTED_BY_MANAGER' => ['Ditolak atasan', 'Pengajuan tidak disetujui oleh atasan pemohon.'],
            'REJECTED_BY_BUM' => ['Ditolak Bagian Umum', 'Pengajuan tidak disetujui pada verifikasi Bagian Umum.'],
            'CANCELLED' => ['Dibatalkan', 'Proses tiket dihentikan atau dibatalkan.'],
            default => ['Ditolak', 'Pengajuan tidak dapat dilanjutkan.'],
        };

        return self::result($label, $description, 1, 1, 'danger', 'bi-x-circle-fill');
    }

    private static function general(?string $ticketStatus, ?string $workflowStatus = null): array
    {
        $status = strtolower((string) ($ticketStatus ?: $workflowStatus ?: 'Open'));

        return match ($status) {
            'closed', 'resolved' => self::result('Selesai', 'Tiket telah diselesaikan.', 3, 3, 'success', 'bi-check-circle-fill'),
            'in progress', 'in_progress' => self::result('Sedang diproses', 'Tiket sedang ditangani oleh petugas.', 2, 3, 'primary', 'bi-hourglass-split'),
            'rejected', 'cancelled' => self::result('Dibatalkan', 'Tiket tidak dapat dilanjutkan.', 1, 1, 'danger', 'bi-x-circle-fill'),
            default => self::result('Menunggu tindak lanjut', 'Tiket sudah diterima dan menunggu diproses.', 1, 3, 'warning', 'bi-inbox'),
        };
    }

    private static function result(string $label, string $description, int $step, int $total, string $color, string $icon): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'step' => $step,
            'total' => $total,
            'percentage' => (int) round(($step / max(1, $total)) * 100),
            'color' => $color,
            'icon' => $icon,
        ];
    }

    private static function humanize(string $status): string
    {
        return str($status)->replace('_', ' ')->lower()->title()->toString();
    }
}
