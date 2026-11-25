<?php

namespace App\Models\It;

use Illuminate\Database\Eloquent\Model;

class AssetLocation extends Model
{
    protected $table = 'it_asset_locations';

    protected $fillable = ['name', 'name_key'];
}
