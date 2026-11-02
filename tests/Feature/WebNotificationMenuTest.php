<?php

namespace Tests\Feature;

use App\Models\Master\Notification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebNotificationMenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->nullable();
            $table->text('message');
            $table->text('url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function test_user_sees_only_their_notifications_and_can_mark_one_read(): void
    {
        $user = User::create(['name' => 'Pemohon', 'email' => 'user@example.test', 'password' => 'secret']);
        $other = User::create(['name' => 'Lain', 'email' => 'other@example.test', 'password' => 'secret']);
        $mine = Notification::create(['user_id' => $user->id, 'title' => 'Tiket baru', 'message' => 'Tiket dibuat', 'url' => '/ticket/123']);
        $theirs = Notification::create(['user_id' => $other->id, 'message' => 'Notifikasi lain', 'url' => '/ticket/456']);

        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.url', '/ticket/123')
            ->assertJsonCount(1, 'notifications');

        $this->postJson(route('notifications.read', $theirs->id))->assertNotFound();
        $this->postJson(route('notifications.read', $mine->id))->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $mine->id, 'is_read' => true]);
        $this->assertDatabaseHas('notifications', ['id' => $theirs->id, 'is_read' => false]);
        $this->getJson(route('notifications.index'))->assertJsonPath('unread_count', 0);
    }
}
