<?php

namespace App\Support;

use App\Models\Master\Ticket;
use App\Models\User;

final class ProcurementAccess
{
    public const TYPE = 'goods_procurement';

    public static function is(Ticket $ticket): bool
    {
        return data_get($ticket->payload, 'request_type') === self::TYPE;
    }

    public static function allowed(?User $user): bool
    {
        return $user && (GaAccess::allowed($user)
            || strtolower(trim((string) $user->role)) === 'procurement');
    }
}
