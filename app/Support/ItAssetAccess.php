<?php

namespace App\Support;

use App\Models\User;

final class ItAssetAccess
{
    public static function allowed(?User $user): bool
    {
        return $user && ! SppdAccess::isOperator($user)
            && (ItAccess::can($user, 'manage_it_assets') || ItAccess::operational($user));
    }

    public const STATUSES = [
        'available' => ['label' => 'Tersedia', 'color' => 'success'],
        'in_use' => ['label' => 'Digunakan', 'color' => 'primary'],
        'maintenance' => ['label' => 'Dalam Perbaikan', 'color' => 'warning'],
        'damaged' => ['label' => 'Rusak', 'color' => 'danger'],
        'lost' => ['label' => 'Hilang', 'color' => 'danger'],
        'retired' => ['label' => 'Tidak Aktif', 'color' => 'secondary'],
        'expired' => ['label' => 'Lisensi Berakhir', 'color' => 'secondary'],
    ];
}
