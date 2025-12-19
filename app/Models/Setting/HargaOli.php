<?php

namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Model;

class HargaOli extends Model
{
    protected $table = 'setting_oli';
    protected $fillable = [
        'jenis_oli',
        'harga',
        'updated_by',
    ];
}
