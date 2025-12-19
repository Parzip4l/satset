<?php

namespace App\Models\General;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Product\Product;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'list_order_id',
        'nama_produk',
        'total_order',
        'jumlah_kirim',
        'sisa_belum_kirim',
        'tanggal_kirim',
        'sales',
    ];

    /**
     * Relationship with ListOrder.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'nama_produk', 'id'); // 'nama_produk' is the foreign key in order_items
    }

    // Define the relationship to ListOrder
    public function listOrder()
    {
        return $this->belongsTo(ListOrder::class);
    }

}
