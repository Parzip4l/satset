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
        'unit_price',
        'minimum_stock',
        'buffer_stock',
        'current_stock',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unit_price' => 'decimal:2',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }
}
