<?php

namespace Tests\Unit;

use App\Models\Master\ConsumableItem;
use App\Models\Master\ProcurementReceiving;
use App\Models\Master\ProcurementReceivingItem;
use App\Services\ConsumableStockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ConsumableStockServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('procurement_receiving_items');
        Schema::dropIfExists('procurement_receivings');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('consumable_items');

        Schema::create('consumable_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('unit');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->integer('buffer_stock')->default(0);
            $table->integer('current_stock')->default(0);
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id');
            $table->string('movement_type');
            $table->integer('qty');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('status')->default('DRAFT');
            $table->timestamps();
        });

        Schema::create('procurement_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->unsignedBigInteger('item_id');
            $table->integer('qty_ordered')->default(0);
            $table->integer('qty_received')->default(0);
            $table->integer('qty_rejected')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_receiving_increases_stock_and_records_stock_card(): void
    {
        $item = $this->item(['current_stock' => 5]);
        $movement = app(ConsumableStockService::class)->increase($item, 10, 'procurement_receiving', 1, 'Full receive');

        $this->assertSame(15, $item->fresh()->current_stock);
        $this->assertSame('IN', $movement->movement_type);
        $this->assertSame(5, $movement->balance_before);
        $this->assertSame(15, $movement->balance_after);
    }

    public function test_partial_receive_only_adds_received_quantity(): void
    {
        $item = $this->item(['current_stock' => 0]);
        $receiving = ProcurementReceiving::create(['reference_number' => 'RCV-1', 'status' => 'PARTIALLY_RECEIVED']);
        ProcurementReceivingItem::create([
            'receiving_id' => $receiving->id,
            'item_id' => $item->id,
            'qty_ordered' => 10,
            'qty_received' => 4,
            'qty_rejected' => 6,
        ]);

        app(ConsumableStockService::class)->increase($item, 4, 'procurement_receiving', $receiving->id, 'Partial receive');

        $this->assertSame(4, $item->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'IN',
            'qty' => 4,
            'balance_after' => 4,
        ]);
    }

    public function test_rejected_receiving_does_not_change_stock(): void
    {
        $item = $this->item(['current_stock' => 7]);

        $this->assertSame(7, $item->fresh()->current_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_handover_decreases_stock_and_prevents_minus_stock(): void
    {
        $item = $this->item(['current_stock' => 3]);
        $service = app(ConsumableStockService::class);

        $service->decrease($item, 2, 'atk_rtk_request', 99, 'Handover');
        $this->assertSame(1, $item->fresh()->current_stock);

        $this->expectException(InvalidArgumentException::class);
        $service->decrease($item->fresh(), 2, 'atk_rtk_request', 99, 'Over handover');
    }

    public function test_stock_opname_adjustment_records_adjustment_movement(): void
    {
        $item = $this->item(['current_stock' => 10]);

        app(ConsumableStockService::class)->adjustment($item, -3, 'stock_opname', 1, 'Opname');

        $this->assertSame(7, $item->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'ADJUSTMENT',
            'qty' => 3,
            'balance_before' => 10,
            'balance_after' => 7,
        ]);
    }

    private function item(array $attributes = []): ConsumableItem
    {
        return ConsumableItem::create(array_merge([
            'code' => 'ATK-TEST',
            'name' => 'Pulpen Test',
            'category' => 'ATK',
            'unit' => 'pcs',
            'minimum_stock' => 1,
            'buffer_stock' => 2,
            'current_stock' => 0,
            'is_active' => true,
        ], $attributes));
    }
}
