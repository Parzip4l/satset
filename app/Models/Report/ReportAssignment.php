<?php

namespace App\Models\Report;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Models\Report\Reports;
use App\Models\Master\Divisions;

class ReportAssignment extends Model
{
   use HasFactory;

    protected $fillable = [
        'report_id',
        'assigned_by',
        'assigned_to',
        'division_id',
        'is_agree',
        'note',
    ];

    // Relasi ke laporan
    public function report()
    {
        return $this->belongsTo(Reports::class);
    }

    // Relasi ke user yang meng-assign (QSHE)
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Relasi ke user yang menerima tugas (PIC)
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Divisi tujuan
    public function division()
    {
        return $this->belongsTo(Divisions::class);
    }
}
