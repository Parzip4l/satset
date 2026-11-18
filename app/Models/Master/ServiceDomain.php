<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class ServiceDomain extends Model
{
    protected $fillable = ['name', 'description', 'icon', 'sort_order', 'is_active', 'sppd_policy_url'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];
}
