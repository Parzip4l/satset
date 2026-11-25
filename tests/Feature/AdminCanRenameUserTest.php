<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SppdAccess;
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

    public function test_only_superadmin_can_assign_or_modify_superadmin_accounts(): void
    {
        $admin = $this->user('Admin', 'admin@lrtjakarta.co.id', 'admin');
        $super = $this->user('Super', 'super@lrtjakarta.co.id', 'superadmin');
        $target = $this->user('Target', 'target@lrtjakarta.co.id', 'pelapor');
        $this->actingAs($admin)->put(route('user.update', $target), ['role' => 'superadmin'])->assertForbidden();
        $this->put(route('user.update', $super), ['password' => 'newpassword', 'password_confirmation' => 'newpassword'])->assertForbidden();
        $this->post(route('user.store'), ['name' => 'New', 'email' => 'new@lrtjakarta.co.id', 'role' => 'superadmin', 'password' => 'password'])->assertForbidden();
        $this->actingAs($super)->put(route('user.update', $target), ['role' => 'superadmin'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'superadmin']);
    }

    public function test_only_admin_can_assign_sppd_role_or_modify_its_accounts(): void
    {
        $admin = $this->user('Admin', 'admin@lrtjakarta.co.id', 'admin');
        $employee = $this->user('User Biasa', 'regular@lrtjakarta.co.id', 'pelapor');
        $target = $this->user('Operator', 'sppd@lrtjakarta.co.id', 'pelapor');
        $this->actingAs($admin)->put(route('user.update', $target), ['role' => SppdAccess::ROLE])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => SppdAccess::ROLE]);

        $this->actingAs($employee)->put(route('user.update', $target), ['password' => 'newpassword', 'password_confirmation' => 'newpassword'])
            ->assertForbidden();
        $this->post(route('user.store'), ['name' => 'Operator Baru', 'email' => 'new@lrtjakarta.co.id', 'role' => SppdAccess::ROLE, 'password' => 'password'])
            ->assertForbidden();
    }

    public function test_it_manager_role_is_assigned_by_admin_and_cannot_be_self_granted(): void
    {
        $admin = $this->user('Admin', 'admin@lrtjakarta.co.id', 'admin');
        $target = $this->user('Karyawan', 'employee@lrtjakarta.co.id', 'pelapor');
        $this->actingAs($target)->put(route('user.update', $target), ['role' => \App\Support\ItAccess::ROLE])->assertRedirect();
        $this->assertSame('pelapor', $target->fresh()->role);
        $this->actingAs($admin)->put(route('user.update', $target), ['role' => \App\Support\ItAccess::ROLE])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(\App\Support\ItAccess::ROLE, $target->fresh()->role);
        $this->actingAs($target)->post(route('user.store'), ['name' => 'Other', 'email' => 'other@example.test', 'role' => 'admin', 'password' => 'password'])->assertForbidden();
        $this->put(route('user.update', $admin), ['role' => \App\Support\ItAccess::ROLE])->assertForbidden();
        $this->actingAs($admin)->put(route('user.update', $target), ['role' => 'pelapor'])->assertRedirect();
        $this->assertFalse(\App\Support\ItAccess::can($target->fresh(), 'manage_it_assets'));
    }

    public function test_pic_assignment_cannot_replace_the_it_manager_role(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        $division = \Illuminate\Support\Facades\DB::table('divisions')->insertGetId(['name' => 'IT']);
        $admin = $this->user('Admin', 'admin@example.test', 'admin');
        $operator = $this->user('IT Manager', 'it@example.test', \App\Support\ItAccess::ROLE);
        $input = ['name' => $operator->id, 'divisi' => $division];
        $this->actingAs($admin)->post(route('pic.dataupdate'), $input)->assertForbidden();
        $this->put(route('pic.update', $operator->id), $input)->assertForbidden();
        $this->assertSame(\App\Support\ItAccess::ROLE, $operator->fresh()->role);
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
