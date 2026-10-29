<?php

namespace App\Support;

use App\Models\Master\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItAccess
{
    public const CAPABILITIES = ['manage_it_configuration', 'publish_it_configuration', 'view_it_queue', 'view_restricted_it', 'manage_it_operations', 'manage_it_practices', 'authorize_it_change', 'publish_it_knowledge'];

    public static function can(?User $user, string $capability): bool
    {
        return $user && in_array($capability, self::CAPABILITIES, true)
            && (AccountAccess::isSuperadmin($user) || (Schema::hasTable('it_user_capabilities')
            && DB::table('it_user_capabilities')->where('user_id', $user->id)->where('capability', $capability)->exists()));
    }

    public static function isIt(Ticket $ticket): bool
    {
        if (data_get($ticket->payload, 'request_type') === 'it') {
            return true;
        }

        return ($ticket->service_domain_id && $ticket->serviceDomain?->code === 'it')
            || (Schema::hasTable('it_ticket_details') && $ticket->itDetail()->exists());
    }

    public static function constrainIt(Builder $query): void
    {
        $query->where(function (Builder $it) {
            $it->where(fn (Builder $marker) => $marker->whereNotNull('payload->request_type')->where('payload->request_type', 'it'));
            if (Schema::hasColumn('requests', 'service_domain_id')) {
                $it->orWhereHas('serviceDomain', fn (Builder $domain) => $domain->where('code', 'it'));
            }
            if (Schema::hasTable('it_ticket_details')) {
                $it->orWhereHas('itDetail');
            }
        });
    }

    /** Keep legacy visibility outside IT; use this alongside existing GA/requester restrictions. */
    public static function constrainVisible(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $visible) use ($user) {
            $visible->whereNot(fn (Builder $it) => self::constrainIt($it))
                ->orWhere(function (Builder $it) use ($user) {
                    self::constrainIt($it);
                    self::constrainReadable($it, $user);
                });
        });
    }

    public static function constrainReadable(Builder $query, User $user): void
    {
        // Restricted tickets require an explicit read capability even for configuration administrators.
        if (Schema::hasTable('it_ticket_details') && ! self::can($user, 'view_restricted_it')) {
            $query->where(fn (Builder $restriction) => $restriction->where('requester_id', $user->id)
                ->orWhereDoesntHave('itDetail', fn (Builder $detail) => $detail->where('is_restricted', true)));
        }

        $query->where(function (Builder $access) use ($user) {
            $access->where('requester_id', $user->id);
            if (Schema::hasColumn('it_ticket_details', 'catalog_key')) {
                // Runtime access follows active team membership; stale assignment does not retain access.
                $access->orWhere(fn (Builder $legacy) => $legacy->where('assigned_user_id', $user->id)
                    ->whereDoesntHave('itDetail', fn (Builder $detail) => $detail->whereNotNull('catalog_key')));
            } else {
                $access->orWhere('assigned_user_id', $user->id);
            }
            $access->orWhereHas('approvals', fn (Builder $approval) => $approval->where('approver_id', $user->id));
            if (self::can($user, 'view_it_queue')) {
                $access->orWhereRaw('1 = 1');
            } elseif (Schema::hasTable('support_team_members') && Schema::hasTable('it_ticket_details')) {
                $access->orWhereHas('itDetail.supportTeam', fn (Builder $team) => $team->where('is_active', true)
                    ->whereHas('members', fn (Builder $member) => $member->where('users.id', $user->id)));
            }
        });
    }

    public static function operational(User $user): bool
    {
        return self::can($user, 'manage_it_operations') || (Schema::hasTable('support_team_members') && DB::table('support_team_members')
            ->join('support_teams', 'support_teams.id', '=', 'support_team_members.support_team_id')->where('user_id', $user->id)->where('is_active', true)->whereIn('role', ['technician', 'coordinator'])->exists());
    }

    public static function canRead(User $user, Ticket $ticket): bool
    {
        $query = Ticket::whereKey($ticket->id);
        self::constrainReadable($query, $user);

        return $query->exists();
    }
}
