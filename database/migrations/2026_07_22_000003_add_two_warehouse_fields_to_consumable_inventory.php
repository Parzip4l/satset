<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumable_items', function (Blueprint $table) {
            $table->string('large_uom', 30)->default('box')->after('unit');
            $table->string('small_uom', 30)->default('pcs')->after('large_uom');
            $table->unsignedInteger('conversion_qty')->default(1)->after('small_uom');
            $table->integer('small_stock')->default(0)->after('current_stock');
        });

        DB::table('consumable_items')->update([
            'large_uom' => DB::raw('unit'),
            'small_uom' => 'pcs',
            'conversion_qty' => 1,
            'small_stock' => 0,
        ]);

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('stock_location', 30)->default('big_warehouse')->after('movement_type')->index();
            $table->string('source_stock_location', 30)->nullable()->after('stock_location');
            $table->string('destination_stock_location', 30)->nullable()->after('source_stock_location');
            $table->string('balance_uom', 30)->nullable()->after('balance_after');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['stock_location', 'source_stock_location', 'destination_stock_location', 'balance_uom']);
        });

        Schema::table('consumable_items', function (Blueprint $table) {
            $table->dropColumn(['large_uom', 'small_uom', 'conversion_qty', 'small_stock']);
        });
    }
};
