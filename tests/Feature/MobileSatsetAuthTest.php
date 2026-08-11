<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileSatsetAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('mobile_satset_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('mobile_satset_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('token_hash', 64)->unique();
            $table->string('client_name', 80)->default('lrtj-space');
            $table->string('device_id', 120)->nullable();
            $table->string('device_name', 150)->nullable();
            $table->string('platform', 30)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_signed_sso_exchange_returns_mobile_satset_token(): void
    {
        config(['satset.mobile_sso.shared_secret' => 'test-shared-secret']);
        $body = json_encode([
            'email' => 'user@lrtjakarta.co.id',
            'name' => 'User LRTJ',
            'device_id' => 'device-1',
            'platform' => 'ios',
        ]);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-shared-secret');

        $response = $this->call('POST', '/api/mobile/v1/satset/auth/sso-exchange', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SATSET_TIMESTAMP' => $timestamp,
            'HTTP_X_SATSET_SIGNATURE' => $signature,
        ], $body);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token', 'expires_at', 'user' => ['email']]]);
    }

    public function test_mobile_satset_bearer_token_can_access_me(): void
    {
        $token = $this->exchangeToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/mobile/v1/satset/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'user@lrtjakarta.co.id');
    }

    private function exchangeToken(): string
    {
        config(['satset.mobile_sso.shared_secret' => 'test-shared-secret']);
        $body = json_encode(['email' => 'user@lrtjakarta.co.id', 'name' => 'User LRTJ']);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-shared-secret');

        return $this->call('POST', '/api/mobile/v1/satset/auth/sso-exchange', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SATSET_TIMESTAMP' => $timestamp,
            'HTTP_X_SATSET_SIGNATURE' => $signature,
        ], $body)->json('data.access_token');
    }
}
