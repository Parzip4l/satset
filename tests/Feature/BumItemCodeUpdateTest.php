<?php

namespace Tests\Feature;

use App\Models\Master\ConsumableItem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BumItemCodeUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('consumable_items');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('consumable_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category')->default('ATK');
            $table->string('unit')->default('pcs');
            $table->string('large_uom')->default('box');
            $table->string('small_uom')->default('pcs');
            $table->unsignedInteger('conversion_qty')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->unsignedInteger('buffer_stock')->default(0);
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('small_stock')->default(0);
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_ga_can_update_item_code_with_roman_number_and_dot(): void
    {
        $ga = User::create([
            'name' => 'Team GA',
            'email' => 'teamga@lrtjakarta.co.id',
            'password' => 'password',
            'role' => 'ga',
        ]);
        $item = ConsumableItem::create([
            'code' => 'III.24',
            'name' => 'Barang ATK',
            'category' => 'ATK',
        ]);

        $response = $this->actingAs($ga)
            ->from(route('bum.items.show', $item))
            ->patch(route('bum.items.code.update', $item), [
                'code' => 'III.25',
                'location' => 'Rak B-12',
            ]);

        $response
            ->assertRedirect(route('bum.items.show', $item))
            ->assertSessionHas('success', 'Kode dan bin lokasi barang berhasil diperbarui.');
        $this->assertDatabaseHas('consumable_items', [
            'id' => $item->id,
            'code' => 'III.25',
            'location' => 'Rak B-12',
        ]);
    }

    public function test_ga_cannot_reuse_another_item_code(): void
    {
        $ga = User::create([
            'name' => 'Team GA',
            'email' => 'ga@example.test',
            'password' => 'password',
            'role' => 'ga',
        ]);
        $item = ConsumableItem::create([
            'code' => 'III.24',
            'name' => 'Barang Pertama',
            'category' => 'ATK',
        ]);
        ConsumableItem::create([
            'code' => 'III.25',
            'name' => 'Barang Kedua',
            'category' => 'ATK',
        ]);

        $response = $this->actingAs($ga)
            ->from(route('bum.items.show', $item))
            ->patch(route('bum.items.code.update', $item), ['code' => 'III.25']);

        $response
            ->assertRedirect(route('bum.items.show', $item))
            ->assertSessionHasErrorsIn('itemCode', ['code']);
        $this->assertDatabaseHas('consumable_items', [
            'id' => $item->id,
            'code' => 'III.24',
        ]);
    }

    public function test_ga_can_deactivate_and_reactivate_an_item(): void
    {
        $ga = User::create([
            'name' => 'Team GA',
            'email' => 'teamga@lrtjakarta.co.id',
            'password' => 'password',
            'role' => 'ga',
        ]);
        $item = ConsumableItem::create([
            'code' => 'III.26',
            'name' => 'Barang Bisa Dinonaktifkan',
            'category' => 'ATK',
            'is_active' => true,
        ]);

        $this->actingAs($ga)
            ->from(route('bum.items'))
            ->patch(route('bum.items.status.update', $item), ['is_active' => false])
            ->assertRedirect(route('bum.items'))
            ->assertSessionHas('success');
        $this->assertFalse($item->fresh()->is_active);

        $this->actingAs($ga)
            ->from(route('bum.items'))
            ->patch(route('bum.items.status.update', $item), ['is_active' => true])
            ->assertRedirect(route('bum.items'))
            ->assertSessionHas('success');
        $this->assertTrue($item->fresh()->is_active);
    }
}
