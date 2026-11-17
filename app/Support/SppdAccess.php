<?php

namespace App\Support;

use App\Models\User;

final class SppdAccess
{
    public const ROLE = 'sppd_operator';

    public const LABEL = 'Pengelola SPPD';

    public static function isOperator(?User $user): bool
    {
        return strtolower(trim((string) $user?->role)) === self::ROLE;
    }

    public static function canConfigure(?User $user): bool
    {
        return self::isOperator($user) || AccountAccess::isAdmin($user);
    }
}
