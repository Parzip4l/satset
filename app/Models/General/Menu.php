<?php

namespace App\Models\General;

use Illuminate\Database\Eloquent\Model;
use App\Models\Setting\Role;

class Menu extends Model
{
    protected $fillable = [
        'title', 'icon', 'url', 'parent_id', 'is_active', 'order'
    ];

    protected $casts = [
        'role_id' => 'array',
    ];

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_id');
    }
    

}
