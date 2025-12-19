<?php

namespace App\Models\General;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penetrasi extends Model
{
    use HasFactory;
    protected $table = 'penetrasi';
    protected $fillable = ['id'];
}
