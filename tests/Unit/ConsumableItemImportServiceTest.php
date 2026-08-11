<?php

namespace Tests\Unit;

use App\Models\Master\ConsumableItem;
use App\Services\ConsumableItemImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumableItemImportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('consumable_items');
        Schema::dropIfExists('consumable_uoms');

        Schema::create('consumable_uoms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('consumable_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('unit');
            $table->string('large_uom')->default('box');
            $table->string('small_uom')->default('pcs');
            $table->unsignedInteger('conversion_qty')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->integer('buffer_stock')->default(0);
            $table->integer('current_stock')->default(0);
            $table->integer('small_stock')->default(0);
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id');
            $table->string('movement_type');
            $table->string('stock_location')->default('big_warehouse');
            $table->string('source_stock_location')->nullable();
            $table->string('destination_stock_location')->nullable();
            $table->integer('qty');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('balance_uom')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_import_updates_existing_item_without_creating_duplicate(): void
    {
        ConsumableItem::create([
            'code' => 'I.1',
            'name' => 'Amplop Lama',
            'category' => 'ATK',
            'unit' => 'pcs',
            'large_uom' => 'rim',
            'small_uom' => 'lembar',
            'conversion_qty' => 100,
            'minimum_stock' => 10,
            'buffer_stock' => 10,
            'current_stock' => 5,
            'small_stock' => 2,
            'is_active' => true,
        ]);

        $summary = app(ConsumableItemImportService::class)->import($this->csvPath(), ['stock_mode' => 'small']);

        $this->assertSame(1, $summary['updated']);
        $this->assertSame(1, ConsumableItem::where('code', 'I.1')->count());

        $item = ConsumableItem::where('code', 'I.1')->first();
        $this->assertSame('Amplop Baru', $item->name);
        $this->assertSame(5, $item->current_stock);
        $this->assertSame(9, $item->small_stock);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => 'ADJUSTMENT',
            'stock_location' => 'small_warehouse',
            'qty' => 7,
            'balance_before' => 2,
            'balance_after' => 9,
            'reference_type' => 'master_item_upload',
        ]);
    }

    public function test_import_can_keep_existing_stock(): void
    {
        ConsumableItem::create([
            'code' => 'I.1',
            'name' => 'Amplop Lama',
            'category' => 'ATK',
            'unit' => 'pcs',
            'large_uom' => 'rim',
            'small_uom' => 'lembar',
            'conversion_qty' => 100,
            'minimum_stock' => 10,
            'buffer_stock' => 10,
            'current_stock' => 5,
            'small_stock' => 2,
            'is_active' => true,
        ]);

        app(ConsumableItemImportService::class)->import($this->csvPath(), ['stock_mode' => 'keep']);

        $item = ConsumableItem::where('code', 'I.1')->first();
        $this->assertSame(5, $item->current_stock);
        $this->assertSame(2, $item->small_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    private function csvPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'master-items-') . '.csv';
        file_put_contents($path, implode("\n", [
            'Kategori,Kode Barang,Nama Barang,Satuan,Jumlah Konversi,Satuan Kecil,Binloc,Harga,Stok Minimum,SOH/Saldo Berjalan',
            'ATK,I.1,Amplop Baru,Rim,100,Lembar,GA02,3300,100,9',
        ]));

        return $path;
    }
}
