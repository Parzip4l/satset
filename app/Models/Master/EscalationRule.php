<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscalationRule extends Model
{
    use HasFactory;

    protected $fillable = ['sla_id', 'threshold_minutes', 'escalate_to_user_id', 'escalate_to_department_id', 'notify_roles'];

    public function sla()
    {
        return $this->belongsTo(Sla::class);
    }
}
