<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'ticket_no'      => $this->ticket_no,
            'title'          => $this->title,
            'description'    => $this->description,
            'created_at'     => $this->created_at->format('Y-m-d H:i'), 
            
            'requester_name' => $this->requester->name ?? '-',
            
            'status'         => $this->status->name ?? 'Unknown',
            'status_color'   => $this->status->color ?? '#000000',
            
            'priority'       => $this->priority->name ?? '-',
            
            'category'       => $this->category->name ?? '-',
            
            'department'     => $this->department->name ?? '-',
            
            'impact'         => $this->impact->name ?? '-',
            'urgency'        => $this->urgency->name ?? '-',
        ];
    }
}