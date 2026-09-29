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

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = self::cleanName($value);
    }

    public static function cleanName(string $value): string
    {
        $cleaned = preg_replace('/\s*[\(\[]?\s*\d+\s*[a-z][a-z0-9\/.-]*\s*=\s*\d+\s*[a-z][a-z0-9\/.-]*\s*[\)\]]?/iu', '', $value);
        $cleaned = preg_replace('/\s+/', ' ', (string) $cleaned);

        return trim($cleaned, " \t\n\r\0\x0B,-–—;");
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }
}
