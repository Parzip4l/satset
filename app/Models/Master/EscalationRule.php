<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Master\Sla;

class EscalationRule extends Model
{
    use HasFactory;

    protected $fillable = ['sla_id', 'escalate_to_role', 'time_limit'];

    public function sla()
    {
        return $this->belongsTo(Sla::class);
    }
}
