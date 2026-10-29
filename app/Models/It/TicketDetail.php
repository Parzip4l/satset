<?php

namespace App\Models\It;

use App\Models\Master\Ticket;
use Illuminate\Database\Eloquent\Model;

class TicketDetail extends Model
{
    protected $table = 'it_ticket_details';

    protected $fillable = ['request_id', 'record_type', 'support_team_id', 'status', 'is_restricted'];

    protected $casts = ['is_restricted' => 'boolean', 'form_answers' => 'array'];

    public function configurationVersion()
    {
        return $this->belongsTo(ConfigurationVersion::class, 'configuration_version_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'request_id');
    }

    public function supportTeam()
    {
        return $this->belongsTo(SupportTeam::class);
    }
}
