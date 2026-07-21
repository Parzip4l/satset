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
        return $this->move($item, 'IN', abs($qty), $referenceType, $referenceId, $notes, $userId);
    }

    public function decrease(ConsumableItem $item, int $qty, string $referenceType, ?int $referenceId, ?string $notes = null, ?int $userId = null): StockMovement
    {
        return $this->move($item, 'OUT', -abs($qty), $referenceType, $referenceId, $notes, $userId);
    }

    public function adjustment(ConsumableItem $item, int $variance, string $referenceType, ?int $referenceId, ?string $notes = null, ?int $userId = null): StockMovement
    {
        return $this->move($item, 'ADJUSTMENT', $variance, $referenceType, $referenceId, $notes, $userId);
    }

    private function move(ConsumableItem $item, string $type, int $delta, string $referenceType, ?int $referenceId, ?string $notes, ?int $userId): StockMovement
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('Qty mutasi stok tidak boleh 0.');
        }

        return DB::transaction(function () use ($item, $type, $delta, $referenceType, $referenceId, $notes, $userId) {
            $locked = ConsumableItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->current_stock;
            $after = $before + $delta;

            if ($after < 0) {
                throw new InvalidArgumentException('Stok tidak mencukupi. Mutasi stok minus tidak diizinkan.');
            }

            $locked->update(['current_stock' => $after]);

            return StockMovement::create([
                'item_id' => $locked->id,
                'movement_type' => $type,
                'qty' => abs($delta),
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }
}
