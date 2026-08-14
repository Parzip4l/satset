<?php

namespace App\Models\Master;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = ['request_id', 'approver_id', 'status', 'note', 'notes', 'level', 'decided_at', 'last_action_source', 'portal_reference_id'];

    public function request()
    {
        return $this->belongsTo(Ticket::class, 'request_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function audits()
    {
        return $this->hasMany(ApprovalAudit::class);
    }
}
