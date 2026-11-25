<?php

namespace App\Models\It;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    protected $table = 'it_asset_types';

    protected $fillable = ['name', 'name_key'];
}
