<?php

namespace App\Models\Report;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Models\Report\Reports;

class ReportFollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'pic_id',
        'description',
        'attachment',
        'reviewed_by',
        'reviewed_at',
        'is_approved',
        'note',
    ];

    // Relasi ke laporan
    public function report()
    {
        return $this->belongsTo(Reports::class);
    }

    // PIC yang input tindak lanjut
    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    // QSHE yang review TL
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
