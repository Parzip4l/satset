<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\It\ConfigurationVersion;
use App\Models\It\SupportTeam;
use App\Models\User;
use App\Services\It\ConfigurationSchema;
use App\Services\It\ConfigurationStore;
use App\Services\It\FormEngine;
use App\Support\ItAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigurationController extends Controller
{
    private function access(Request $request, ?string $ability = null): void
    {
        abort_unless($ability ? ItAccess::can($request->user(), $ability) : (ItAccess::can($request->user(), 'manage_it_configuration') || ItAccess::can($request->user(), 'publish_it_configuration')), 403);
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('it_configuration_sets'), 503, 'Fondasi konfigurasi IT belum diterapkan.');
    }

    public function index(Request $request)
    {
        $this->access($request);
        $set = DB::table('it_configuration_sets')->where('code', 'it')->first();
        abort_unless($set, 503, 'Jalankan inisialisasi konfigurasi IT terlebih dahulu.');
        $versions = ConfigurationVersion::where('configuration_set_id', $set->id)->orderByDesc('number')->get();
        $events = DB::table('it_configuration_events')
            ->leftJoin('users', 'users.id', '=', 'it_configuration_events.actor_id')
            ->where('it_configuration_events.configuration_set_id', $set->id)
            ->orderByDesc('it_configuration_events.id')
            ->limit(30)
            ->get(['it_configuration_events.*', 'users.name as actor_name']);

        return view('it.configuration.index', compact('set', 'versions', 'events'));
    }

    public function show(Request $request, ConfigurationVersion $version)
    {
        $this->access($request);
        $set = DB::table('it_configuration_sets')->where('id', $version->configuration_set_id)->first();
        $users = User::orderBy('name')->get(['id', 'name']);
        $teams = SupportTeam::orderBy('name')->get(['id', 'name', 'is_active']);

        return view('it.configuration.editor', compact('version', 'set', 'users', 'teams'));
    }

    public function draft(Request $request, ConfigurationStore $store)
    {
        $this->access($request, 'manage_it_configuration');
        $data = $request->validate(['source_version_id' => 'nullable|integer|exists:it_configuration_versions,id', 'reason' => 'required|string|min:5|max:1000']);
        $version = $store->draft($request->user(), $data['reason'], $data['source_version_id'] ?? null);

        return redirect()->route('it.configuration.show', $version)->with('success', 'Draft baru dibuat dari snapshot versi sumber.');
    }

    public function update(Request $request, ConfigurationVersion $version, ConfigurationStore $store)
    {
        $this->access($request, 'manage_it_configuration');
        $data = $request->validate(['lock_version' => 'required|integer|min:0', 'reason' => 'required|string|min:5|max:1000']);
        $configuration = $request->input('configuration');
        if (is_string($configuration) && strlen($configuration) <= 262144) {
            try {
                $configuration = json_decode($configuration, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $configuration = null;
            }
        }
        if (! is_array($configuration) || strlen(json_encode($configuration)) > 262144) {
            throw ValidationException::withMessages(['configuration' => 'Bundle harus valid dan maksimal 256 KB.']);
        }
        $saved = $store->save($version->id, $configuration, (int) $data['lock_version'], $request->user(), $data['reason']);
        if ($request->expectsJson()) {
            return response()->json(['version_id' => $saved->id, 'lock_version' => $saved->lock_version, 'checksum' => $saved->checksum]);
        }

        return redirect()->route('it.configuration.show', $saved)->with('success', 'Draft tersimpan. Versi aktif dan tiket lama tidak berubah.');
    }

    public function publish(Request $request, ConfigurationVersion $version, ConfigurationStore $store)
    {
        return $this->activate($request, $version, $store, false);
    }

    public function rollback(Request $request, ConfigurationVersion $version, ConfigurationStore $store)
    {
        return $this->activate($request, $version, $store, true);
    }

    private function activate(Request $request, ConfigurationVersion $version, ConfigurationStore $store, bool $rollback)
    {
        $this->access($request, 'publish_it_configuration');
        $data = $request->validate(['set_revision' => 'required|integer|min:0', 'lock_version' => 'required|integer|min:0', 'reason' => 'required|string|min:5|max:1000']);
        $store->activate($version->id, (int) $data['set_revision'], (int) $data['lock_version'], $request->user(), $data['reason'], $rollback);
        if ($request->expectsJson()) {
            return response()->json(['active_version_id' => $version->id, 'submission_enabled' => false]);
        }

        return redirect()->route('it.configuration.index')->with('success', $rollback ? 'Versi sebelumnya diaktifkan. Tiket lama tetap memakai snapshot awal.' : 'Konfigurasi dipublikasikan. Pengajuan IT tetap tertutup sampai fase workflow dan SLA.');
    }

    public function preview(Request $request, ConfigurationVersion $version, FormEngine $engine)
    {
        $this->access($request);
        $data = $request->validate(['form_key' => 'required|string|max:50', 'role' => 'required|in:requester,technician,approver', 'answers' => 'sometimes|array|max:60', 'validate' => 'sometimes|boolean']);
        $configuration = app(ConfigurationSchema::class)->validate($version->configuration);
        $result = $engine->inspect($configuration, $data['form_key'], $data['role'], $data['answers'] ?? [], $request->file('uploads', []), (bool) ($data['validate'] ?? false));
        $result['version_id'] = $version->id;
        $result['simulation'] = ['workflow' => 'Belum dikonfigurasi — Fase 3', 'routing' => 'Belum dikonfigurasi — Fase 3', 'approval' => 'Belum dikonfigurasi — Fase 3', 'sla' => 'Belum dikonfigurasi — Fase 4', 'submission_enabled' => false];

        if (($configuration['schema_version'] ?? 1) >= 2) {
            $result['simulation']['workflow'] = 'Incident / Service Request — it_v1';
            $result['simulation']['routing'] = count($configuration['profiles']['routing']).' aturan berurutan + fallback layanan';
            $result['simulation']['approval'] = count($configuration['profiles']['approval']).' aturan Service Request. Resolver dijalankan saat submit';
        }

        if (($configuration['schema_version'] ?? 1) === 3) {
            $result['simulation']['sla'] = count($configuration['profiles']['sla']['calendars']).' kalender / '.count($configuration['profiles']['sla']['policies']).' kebijakan. Snapshot dan clock terpisah';
        }

        return response()->json($result, count($result['errors']) ? 422 : 200);
    }
}
