<?php 

// app/Models/Urgency.php
namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Urgency extends Model
{
    protected $fillable = ['name', 'code'];

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }
}
