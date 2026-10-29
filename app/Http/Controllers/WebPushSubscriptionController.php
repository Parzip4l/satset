<?php

namespace App\Http\Controllers;

use App\Models\WebPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(config('satset.web_push.enabled'), 404);

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:4096', 'url'],
            'keys.p256dh' => ['required', 'string', 'max:2048'],
            'keys.auth' => ['required', 'string', 'max:512'],
            'content_encoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $endpoint = $validated['endpoint'];
        $subscription = WebPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $endpoint,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'last_used_at' => now(),
                'failed_at' => null,
            ],
        );

        return response()->json([
            'message' => 'Notifikasi browser berhasil diaktifkan.',
            'subscription_id' => $subscription->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:4096'],
        ]);

        WebPushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->delete();

        return response()->json(['message' => 'Langganan notifikasi browser dihapus.']);
    }
}
