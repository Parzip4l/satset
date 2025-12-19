<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Master\ProblemCategory;
use App\Models\Master\Approval;
use App\Models\Master\Assignment;
use App\Models\Master\Priority;
use App\Models\Master\Status;
use App\Models\Master\Impact;
use App\Models\Master\Urgency;

class TicketHistory extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'status_id', 'action'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
