<?php

namespace App\Services\It;

use App\Models\It\ConfigurationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ConfigurationStore
{
    public function __construct(private ConfigurationSchema $schema) {}

    public function draft(User $actor, string $reason, ?int $source = null): ConfigurationVersion
    {
        return DB::transaction(function () use ($actor, $reason, $source) {
            $set = DB::table('it_configuration_sets')->where('code', 'it')->lockForUpdate()->first();
            abort_unless($set, 503, 'Konfigurasi IT belum diinisialisasi.');
            $base = ConfigurationVersion::where('configuration_set_id', $set->id)->findOrFail($source ?? $set->active_version_id ?? ConfigurationVersion::where('configuration_set_id', $set->id)->max('id'));
            $number = ConfigurationVersion::where('configuration_set_id', $set->id)->max('number') + 1;
            $draft = ConfigurationVersion::create(['configuration_set_id' => $set->id, 'number' => $number, 'configuration' => $base->configuration, 'checksum' => $base->checksum, 'state' => 'draft', 'source_version_id' => $base->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $this->event($draft, $actor, 'draft_created', $reason, $base->checksum, ['source_version_id' => $base->id, 'after_configuration' => $draft->configuration]);

            return $draft;
        });
    }

    public function save(int $id, array $configuration, int $lock, User $actor, string $reason): ConfigurationVersion
    {
        return DB::transaction(function () use ($id, $configuration, $lock, $actor, $reason) {
            $version = ConfigurationVersion::lockForUpdate()->findOrFail($id);
            if ($version->state !== 'draft' || $version->lock_version !== $lock) {
                throw new ConflictHttpException('Draft berubah atau sudah dipublikasikan. Muat ulang sebelum menyimpan.');
            }
            $configuration = $this->schema->validate($configuration);
            $this->stableTypes($configuration, $version->configuration_set_id);
            $before = $version->checksum;
            $beforeConfiguration = $version->configuration;
            $version->fill(['configuration' => $configuration, 'checksum' => self::checksum($configuration), 'lock_version' => $lock + 1, 'updated_by' => $actor->id])->save();
            $changedSections = array_values(array_filter(array_keys($configuration), fn ($section) => ($beforeConfiguration[$section] ?? null) !== $configuration[$section]));
            $this->event($version, $actor, 'draft_saved', $reason, $before, ['changed_sections' => $changedSections, 'before_configuration' => $beforeConfiguration, 'after_configuration' => $configuration]);

            return $version;
        });
    }

    public function activate(int $id, int $setRevision, int $lock, User $actor, string $reason, bool $rollback = false): ConfigurationVersion
    {
        return DB::transaction(function () use ($id, $setRevision, $lock, $actor, $reason, $rollback) {
            $candidate = ConfigurationVersion::findOrFail($id);
            $set = DB::table('it_configuration_sets')->where('id', $candidate->configuration_set_id)->lockForUpdate()->first();
            $version = ConfigurationVersion::lockForUpdate()->findOrFail($id);
            if ((int) $set->revision !== $setRevision || $version->lock_version !== $lock) {
                throw new ConflictHttpException('Versi aktif/draft berubah. Muat ulang sebelum publikasi.');
            }
            if ($rollback ? $version->state !== 'published' : $version->state !== 'draft') {
                throw new ConflictHttpException('Aksi tidak sesuai status versi.');
            }
            if ((int) $set->active_version_id === $version->id) {
                throw new ConflictHttpException('Versi ini sudah aktif.');
            }
            $this->schema->validate($version->configuration, true);
            $this->stableTypes($version->configuration, $set->id);
            if (! hash_equals($version->checksum, self::checksum($version->configuration))) {
                throw new ConflictHttpException('Checksum versi tidak cocok.');
            }
            $previous = $set->active_version_id ? ConfigurationVersion::findOrFail($set->active_version_id)->checksum : null;
            if (! $rollback) {
                $version->fill(['state' => 'published', 'published_by' => $actor->id, 'published_at' => now(), 'lock_version' => $lock + 1])->save();
            }
            DB::table('it_configuration_sets')->where('id', $set->id)->update(['active_version_id' => $id, 'revision' => $setRevision + 1, 'updated_at' => now()]);
            $this->event($version, $actor, $rollback ? 'rollback_activated' : 'published', $reason, $previous, ['previous_version_id' => $set->active_version_id, 'set_revision' => $setRevision + 1]);

            return $version;
        });
    }

    private function stableTypes(array $configuration, int $setId): void
    {
        $types = [];
        foreach (ConfigurationVersion::where('configuration_set_id', $setId)->where('state', 'published')->get() as $version) {
            foreach ($version->configuration['forms'] as $form) {
                foreach ($form['fields'] as $field) {
                    $types[$form['key'].'.'.$field['key']] = $field['type'];
                }
            }
        }
        foreach ($configuration['forms'] as $form) {
            foreach ($form['fields'] as $field) {
                if (isset($types[$form['key'].'.'.$field['key']]) && $types[$form['key'].'.'.$field['key']] !== $field['type']) {
                    throw ValidationException::withMessages(['configuration' => 'Tipe field terpublikasi tidak boleh diganti. Gunakan kode field baru: '.$field['key']]);
                }
            }
        }
    }

    private function event(ConfigurationVersion $version, User $actor, string $action, string $reason, ?string $before, array $metadata = []): void
    {
        DB::table('it_configuration_events')->insert(['configuration_set_id' => $version->configuration_set_id, 'version_id' => $version->id, 'actor_id' => $actor->id, 'action' => $action, 'reason' => $reason, 'before_checksum' => $before, 'after_checksum' => $version->checksum, 'metadata' => json_encode($metadata), 'created_at' => now()]);
    }

    public static function checksum(array $data): string
    {
        $canonicalize = function ($value) use (&$canonicalize) {
            if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) <= 1000000000) {
                return (int) $value;
            }
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonicalize, $value);
        };

        return hash('sha256', json_encode($canonicalize($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }
}
