<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'mime_type',
        'size',
        'attachment_type',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Ticket::class, 'request_id');
    }
}
