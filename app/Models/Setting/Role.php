<?php

namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Model;
use App\Model\General\Menu;

class Role extends Model
{
    protected $fillable = ['name'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
