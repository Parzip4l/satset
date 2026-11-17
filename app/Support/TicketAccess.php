<?php

namespace App\Support;

use App\Models\Master\Ticket;
use App\Models\User;

final class TicketAccess
{
    public static function canView(?User $user, Ticket $ticket): bool
    {
        if (! $user) {
            return false;
        }

        if (Sppd::is($ticket)) {
            return SppdAccess::isOperator($user)
                || (int) $ticket->requester_id === (int) $user->id
                || $ticket->approvals()->where('approver_id', $user->id)->exists();
        }

        if (SppdAccess::isOperator($user)) {
            return (int) $ticket->requester_id === (int) $user->id;
        }

        if (ItAccess::isIt($ticket)) {
            return ItAccess::canRead($user, $ticket);
        }

        if (GaAccess::allowed($user)) {
            return true;
        }

        if ((int) $ticket->requester_id === (int) $user->id
            || (int) $ticket->assigned_user_id === (int) $user->id) {
            return true;
        }

        return $ticket->approvals()
            ->where('approver_id', $user->id)
            ->exists();
    }
}
