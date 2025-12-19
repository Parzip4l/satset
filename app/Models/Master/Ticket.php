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
use App\Models\Master\Department;
use App\Models\Master\Comment;

class Ticket extends Model
{
    use HasFactory;
    protected $table = "requests";

    protected $fillable = [
        'ticket_no',
        'requester_id',
        'department_id',
        'category_id',
        'title',
        'description',
        'priority_id',
        'impact_id',
        'urgency_id',
        'status_id',
        'resolved_at',
        'closed_at',
        'ticket_category_id'
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProblemCategory::class, 'category_id');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'request_id', 'id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function impact(): BelongsTo
    {
        return $this->belongsTo(Impact::class, 'impact_id');
    }

    public function urgency(): BelongsTo
    {
        return $this->belongsTo(Urgency::class, 'urgency_id');
    }

    public function histories()
    {
        return $this->hasMany(TicketHistory::class, 'ticket_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedDepartment()
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'request_id', 'id');
    }

    public function categoryticket()
    {
        return $this->belongsTo(\App\Models\Master\TicketCategory::class, 'ticket_category_id');
    }

}
