<?php

namespace App\Models\General;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ListOrder extends Model
{
    use HasFactory;

    protected $table = 'list_orders';

    protected $fillable = [
        'tanggal_terima_order',
        'maks_kirim',
        'customer',
        'tujuan',
        'ppn',
        'no_so',
        'expedisi',
        'keterangan',
        'status',
    ];

    /**
     * Relationship with OrderItem.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'list_order_id');
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class, 'customer'); // 'customer' is the foreign key
    }
}
