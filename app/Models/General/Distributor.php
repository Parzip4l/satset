<?php

namespace App\Models\General;

use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    protected $table = 'distributor';
    protected $fillable = [
        'name',
        'email',
        'phone',
        'city',
        'province',
        'address_details',
        'category',
        'is_active',
        'notes',
    ];
}
