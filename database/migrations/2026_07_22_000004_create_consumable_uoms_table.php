<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumable_uoms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('consumable_uoms')->insert([
            ['code' => 'pcs', 'name' => 'Pieces', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'buah', 'name' => 'Buah', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'box', 'name' => 'Box', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'pack', 'name' => 'Pack', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'dus', 'name' => 'Dus', 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'rim', 'name' => 'Rim', 'is_active' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'roll', 'name' => 'Roll', 'is_active' => true, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'lembar', 'name' => 'Lembar', 'is_active' => true, 'sort_order' => 80, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'botol', 'name' => 'Botol', 'is_active' => true, 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'liter', 'name' => 'Liter', 'is_active' => true, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('consumable_uoms');
    }
};
