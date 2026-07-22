<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id',
        'item_id',
        'stock_location',
        'system_stock',
        'physical_stock',
        'variance',
        'notes',
    ];

    public function opname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function item()
    {
        return $this->belongsTo(ConsumableItem::class, 'item_id');
    }
}
