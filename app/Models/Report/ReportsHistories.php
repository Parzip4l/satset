<?php

namespace App\Models\Report;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\User;
use App\Models\Report\Reports;


class ReportsHistories extends Model
{
    use HasFactory;
    protected $table = 'report_histories';
    protected $fillable = [
        'report_id', 'user_id', 'tipe', 'catatan', 'tanggal'
    ];

    public function report() { return $this->belongsTo(Reports::class); }
    public function user() { return $this->belongsTo(User::class); }
}
