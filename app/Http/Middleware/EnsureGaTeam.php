<?php

namespace App\Http\Middleware;

use App\Support\GaAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGaTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!GaAccess::allowed($request->user())) {
            abort(403, 'Menu Operasional GA hanya untuk tim GA.');
        }

        return $next($request);
    }
}
