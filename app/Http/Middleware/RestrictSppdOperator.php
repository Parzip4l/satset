<?php

namespace App\Http\Middleware;

use App\Models\Master\Ticket;
use App\Support\SppdAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictSppdOperator
{
    private const REQUESTER_ROUTES = [
        'ticket.index', 'ticket.mine', 'ticket.general', 'ticket.create', 'ticket.store',
        'ticket.konsumsi.create', 'ticket.atk-rtk.create', 'ticket.ga-permintaan-temuan.create',
        'ticket.form-schema', 'ticket.show', 'ticket.attachments.show', 'ticket.comment',
        'ticket.approvals.sync-manager', 'ticket.atk-rtk.goods-issue',
        'ticket.consumption.form', 'ticket.consumption.evidence', 'ticket.ga-request-finding.report',
        'it.index', 'it.request', 'it.create', 'it.form', 'it.store', 'it.show',
        'it.revise.form', 'it.revise.schema', 'it.action', 'it.attachment', 'it.notifications*',
        'booking.*', 'notifications.*', 'web-push.subscriptions.*',
        'mobile.satset.me', 'mobile.satset.logout', 'mobile.satset.bootstrap',
        'mobile.satset.requests.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! SppdAccess::isOperator($request->user())
            || $request->routeIs('sppd.*', 'settings.sppd-service.*', 'logout')) {
            return $next($request);
        }

        if ($request->isMethodSafe() && ! $request->expectsJson()
            && ($request->is('/') || $request->routeIs('dashboard', 'dashboard.index', 'login'))) {
            return redirect()->route('sppd.index');
        }

        abort_unless($request->routeIs(...self::REQUESTER_ROUTES), 403, 'Akses pengelolaan akun ini terbatas pada SPPD.');

        $ticket = $request->route('ticket');
        if ($ticket instanceof Ticket) {
            abort_unless((int) $ticket->requester_id === (int) $request->user()->id, 403);
        }

        if ($request->routeIs('it.action', 'mobile.satset.requests.it.action')) {
            abort_unless(in_array($request->route('action'), ['comment', 'attachment', 'confirm', 'reopen', 'revise', 'cancel'], true), 403);
        }

        return $next($request);
    }
}
