<?php

namespace App\Models\It;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SupportTeam extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'support_team_members')->withPivot('role')->withTimestamps();
    }
}
