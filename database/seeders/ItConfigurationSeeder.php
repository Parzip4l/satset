<?php

namespace Database\Seeders;

use App\Models\It\ConfigurationVersion;
use App\Services\It\ConfigurationSchema;
use App\Services\It\ConfigurationStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItConfigurationSeeder extends Seeder
{
    public static function baseline(): array
    {
        $field = fn ($key, $label, $type, $extra = []) => array_merge(['key' => $key, 'label' => $label, 'type' => $type, 'required' => false, 'visible_roles' => ConfigurationSchema::ROLES, 'read_only_roles' => [], 'width' => 12, 'reportable' => false], $extra);
        $base = [$field('subject', 'Ringkasan kebutuhan', 'text', ['required' => true, 'max_length' => 150]), $field('details', 'Detail kebutuhan / gangguan', 'textarea', ['required' => true, 'max_length' => 5000, 'help' => 'Jelaskan kebutuhan tanpa menyertakan password, token, atau informasi rahasia.'])];
        $incident = array_merge($base, [
            $field('connection', 'Jenis koneksi', 'select', ['options' => [['value' => 'wifi', 'label' => 'Wi-Fi'], ['value' => 'lan', 'label' => 'LAN']]]),
            $field('ssid', 'Nama jaringan Wi-Fi', 'text', ['max_length' => 100, 'visible_when' => ['field' => 'connection', 'operator' => 'equals', 'value' => 'wifi']]),
            $field('occurred_at', 'Mulai terjadi', 'datetime'),
            $field('evidence', 'Bukti pendukung', 'file', ['max_files' => 3, 'max_size_kb' => 2048, 'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png']]),
            $field('internal_note', 'Catatan internal IT', 'textarea', ['visible_roles' => ['technician', 'approver'], 'max_length' => 2000]),
        ]);
        $request = array_merge($base, [$field('business_reason', 'Alasan bisnis', 'textarea', ['required' => true, 'max_length' => 2000]), $field('needed_date', 'Tanggal dibutuhkan', 'date'), $field('location', 'Lokasi', 'reference', ['source' => 'locations'])]);
        $categories = [];
        foreach (['hardware' => 'Hardware & Perangkat', 'network' => 'Jaringan & Koneksi', 'application' => 'Aplikasi & Sistem', 'access' => 'Akun & Hak Akses', 'software' => 'Software & Lisensi', 'infrastructure' => 'Server & Infrastruktur', 'security' => 'Keamanan Informasi'] as $key => $name) {
            $categories[] = ['key' => $key, 'name' => $name, 'parent_key' => null, 'is_active' => true];
        }
        $services = [];
        foreach (['workstation' => 'Perangkat Kerja', 'connectivity' => 'Koneksi & Infrastruktur', 'applications' => 'Aplikasi Perusahaan'] as $key => $name) {
            $services[] = ['key' => $key, 'name' => $name, 'description' => '', 'owner_id' => null, 'fallback_team_id' => null, 'is_active' => true];
        }
        $catalog = [];
        foreach ([['hardware_issue', 'Gangguan perangkat kerja', 'workstation', 'hardware', 'incident'], ['network_issue', 'Gangguan jaringan', 'connectivity', 'network', 'incident'], ['application_issue', 'Gangguan aplikasi', 'applications', 'application', 'incident'], ['access_request', 'Permintaan akun / akses', 'applications', 'access', 'service_request'], ['software_request', 'Permintaan software', 'workstation', 'software', 'service_request'], ['infrastructure_issue', 'Gangguan infrastruktur', 'connectivity', 'infrastructure', 'incident'], ['security_report', 'Laporan keamanan informasi', 'connectivity', 'security', 'incident']] as [$key, $name, $service, $category, $type]) {
            $catalog[] = ['key' => $key, 'name' => $name, 'description' => 'Template awal untuk ditinjau pengelola konfigurasi.', 'service_key' => $service, 'category_key' => $category, 'record_type' => $type, 'form_key' => $type === 'incident' ? 'incident_general' : 'request_general', 'is_active' => true];
        }

        return ['schema_version' => 1, 'services' => $services, 'categories' => $categories, 'catalog' => $catalog,
            'locations' => [], 'forms' => [['key' => 'incident_general', 'name' => 'Laporan Gangguan IT', 'record_type' => 'incident', 'fields' => $incident], ['key' => 'request_general', 'name' => 'Permintaan Layanan IT', 'record_type' => 'service_request', 'fields' => $request]], 'profiles' => ConfigurationSchema::PROFILES];
    }

    public function run(): void
    {
        DB::transaction(function () {
            if (DB::table('it_configuration_sets')->where('code', 'it')->exists()) {
                return;
            }
            $configuredActor = config('satset.it.bootstrap_actor_id');
            $actorId = filter_var($configuredActor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! $actorId && ($configuredActor === null || $configuredActor === '') && ! app()->environment('production')) {
                $actorId = 1;
            }
            abort_unless($actorId && DB::table('users')->where('id', $actorId)->exists(), 422, 'Tetapkan SATSET_IT_BOOTSTRAP_ACTOR_ID ke akun Satset yang telah diverifikasi sebelum membuat draft awal.');
            $configuration = app(ConfigurationSchema::class)->validate(self::baseline());
            $set = DB::table('it_configuration_sets')->insertGetId(['code' => 'it', 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $version = ConfigurationVersion::create(['configuration_set_id' => $set, 'number' => 1, 'state' => 'draft', 'configuration' => $configuration, 'checksum' => ConfigurationStore::checksum($configuration), 'created_by' => $actorId, 'updated_by' => $actorId]);
            DB::table('it_configuration_events')->insert(['configuration_set_id' => $set, 'version_id' => $version->id, 'actor_id' => $actorId, 'action' => 'initialized', 'reason' => 'Draft awal Fase 2; belum dipublikasikan dan tidak membuka pengajuan.', 'after_checksum' => $version->checksum, 'created_at' => now()]);
        });
    }
}
