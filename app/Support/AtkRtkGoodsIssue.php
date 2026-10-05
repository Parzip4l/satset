<?php

namespace App\Support;

use Illuminate\Support\Collection;

class AtkRtkGoodsIssue
{
    /**
     * A request is considered bulk when at least one requested item reaches
     * one complete large-warehouse UOM.
     */
    public static function isBulk(array $payload): bool
    {
        return self::items($payload)->contains(
            fn (array $item) => $item['quantity'] >= $item['conversion_qty']
        );
    }

    /**
     * Normalize current multi-item payloads and legacy single-item payloads.
     */
    public static function items(array $payload): Collection
    {
        $items = collect($payload['items'] ?? []);

        if ($items->isEmpty() && ! empty($payload['item_id'])) {
            $items = collect([[
                'item_id' => $payload['item_id'],
                'item_code' => $payload['item_code'] ?? null,
                'item_name' => $payload['item_name'] ?? null,
                'quantity' => $payload['fulfilled_qty'] ?? $payload['quantity'] ?? 0,
                'small_uom' => $payload['small_uom'] ?? null,
                'large_uom' => $payload['large_uom'] ?? null,
                'conversion_qty' => $payload['conversion_qty'] ?? 1,
            ]]);
        }

        return $items
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return array_merge($item, [
                    'quantity' => max(0, (int) ($item['quantity'] ?? 0)),
                    'conversion_qty' => max(1, (int) ($item['conversion_qty'] ?? 1)),
                ]);
            })
            ->filter(fn (array $item) => ! empty($item['item_id']) && $item['quantity'] > 0)
            ->values();
    }
}
