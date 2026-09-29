<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

use App\Models\Master\Divisions;
use App\Models\Setting\Role;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Kolom tambahan yang tidak selalu ada di semua database lama.
     *
     * @var array<int, string>
     */
    private const OPTIONAL_COLUMNS = [
        'user_type',
        'nik',
        'phone',
        'kartu_uang_1',
        'kartu_uang_2',
        'role',
        'role_id',
        'division_id',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'remember_token',
        'role_id',
        'role',
        'phone'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            foreach (self::OPTIONAL_COLUMNS as $column) {
                if (
                    array_key_exists($column, $user->attributes)
                    && ! Schema::hasColumn($user->getTable(), $column)
                ) {
                    unset($user->attributes[$column]);
                }
            }
        });
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function division()
    {
        return $this->belongsTo(Divisions::class);
    }
}
