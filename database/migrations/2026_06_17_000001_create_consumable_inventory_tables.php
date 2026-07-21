<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumable_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('category', 20)->index();
            $table->string('unit', 30);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->integer('buffer_stock')->default(0);
            $table->integer('current_stock')->default(0);
            $table->string('location', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('consumable_items')->cascadeOnDelete();
            $table->string('movement_type', 30)->index();
            $table->integer('qty');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('reference_type', 80)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'created_at']);
        });

        Schema::create('procurement_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 50)->unique();
            $table->string('vendor_name', 150)->nullable();
            $table->string('po_number', 80)->nullable()->index();
            $table->string('do_number', 80)->nullable()->index();
            $table->string('gr_number', 80)->nullable()->index();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->date('received_date')->nullable()->index();
            $table->date('scheduled_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('procurement_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_id')->constrained('procurement_receivings')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('consumable_items')->restrictOnDelete();
            $table->integer('qty_ordered')->default(0);
            $table->integer('qty_received')->default(0);
            $table->integer('qty_rejected')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->index();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('consumable_items')->restrictOnDelete();
            $table->integer('system_stock');
            $table->integer('physical_stock');
            $table->integer('variance');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('procurement_receiving_items');
        Schema::dropIfExists('procurement_receivings');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('consumable_items');
    }
};
