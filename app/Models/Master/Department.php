<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Department extends Model
{
    use HasFactory;
    protected $table="departments";
    protected $fillable = ['division_id', 'name', 'code','email'];

    public function division()
    {
        return $this->belongsTo(Divisions::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function categories()
    {
        return $this->belongsToMany(
            ProblemCategory::class,
            'category_department',
            'department_id',
            'category_id'
        );
    }
}
