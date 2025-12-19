<?php

namespace App\Models\Report;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Master\Divisions;
use App\Models\Master\Locations;
use App\Models\Master\Observation;
use App\Models\Master\Hazard;
use App\Models\Master\KategoriBahaya;
use App\Models\Report\ReportsHistories;
use App\Models\User;

class Reports extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nomor_laporan',
        'judul',
        'tanggal_laporan',
        'observation_type_id',
        'location_id',
        'detail_lokasi',
        'keterangan',
        'hazard_potential_id',
        'perlu_tindak_lanjut',
        'status',
        'division_id',
        'assigned_to',
        'foto',
        'bahaya_id'
    ];

    // Relasi ke pelapor
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke lokasi (master)
    public function location()
    {
        return $this->belongsTo(Locations::class);
    }

    // Relasi ke jenis pengamatan
    public function observationType()
    {
        return $this->belongsTo(Observation::class);
    }

    // Relasi ke potensi bahaya
    public function hazardPotential()
    {
        return $this->belongsTo(Hazard::class);
    }

    public function Bahaya()
    {
        return $this->belongsTo(KategoriBahaya::class);
    }

    // Relasi ke divisi
    public function division()
    {
        return $this->belongsTo(Divisions::class);
    }

    // Riwayat laporan
    public function histories()
    {
        return $this->hasMany(ReportsHistories::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function pelapor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
