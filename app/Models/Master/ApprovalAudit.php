<?php

namespace App\Models\Master;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalAudit extends Model
{
    protected $fillable = [
        'approval_id',
        'ticket_id',
        'approver_id',
        'source',
        'status',
        'comment',
        'satset_reference_id',
        'external_reference_id',
        'approver_email',
        'approver_name',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
