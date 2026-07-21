<?php

namespace App\Models\Master;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id',
        'movement_type',
        'qty',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    public function item()
    {
        return $this->belongsTo(ConsumableItem::class, 'item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
