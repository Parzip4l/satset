<?php

namespace App\Support;

use App\Models\Master\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class Sppd
{
    public const TYPE = 'sppd';

    public const CATEGORIES = [
        'domestic_under_60' => 'Perjalanan Dinas Dalam Negeri <60 km',
        'domestic_over_60' => 'Perjalanan Dinas Dalam Negeri >60 km',
        'international' => 'Perjalanan Dinas Luar Negeri',
    ];

    // The keys follow the source Excel rows and its two total formulas.
    public const COSTS = [
        30 => ['1', 'Penginapan', 'Supported by GA'],
        31 => ['2', 'Transportasi', 'Supported by GA'],
        32 => ['', 'a. Tiket Pesawat', ''],
        33 => ['', 'b. Ke / Dari Terminal/Lokasi', ''],
        34 => ['3', 'Uang Makan', ''],
        35 => ['3', 'Uang Saku', ''],
        36 => ['4', 'Uang Makan', ''],
        38 => ['', 'a. Bantuan Perlengkapan Musim Dingin', ''],
    ];

    public const EMPLOYEE_COST_ROWS = [33, 34, 35, 36];

    public const STAGES = [
        0 => ['Diajukan Oleh', 'Pelaksana Perjalanan Dinas', 'requester'],
        1 => ['Mengetahui', 'Atasan Langsung', 'direct_manager'],
        2 => ['Mengetahui', 'Kepala Divisi SDM', 'hr_division_head'],
        3 => ['Disetujui Oleh', 'Direktur Keuangan dan Dukungan Bisnis', 'finance_director'],
    ];

    public static function is(Ticket $ticket): bool
    {
        return data_get($ticket->payload, 'request_type') === self::TYPE;
    }

    public static function excludeFrom(Builder $query): Builder
    {
        return $query->where(fn (Builder $tickets) => $tickets
            ->whereNull('payload->request_type')
            ->orWhere('payload->request_type', '<>', self::TYPE));
    }

    public static function label(Ticket $ticket): string
    {
        return match (data_get($ticket->payload, 'workflow_status')) {
            'WAITING_MANAGER_APPROVAL' => 'Menunggu Atasan Langsung',
            'WAITING_HR_APPROVAL' => 'Menunggu Kepala Divisi SDM',
            'WAITING_DIRECTOR_APPROVAL' => 'Menunggu Direktur Keuangan dan Dukungan Bisnis',
            'REVISION_REQUESTED' => 'Perlu Revisi',
            'REJECTED' => 'Ditolak',
            'ISSUED' => 'Terbit',
            default => 'Diajukan',
        };
    }

    public static function reference(Ticket $ticket): string
    {
        return $ticket->ticket_no.'/R'.data_get($ticket->payload, 'sppd.version', 1);
    }

    public static function rupiah(int $cents): string
    {
        return 'Rp'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', '.');
    }
}
