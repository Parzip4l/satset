<?php

namespace App\Models\It;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $table = 'it_assets';

    protected $fillable = ['asset_tag', 'name', 'type_id', 'location_id', 'assigned_employee_id', 'brand', 'model', 'serial_number', 'acquired_on', 'purchase_price', 'expires_on', 'status', 'notes', 'specifications', 'lock_version', 'created_by'];

    protected function casts(): array
    {
        return ['acquired_on' => 'date', 'expires_on' => 'date', 'purchase_price' => 'decimal:2', 'lock_version' => 'integer', 'specifications' => 'array'];
    }

    public function type()
    {
        return $this->belongsTo(AssetType::class, 'type_id');
    }

    public function location()
    {
        return $this->belongsTo(AssetLocation::class, 'location_id');
    }

    public function assignee()
    {
        return $this->belongsTo(AssetEmployee::class, 'assigned_employee_id');
    }

    public function histories()
    {
        return $this->hasMany(AssetHistory::class, 'asset_id');
    }

    public function getAgeLabelAttribute(): string
    {
        if (! $this->acquired_on) {
            return 'Belum diketahui';
        }
        $age = $this->acquired_on->diff(now()->startOfDay());
        if ($age->y > 0) {
            return $age->y.' tahun'.($age->m > 0 ? ' '.$age->m.' bulan' : '');
        }

        return $age->m > 0 ? $age->m.' bulan' : $age->d.' hari';
    }
}
