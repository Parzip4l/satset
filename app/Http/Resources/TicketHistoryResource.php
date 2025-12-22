<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Logika untuk menentukan Marker (System vs User)
        // Sesuai view: {{ $history->user_id ? '' : 'system' }}
        $type = $this->user_id ? 'user' : 'system';

        return [
            'id'           => $this->id,
        
            'user_name'    => $this->user->name ?? 'System',
            
            // 2. Waktu (Format sesuai request: d M, H:i)
            'time_display' => $this->created_at->format('d M, H:i'),
            'timestamp'    => $this->created_at->toIso8601String(),
            
            // 3. Konten Action
            'action'       => $this->action,
            'status_info'  => $this->status ? [
                'has_changed' => true,
                'name'        => $this->status->name,
            ] : null,
        ];
    }
}