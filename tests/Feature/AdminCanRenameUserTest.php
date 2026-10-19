<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminCanRenameUserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('role')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_admin_can_change_a_users_name(): void
    {
        $admin = $this->user('Administrator', 'admin@lrtjakarta.co.id', 'admin');
        $target = $this->user('Nama Lama', 'user@lrtjakarta.co.id', 'pelapor');

        $this->actingAs($admin)
            ->put(route('user.update', $target), ['name' => 'Nama Baru'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Data user berhasil diperbarui.');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Nama Baru',
        ]);
    }

    public function test_non_admin_cannot_change_another_users_name(): void
    {
        $regularUser = $this->user('User Biasa', 'regular@lrtjakarta.co.id', 'pelapor');
        $target = $this->user('Nama Tetap', 'target@lrtjakarta.co.id', 'pelapor');

        $this->actingAs($regularUser)
            ->put(route('user.update', $target), ['name' => 'Nama Paksa'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Nama Tetap',
        ]);
    }

    private function user(string $name, string $email, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => 'password',
        ]);
    }
}
