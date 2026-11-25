<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['it_asset_types', 'it_asset_locations'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('name_key', 150)->unique();
                $table->timestamps();
            });
        }
        foreach (['Hardware', 'Software', 'Lainnya'] as $name) {
            DB::table('it_asset_types')->insert(['name' => $name, 'name_key' => strtolower($name), 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('it_asset_employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('portal_id')->unique();
            $table->string('name');
            $table->string('email')->index();
            $table->string('employee_number', 100)->nullable();
            $table->string('department')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at');
            $table->timestamps();
        });
        Schema::create('it_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 80)->unique();
            $table->string('name', 200);
            $table->foreignId('type_id')->constrained('it_asset_types')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('it_asset_locations')->restrictOnDelete();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('it_asset_employees')->restrictOnDelete();
            $table->string('brand', 100)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 150)->nullable()->index();
            $table->date('acquired_on')->nullable()->index();
            $table->decimal('purchase_price', 18, 2)->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status', 30)->default('available')->index();
            $table->text('notes')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['type_id', 'location_id']);
        });
        Schema::create('it_asset_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('it_assets')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->unsignedInteger('sequence');
            $table->string('action', 30);
            $table->text('reason');
            $table->json('before_state')->nullable();
            $table->json('after_state');
            $table->timestamp('created_at');
            $table->unique(['asset_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_asset_histories');
        Schema::dropIfExists('it_assets');
        Schema::dropIfExists('it_asset_employees');
        Schema::dropIfExists('it_asset_locations');
        Schema::dropIfExists('it_asset_types');
    }
};
