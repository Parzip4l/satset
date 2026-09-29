<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    public function test_optional_legacy_columns_are_ignored_when_users_table_does_not_have_them(): void
    {
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        $user = User::forceCreate([
            'user_type' => 'public',
            'name' => 'Pelapor ATK',
            'email' => 'pelapor.atk@example.test',
            'phone' => '0',
            'password' => 'secret',
            'kartu_uang_1' => '-',
            'kartu_uang_2' => null,
            'role' => 'pelapor',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'pelapor.atk@example.test',
            'name' => 'Pelapor ATK',
        ]);
        $this->assertArrayNotHasKey('kartu_uang_1', $user->getAttributes());
    }
}
