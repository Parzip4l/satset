<?php 

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Master\Sla;
use App\Models\Master\Ticket;
use App\Models\Master\Department;

class ProblemCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name','parent_id', 'code','description'];

    public function requests()
    {
        return $this->hasMany(Ticket::class);
    }

    public function sla()
    {
        return $this->hasOne(Sla::class);
    }

    public function parent()
    {
       return $this->belongsTo(ProblemCategory::class, 'parent_id');
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
