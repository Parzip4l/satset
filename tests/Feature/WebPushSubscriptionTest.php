<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_and_refresh_a_browser_subscription(): void
    {
        config()->set('satset.web_push.enabled', true);
        $user = $this->user();
        $endpoint = 'https://push.example.test/subscriptions/browser-123';

        $payload = [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => str_repeat('a', 87),
                'auth' => str_repeat('b', 22),
            ],
            'content_encoding' => 'aes128gcm',
        ];

        $this->actingAs($user)
            ->postJson(route('web-push.subscriptions.store'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Notifikasi browser berhasil diaktifkan.');

        $this->assertDatabaseHas('web_push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
            'content_encoding' => 'aes128gcm',
        ]);

        $this->actingAs($user)
            ->postJson(route('web-push.subscriptions.store'), array_replace_recursive($payload, [
                'keys' => ['auth' => 'updated-auth-token'],
            ]))
            ->assertOk();

        $this->assertDatabaseCount('web_push_subscriptions', 1);
        $this->assertDatabaseHas('web_push_subscriptions', [
            'endpoint_hash' => hash('sha256', $endpoint),
            'auth_token' => 'updated-auth-token',
        ]);
    }

    public function test_subscription_endpoint_requires_authentication(): void
    {
        $this->postJson(route('web-push.subscriptions.store'), [])->assertUnauthorized();
    }

    public function test_user_can_remove_only_their_current_browser_subscription(): void
    {
        config()->set('satset.web_push.enabled', true);
        $user = $this->user();
        $endpoint = 'https://push.example.test/subscriptions/to-remove';

        $this->actingAs($user)->postJson(route('web-push.subscriptions.store'), [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
        ])->assertOk();

        $this->actingAs($user)->deleteJson(route('web-push.subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ])->assertOk();

        $this->assertDatabaseMissing('web_push_subscriptions', [
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
    }

    private function user(): User
    {
        return User::query()->forceCreate([
            'name' => 'Web Push User',
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'phone' => '0800000000',
            'password' => Hash::make('password'),
            'kartu_uang_1' => 'TEST-CARD',
            'role' => 'pelapor',
        ]);
    }
}
