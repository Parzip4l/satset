<?php

namespace App\Services\It;

use App\Models\It\ConfigurationVersion;
use App\Models\User;
use App\Support\ServiceDomains;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Fail closed if any pilot scope or operational prerequisite changes. */
class PilotGate
{
    public function readiness(): array
    {
        $issues = [];
        $cfg = config('satset.it.pilot', []);
        $version = null;
        if (! Schema::hasTable('it_configuration_sets')) {
            return ['ready' => false, 'issues' => ['Konfigurasi IT belum tersedia.']];
        }
        $id = DB::table('it_configuration_sets')->where('code', 'it')->value('active_version_id');
        if (! $id || ! $cfg['version_id'] || (int) $id !== (int) $cfg['version_id']) {
            $issues[] = 'Versi aktif belum sesuai versi pilot yang disetujui.';
        }
        if ($id) {
            $version = ConfigurationVersion::find($id);
            if (! $version || $version->state !== 'published' || ($version->configuration['schema_version'] ?? 0) !== 3) {
                $issues[] = 'Pilot membutuhkan konfigurasi SLA schema 3 yang dipublikasikan.';
            } elseif (! hash_equals($version->checksum, ConfigurationStore::checksum($version->configuration))) {
                $issues[] = 'Checksum konfigurasi tidak cocok.';
            } else {
                try {
                    app(ConfigurationSchema::class)->validate($version->configuration, true);
                } catch (Throwable $e) {
                    $issues[] = 'Konfigurasi operasional tidak lagi memenuhi validasi publikasi.';
                }
            }
        }
        $users = array_values(array_unique($cfg['user_ids'] ?? []));
        $catalogs = array_values(array_unique($cfg['catalog_keys'] ?? []));
        if (! $users || count($users) > 100 || count(array_filter($users, fn ($id) => is_int($id) && $id > 0)) !== count($users)) {
            $issues[] = 'Daftar peserta pilot harus berisi 1–100 ID pengguna valid.';
        } elseif (DB::table('users')->whereIn('id', $users)->count() !== count($users)) {
            $issues[] = 'Ada peserta pilot yang tidak ditemukan.';
        }
        if (! $catalogs || count($catalogs) > 20) {
            $issues[] = 'Daftar katalog pilot harus berisi 1–20 item.';
        }
        if ($version && ($version->configuration['schema_version'] ?? 0) === 3) {
            $items = collect($version->configuration['catalog'])->where('is_active', true)->keyBy('key');
            foreach ($catalogs as $key) {
                if (! $items->has($key)) {
                    $issues[] = 'Katalog pilot tidak aktif atau tidak ditemukan: '.$key;
                }
            }
            $selected = $items->only($catalogs);
            foreach ($selected as $item) {
                $service = collect($version->configuration['services'])->firstWhere('key', $item['service_key']);
                $teamIds = array_filter([$service['fallback_team_id'] ?? null]);
                foreach ($version->configuration['profiles']['routing'] as $rule) {
                    if (app(OperationalProfile::class)->matches($rule, ['catalog_key' => $item['key'], 'service_key' => $item['service_key'], 'category_key' => $item['category_key'], 'record_type' => $item['record_type']])) {
                        $teamIds[] = $rule['team_id'];
                    }
                }
                foreach (array_unique($teamIds) as $team) {
                    $coordinators = DB::table('support_team_members')->where('support_team_id', $team)->where('role', 'coordinator')->pluck('user_id');
                    if (($item['is_restricted'] ?? false)) {
                        $coordinators = $coordinators->filter(fn ($userId) => DB::table('it_user_capabilities')->where('user_id', $userId)->where('capability', 'view_restricted_it')->exists());
                    }
                    if ($coordinators->isEmpty()) {
                        $issues[] = 'Tim pilot membutuhkan koordinator yang berhak menangani katalog '.$item['key'].'.';
                    }
                }
                if ($item['record_type'] === 'service_request') {
                    foreach ($version->configuration['profiles']['approval'] as $rule) {
                        if (app(OperationalProfile::class)->matches($rule, ['catalog_key' => $item['key'], 'service_key' => $item['service_key'], 'category_key' => $item['category_key'], 'record_type' => $item['record_type']])) {
                            if ($rule['resolver'] === 'portal' && ! config('satset.approval_resolver.shared_secret')) {
                                $issues[] = 'Resolver approval Portal belum dikonfigurasi.';
                            }
                            if ($rule['resolver'] === 'user' && in_array((int) $rule['approver_id'], $users, true)) {
                                $issues[] = 'Approver tetap tidak boleh menjadi peserta pemohon untuk katalog yang sama.';
                            }
                            break;
                        }
                    }
                }
            }
            $types = $items->only($catalogs)->pluck('record_type')->unique()->all();
            if (! in_array('incident', $types, true) || ! in_array('service_request', $types, true)) {
                $issues[] = 'Pilot harus mencakup Incident dan Service Request.';
            }
        }
        if (! ($cfg['business_approved'] ?? false)) {
            $issues[] = 'Persetujuan cakupan, tim dan target SLA belum dicatat.';
        }
        if (! ($cfg['uat_approved'] ?? false)) {
            $issues[] = 'UAT pengguna belum disetujui.';
        }
        if (! ($cfg['restore_verified'] ?? false)) {
            $issues[] = 'Backup dan restore untuk cutover belum diverifikasi.';
        }
        if (! ($cfg['support_ready'] ?? false)) {
            $issues[] = 'Kesiapan support dan rollback belum dicatat.';
        }
        try {
            if (! (app(OperationHealth::class)->report()['ready'] ?? false)) {
                $issues[] = 'Scheduler, worker atau antrean IT belum sehat.';
            }
        } catch (Throwable $e) {
            $issues[] = 'Status operasional IT tidak tersedia.';
        }

        return ['ready' => ! $issues, 'issues' => array_values(array_unique($issues)), 'version_id' => $id, 'participant_count' => count($users), 'catalog_keys' => $catalogs];
    }

    public function allowed(?User $user, ?string $catalog = null): bool
    {
        if (! ServiceDomains::itActive() || ! config('satset.it.requests_enabled', false)) {
            return false;
        }
        if (! config('satset.it.pilot.required', true)) {
            return app()->environment('testing');
        }
        if (! $user || ! in_array((int) $user->id, config('satset.it.pilot.user_ids', []), true)) {
            return false;
        }
        if ($catalog !== null && ! in_array($catalog, config('satset.it.pilot.catalog_keys', []), true)) {
            return false;
        }

        return $this->readiness()['ready'];
    }
}
