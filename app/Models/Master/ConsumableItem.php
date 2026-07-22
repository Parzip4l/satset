<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumableItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'large_uom',
        'small_uom',
        'conversion_qty',
        'unit_price',
        'minimum_stock',
        'buffer_stock',
        'current_stock',
        'small_stock',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unit_price' => 'decimal:2',
        'conversion_qty' => 'integer',
        'current_stock' => 'integer',
        'small_stock' => 'integer',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }
}
