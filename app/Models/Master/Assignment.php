<?php 

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Master\Ticket;
use App\Models\User;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = ['request_id', 'assignee_user_id ', 'assignee_department_id','assigned_at','accepted_at','completed_at','notes'];

    public function request()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
