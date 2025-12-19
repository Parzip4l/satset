<?php
namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Master\Ticket;
use App\Models\User;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = ['request_id', 'approver_id', 'status', 'note','level','decided_at'];

    public function request()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
