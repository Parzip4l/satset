<?php

namespace App\Http\Middleware;

use App\Models\Master\Ticket;
use App\Support\ItAccess;
use Closure;
use Illuminate\Http\Request;

class ProtectItTickets
{
    public function handle(Request $request, Closure $next)
    {
        $controller = $request->route()?->getActionName() ?? '';
        if (str_contains($controller, 'Setting\\TicketDataController@')) {
            $it = Ticket::query();
            ItAccess::constrainIt($it);
            abort_if($it->exists(), 409, 'Data IT memerlukan backup database lengkap oleh operator server. Pengelolaan ZIP lama belum mendukung isolasi IT.');
        }
        $legacy = str_contains($controller, 'Master\\TicketController@')
            || str_contains($controller, 'MobileSatsetTicketController@')
            || str_contains($controller, 'V1\\TicketApiController@')
            || str_contains($controller, 'Intranet\\SatsetApprovalController@');

        if (! $legacy) {
            return $next($request);
        }

        $writing = ! $request->isMethodSafe();
        if ($writing) {
            abort_if($request->exists('service_domain_id') || $request->exists('it_detail')
                || $request->input('request_type') === 'it' || $request->input('payload.request_type') === 'it',
                422, 'Atribut layanan IT tidak dapat ditulis melalui endpoint tiket lama.');
        }

        $parameter = $request->route('ticket') ?? $request->route('id');
        $ticket = $parameter instanceof Ticket ? $parameter : null;
        if (! $ticket && $parameter !== null) {
            $ticket = (new Ticket)->resolveRouteBinding($parameter);
        }
        $approval = $request->route('approval');
        if (! $ticket && $approval instanceof \App\Models\Master\Approval) {
            $ticket = $approval->request;
        }

        if ($ticket && ItAccess::isIt($ticket)) {
            // API v1 trusts a supplied email, so it must never expose IT records.
            abort_if(str_contains($controller, 'V1\\TicketApiController@'), 404);
            abort_unless($request->user() && ItAccess::canRead($request->user(), $ticket), 403);
            abort_if($writing, 409, 'Workflow IT belum dibuka. Gunakan layanan IT setelah tahap implementasi berikutnya.');

            abort_if(str_contains($controller, 'viewAttachment') || str_contains($controller, 'Controller@history'),
                409, 'Lampiran dan riwayat IT akan tersedia melalui endpoint IT tersendiri.');

            if (str_contains($controller, 'Master\\TicketController@show')) {
                return redirect()->route('it.show', $ticket);
            }
        }

        return $next($request);
    }
}
