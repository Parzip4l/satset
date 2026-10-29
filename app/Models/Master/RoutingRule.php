<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoutingRule extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'assignee_department_id', 'default_priority', 'sla_id'];

    public function category()
    {
        return $this->belongsTo(ProblemCategory::class, 'category_id');
    }

    public function sla()
    {
        return $this->belongsTo(Sla::class, 'sla_id');
    }
}
