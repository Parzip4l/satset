<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Master\Ticket;

class Comment extends Model
{
    use HasFactory;
    protected $table="comments";
    protected $fillable = ['request_id', 'user_id', 'message'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'request_id', 'id');
    }
}
