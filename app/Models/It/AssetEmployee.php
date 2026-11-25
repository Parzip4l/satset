<?php

namespace App\Models\It;

use Illuminate\Database\Eloquent\Model;

class AssetEmployee extends Model
{
    protected $table = 'it_asset_employees';

    protected $fillable = ['portal_id', 'name', 'email', 'employee_number', 'department', 'is_active', 'synced_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'synced_at' => 'datetime'];
    }
}
