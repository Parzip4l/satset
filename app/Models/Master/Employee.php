<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

use App\Models\Master\Divisions;
use App\Models\User;

class Employee extends Model
{
    protected $table = 'employee';
    protected $fillable = [
        'user_id', 'nik', 'nama_lengkap', 'jabatan', 'division_id', 'is_pic'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function division() {
        return $this->belongsTo(Divisions::class);
    }
}
