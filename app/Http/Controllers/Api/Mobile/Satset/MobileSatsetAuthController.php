<?php

namespace App\Http\Controllers\Api\Mobile\Satset;

use App\Http\Controllers\Controller;
use App\Models\MobileSatsetToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MobileSatsetAuthController extends Controller
{
    public function exchange(Request $request): JsonResponse
    {
        $secret = (string) config('satset.mobile_sso.shared_secret');
        if ($secret === '') {
            return response()->json(['success' => false, 'message' => 'SatSet mobile SSO secret belum dikonfigurasi.'], 503);
        }

        $timestamp = (int) $request->header('X-Satset-Timestamp');
        $signature = (string) $request->header('X-Satset-Signature');
        $tolerance = (int) config('satset.mobile_sso.signature_tolerance_seconds', 300);
        if ($timestamp <= 0 || abs(now()->timestamp - $timestamp) > $tolerance) {
            return response()->json(['success' => false, 'message' => 'SSO exchange timestamp tidak valid.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);
        if (!hash_equals($expected, $signature)) {
            return response()->json(['success' => false, 'message' => 'SSO exchange signature tidak valid.'], 401);
        }

        $data = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:150',
            'username' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'device_id' => 'nullable|string|max:120',
            'device_name' => 'nullable|string|max:150',
            'platform' => 'nullable|string|max:30',
        ]);

        $email = Str::lower($data['email']);
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: $data['name'];
        $user->username = $user->username ?: ($data['username'] ?? Str::before($email, '@'));
        $user->phone = $user->phone ?: ($data['phone'] ?? null);
        if (!$user->exists) {
            $user->password = Hash::make(Str::random(40));
        }
        $user->save();

        $plainToken = Str::random(80);
        $expiresAt = now()->addMinutes((int) config('satset.mobile_sso.token_ttl_minutes', 129600));
        MobileSatsetToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'client_name' => 'lrtj-space',
            'device_id' => $data['device_id'] ?? null,
            'device_name' => $data['device_name'] ?? null,
            'platform' => $data['platform'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $plainToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('mobile_satset_token');
        $token?->delete();

        return response()->json(['success' => true, 'message' => 'Session SatSet mobile dihapus.']);
    }
}
