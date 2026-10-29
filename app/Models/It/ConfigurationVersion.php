<?php

namespace App\Models\It;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ConfigurationVersion extends Model
{
    protected $table = 'it_configuration_versions';

    protected $guarded = ['id'];

    protected $casts = ['configuration' => 'array', 'published_at' => 'datetime', 'lock_version' => 'integer'];

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->getRawOriginal('state') === 'published') {
                throw new LogicException('Versi terpublikasi tidak dapat diubah; buat draft baru.');
            }
        });
        static::deleting(fn () => throw new LogicException('Versi konfigurasi tidak dapat dihapus.'));
    }
}
