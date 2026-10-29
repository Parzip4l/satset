<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sla extends Model
{
    use HasFactory;

    protected $table = 'sla';

    protected $fillable = ['name', 'response_time_minutes', 'resolve_time_minutes', 'business_hours'];

    protected $casts = ['business_hours' => 'boolean', 'response_time_minutes' => 'integer', 'resolve_time_minutes' => 'integer'];

    public function routingRules()
    {
        return $this->hasMany(RoutingRule::class, 'sla_id');
    }
}
