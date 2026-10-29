<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProblemCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'code', 'description'];

    public function requests()
    {
        return $this->hasMany(Ticket::class);
    }

    public function sla()
    {
        return $this->hasOneThrough(Sla::class, RoutingRule::class, 'category_id', 'id', 'id', 'sla_id');
    }

    public function parent()
    {
        return $this->belongsTo(ProblemCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ProblemCategory::class, 'parent_id');
    }

    public function departments()
    {
        return $this->belongsToMany(
            Department::class,
            'category_department',
            'category_id',
            'department_id'
        );
    }
}
