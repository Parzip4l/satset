<?php

namespace App\Services;

use App\Models\Master\ConsumableItem;
use App\Models\Master\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConsumableStockService
{
    public function increase(ConsumableItem $item, int $qty, string $referenceType, ?int $referenceId, ?string $notes = null, ?int $userId = null): StockMovement
    {
        return $this->move($item, 'IN', abs($qty), $referenceType, $referenceId, $notes, $userId, 'big_warehouse');
    }

    public function decrease(ConsumableItem $item, int $qty, string $referenceType, ?int $referenceId, ?string $notes = null, ?int $userId = null): StockMovement
    {
        return $this->move($item, 'OUT', -abs($qty), $referenceType, $referenceId, $notes, $userId, 'big_warehouse');
    }

    public function increaseSmall(ConsumableItem $item, int $qty, string $referenceType, ?int $referenceId, ?string $notes = null, ?int $userId = null): StockMovement
    {
        return $this->move($item, 'IN', abs($qty), $referenceType, $referenceId, $notes, $userId, 'small_warehouse');
    }

    public function decreaseSmall(ConsumableItem $item, int $qty, string $referenceType, ?int $referenceId, ?string $notes = null, ?int $userId = null): StockMovement
    {
        return $this->move($item, 'OUT', -abs($qty), $referenceType, $referenceId, $notes, $userId, 'small_warehouse');
    }

    public function transferBigToSmall(ConsumableItem $item, int $largeQty, string $referenceType, ?int $referenceId, ?string $notes = null, ?int $userId = null): array
    {
        if ($largeQty <= 0) {
            throw new InvalidArgumentException('Qty transfer gudang besar harus lebih dari 0.');
        }

        return DB::transaction(function () use ($item, $largeQty, $referenceType, $referenceId, $notes, $userId) {
            $locked = ConsumableItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $smallQty = $largeQty * max(1, (int) $locked->conversion_qty);

            $out = $this->move($locked, 'TRANSFER_OUT', -abs($largeQty), $referenceType, $referenceId, $notes, $userId, 'big_warehouse', 'big_warehouse', 'small_warehouse');
            $in = $this->move($locked->fresh(), 'TRANSFER_IN', abs($smallQty), $referenceType, $referenceId, $notes, $userId, 'small_warehouse', 'big_warehouse', 'small_warehouse');

            return [$out, $in];
        });
    }

    public function adjustment(ConsumableItem $item, int $variance, string $referenceType, ?int $referenceId, ?string $notes = null, ?int $userId = null): StockMovement
    {
        return $this->move($item, 'ADJUSTMENT', $variance, $referenceType, $referenceId, $notes, $userId, 'big_warehouse');
    }

    private function move(
        ConsumableItem $item,
        string $type,
        int $delta,
        string $referenceType,
        ?int $referenceId,
        ?string $notes,
        ?int $userId,
        string $stockLocation,
        ?string $sourceStockLocation = null,
        ?string $destinationStockLocation = null
    ): StockMovement
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('Qty mutasi stok tidak boleh 0.');
        }

        return DB::transaction(function () use ($item, $type, $delta, $referenceType, $referenceId, $notes, $userId, $stockLocation, $sourceStockLocation, $destinationStockLocation) {
            $locked = ConsumableItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $stockColumn = $stockLocation === 'small_warehouse' ? 'small_stock' : 'current_stock';
            $before = (int) $locked->{$stockColumn};
            $after = $before + $delta;

            if ($after < 0) {
                throw new InvalidArgumentException('Stok tidak mencukupi. Mutasi stok minus tidak diizinkan.');
            }

            $locked->update([$stockColumn => $after]);

            return StockMovement::create([
                'item_id' => $locked->id,
                'movement_type' => $type,
                'stock_location' => $stockLocation,
                'source_stock_location' => $sourceStockLocation,
                'destination_stock_location' => $destinationStockLocation,
                'qty' => abs($delta),
                'balance_before' => $before,
                'balance_after' => $after,
                'balance_uom' => $stockLocation === 'small_warehouse' ? $locked->small_uom : $locked->large_uom,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }
}
