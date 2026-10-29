<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\It\ConfigurationVersion;
use App\Services\It\ConfigurationSchema;
use App\Services\It\OperationalProfile;
use App\Services\It\SlaCalendar;
use App\Support\ItAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SlaPreviewController extends Controller
{
    public function __invoke(Request $r, ConfigurationVersion $version, SlaCalendar $calendar)
    {
        abort_unless(ItAccess::can($r->user(), 'manage_it_configuration') || ItAccess::can($r->user(), 'publish_it_configuration'), 403);
        $data = $r->validate(['catalog_key' => 'required|string|max:50', 'priority' => 'required|in:P1,P2,P3,P4', 'started_at' => 'required|date']);
        $cfg = app(ConfigurationSchema::class)->validate($version->configuration);
        abort_unless($cfg['schema_version'] === 3, 409, 'Profil SLA belum tersedia.');
        $item = collect($cfg['catalog'])->firstWhere('key', $data['catalog_key']);
        abort_unless($item, 422, 'Katalog tidak ditemukan.');
        $context = ['catalog_key' => $item['key'], 'service_key' => $item['service_key'], 'category_key' => $item['category_key'], 'record_type' => $item['record_type']];
        $sla = $cfg['profiles']['sla'];
        $policy = null;
        foreach ($sla['policies'] as $p) {
            if (app(OperationalProfile::class)->matches($p, $context)) {
                $policy = $p;
                break;
            }
        }
        $policy ??= collect($sla['policies'])->firstWhere('key', $sla['fallback'][$item['record_type']] ?? null);
        if (! $policy) {
            throw ValidationException::withMessages(['profiles.sla' => 'Lengkapi kebijakan dan simpan draft sebelum simulasi.']);
        }
        $cal = collect($sla['calendars'])->firstWhere('key', $policy['calendar_key']);
        $start = CarbonImmutable::parse($data['started_at'])->utc();
        $clocks = [];
        foreach (['response', $item['record_type'] === 'incident' ? 'restore' : 'fulfil'] as $kind) {
            $minutes = (int) $policy['targets'][$data['priority']][$kind];
            $clocks[] = ['kind' => $kind, 'target_minutes' => $minutes, 'due_at' => $calendar->deadline($cal, $start, $minutes * 60)->toIso8601String(), 'pause_codes' => $policy['pause'][$kind]];
        }

        return response()->json(['persisted' => false, 'version_id' => $version->id, 'policy' => $policy['key'], 'calendar' => $cal['name'], 'timezone' => $cal['timezone'], 'started_at' => $start->toIso8601String(), 'clocks' => $clocks]);
    }
}
