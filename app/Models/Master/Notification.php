<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'title', 'message', 'url', 'is_read'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
