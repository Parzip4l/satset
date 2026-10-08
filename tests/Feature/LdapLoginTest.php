<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LdapLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_missing_ldap_host_returns_a_controlled_login_error(): void
    {
        config([
            'ldap.default' => 'default',
            'ldap.connections.default.hosts' => [null],
            'ldap.connections.default.base_dn' => '',
        ]);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'user@lrtjakarta.co.id',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');
    }

    public function test_local_login_still_works_when_ldap_is_not_configured(): void
    {
        config([
            'ldap.default' => 'default',
            'ldap.connections.default.hosts' => [null],
            'ldap.connections.default.base_dn' => '',
        ]);

        $user = User::create([
            'name' => 'Local Admin',
            'email' => 'admin@lrtjakarta.co.id',
            'username' => 'admin',
            'phone' => '0',
            'password' => Hash::make('local-password'),
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'local-password',
        ]);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
    }
}
