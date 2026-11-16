<?php

namespace App\Http\Middleware;

use App\Support\ServiceDomains;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSppdAcceptingRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ServiceDomains::sppdActive()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(423, 'Pengajuan SPPD baru sedang ditutup.');
        }

        return redirect()->route('sppd.index');
    }
}
