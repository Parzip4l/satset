<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class BrowserWebPushService
{
    public function configured(): bool
    {
        return (bool) config('satset.web_push.enabled')
            && (string) config('satset.web_push.public_key') !== ''
            && (string) config('satset.web_push.private_key') !== '';
    }

    public function sendToUser(User $recipient, string $title, string $body, string $url, array $data = []): void
    {
        if (! $this->configured() || ! Schema::hasTable('web_push_subscriptions')) {
            return;
        }

        $subscriptions = WebPushSubscription::query()
            ->where('user_id', $recipient->id)
            ->whereNull('failed_at')
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('satset.web_push.subject'),
                    'publicKey' => (string) config('satset.web_push.public_key'),
                    'privateKey' => (string) config('satset.web_push.private_key'),
                ],
            ], [
                'TTL' => (int) config('satset.web_push.ttl', 86400),
                'urgency' => 'high',
            ], (int) config('satset.web_push.timeout', 10));

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'icon' => asset('assets/images/satset-icon-192.png'),
                'badge' => asset('assets/images/k_favicon_32x.png'),
                'tag' => $data['tag'] ?? 'satset-notification',
                'data' => $data,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            foreach ($subscriptions as $stored) {
                $webPush->queueNotification(new Subscription(
                    $stored->endpoint,
                    $stored->public_key,
                    $stored->auth_token,
                    $stored->content_encoding,
                ), $payload);
            }

            foreach ($webPush->flush() as $report) {
                $stored = $subscriptions->firstWhere('endpoint', $report->getEndpoint());
                if (! $stored) {
                    continue;
                }

                if ($report->isSuccess()) {
                    $stored->forceFill(['last_used_at' => now(), 'failed_at' => null])->save();
                } elseif ($report->isSubscriptionExpired()) {
                    $stored->delete();
                } else {
                    Log::warning('Browser Web Push failed.', [
                        'user_id' => $recipient->id,
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Browser Web Push could not be sent.', [
                'user_id' => $recipient->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
