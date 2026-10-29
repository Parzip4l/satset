<?php

namespace App\Support;

use App\Models\User;

class AccountAccess
{
    public static function isSuperadmin(?User $user): bool
    {
        return strtolower(trim((string) $user?->role)) === 'superadmin';
    }

    public static function isAdmin(?User $user): bool
    {
        return in_array(strtolower(trim((string) $user?->role)), ['admin', 'superadmin'], true);
    }
}
