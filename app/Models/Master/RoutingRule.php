<?php 

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Master\ProblemCategory;

class RoutingRule extends Model
{
    use HasFactory;

    protected $fillable = ['problem_category_id', 'assigned_role'];

    public function category()
    {
        return $this->belongsTo(ProblemCategory::class, 'problem_category_id');
    }
}
