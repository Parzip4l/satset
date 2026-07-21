<?php

namespace App\Models\Master;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProcurementReceiving extends Model
{
    protected $fillable = [
        'reference_number',
        'vendor_name',
        'po_number',
        'do_number',
        'gr_number',
        'status',
        'received_date',
        'scheduled_delivery_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'scheduled_delivery_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(ProcurementReceivingItem::class, 'receiving_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
