<?php

namespace App\Support;

use App\Models\Master\ServiceDomain;
use App\Models\Master\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TicketServiceDomain
{
    public static function code(Ticket $ticket): ?string
    {
        if (ItAccess::isIt($ticket)) {
            return 'it';
        }
        if ($ticket->service_domain_id) {
            return $ticket->serviceDomain?->code;
        }
        $type = data_get($ticket->payload, 'request_type');

        return in_array($type, ['atk_rtk', 'consumption', 'ga_request_finding'], true) ? $type : null;
    }

    public static function filter(Builder $query, mixed $code): Builder
    {
        if (! is_string($code) || ! array_key_exists($code, ServiceDomains::DEFINITIONS)) {
            return $query->whereRaw('1 = 0');
        }
        if ($code === 'it') {
            ItAccess::constrainIt($query);

            return $query;
        }

        return $query->where(function (Builder $domain) use ($code) {
            if (Schema::hasColumn('requests', 'service_domain_id')) {
                $domain->whereHas('serviceDomain', fn (Builder $service) => $service->where('code', $code))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('service_domain_id')->where('payload->request_type', $code));
            } else {
                $domain->where('payload->request_type', $code);
            }
        })->whereNot(fn (Builder $it) => ItAccess::constrainIt($it));
    }

    public static function assignKnownDomain(Ticket $ticket): void
    {
        if ($ticket->service_domain_id || ! Schema::hasColumn('requests', 'service_domain_id')) {
            return;
        }
        $type = data_get($ticket->payload, 'request_type');
        if (in_array($type, ['atk_rtk', 'consumption', 'ga_request_finding'], true)) {
            $ticket->service_domain_id = ServiceDomain::where('code', $type)->value('id');
        }
    }
}
