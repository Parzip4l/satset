<?php

namespace App\Models\It;

use Illuminate\Database\Eloquent\Model;

class AssetHistory extends Model
{
    protected $table = 'it_asset_histories';

    public $timestamps = false;

    protected $fillable = ['asset_id', 'actor_id', 'actor_name', 'sequence', 'action', 'reason', 'before_state', 'after_state', 'created_at'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'created_at' => 'datetime'];
    }
}
