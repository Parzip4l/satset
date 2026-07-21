<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class ProcurementReceivingItem extends Model
{
    protected $fillable = [
        'receiving_id',
        'item_id',
        'qty_ordered',
        'qty_received',
        'qty_rejected',
        'notes',
    ];

    public function receiving()
    {
        return $this->belongsTo(ProcurementReceiving::class, 'receiving_id');
    }

    public function item()
    {
        return $this->belongsTo(ConsumableItem::class, 'item_id');
    }
}
