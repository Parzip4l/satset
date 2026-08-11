<?php

namespace App\Http\Middleware;

use App\Models\MobileSatsetToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileSatset
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->header('Authorization', '');
        if (!str_starts_with($authorization, 'Bearer ')) {
            return response()->json(['success' => false, 'message' => 'Bearer token wajib dikirim.'], 401);
        }

        $plainToken = trim(substr($authorization, 7));
        if ($plainToken === '') {
            return response()->json(['success' => false, 'message' => 'Bearer token tidak valid.'], 401);
        }

        $token = MobileSatsetToken::with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (!$token || !$token->user || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['success' => false, 'message' => 'Session SatSet tidak valid atau sudah kedaluwarsa.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('mobile_satset_token', $token);

        return $next($request);
    }
}
