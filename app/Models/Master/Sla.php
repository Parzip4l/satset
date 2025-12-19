<?php 

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Master\ProblemCategory;

class Sla extends Model
{
    use HasFactory;

    protected $fillable = ['problem_category_id', 'response_time', 'resolution_time','name','response_time_minutes','resolve_time_minutes'];

    public function category()
    {
        return $this->belongsTo(ProblemCategory::class, 'problem_category_id');
    }
}
