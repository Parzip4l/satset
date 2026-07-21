<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class TicketFormSchema extends Model
{
    protected $fillable = [
        'ticket_category_id',
        'schema',
    ];

    protected $casts = [
        'schema' => 'array',
    ];

    public function ticketCategory()
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    protected function schema(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // Decode JSON asli dulu jika $value masih raw string
                $data = is_array($value) ? $value : json_decode($value, true);

                // Lakukan mapping
                return array_map(function ($item) {
                    if (($item['type'] ?? '') === 'select' && !empty($item['options']) && is_string($item['options'])) {
                        $item['options'] = array_map('trim', explode(',', $item['options']));
                    }
                    return $item;
                }, $data ?: []);
            }
        );
    }
}
