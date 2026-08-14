<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIntranetSatsetSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('satset.intranet_api.shared_secret');
        if ($secret === '') {
            return response()->json(['success' => false, 'message' => 'Shared secret Satset Intranet API belum dikonfigurasi.'], 500);
        }

        $timestamp = (int) $request->header('X-Satset-Timestamp');
        $signature = (string) $request->header('X-Satset-Signature');
        $tolerance = (int) config('satset.intranet_api.signature_tolerance_seconds', 300);

        if ($timestamp <= 0 || $signature === '') {
            return response()->json(['success' => false, 'message' => 'Header signature Satset wajib dikirim.'], 401);
        }

        if (abs(now()->timestamp - $timestamp) > $tolerance) {
            return response()->json(['success' => false, 'message' => 'Signature Satset sudah kedaluwarsa.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['success' => false, 'message' => 'Signature Satset tidak valid.'], 401);
        }

        return $next($request);
    }
}
