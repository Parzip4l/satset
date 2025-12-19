<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class DepartmentCategory extends Model
{
    use HasFactory;
    protected $table="category_department";
    protected $fillable = ['category_id', 'department_id'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function category()
    {
        return $this->belongsTo(ProblemCategory::class);
    }
}
