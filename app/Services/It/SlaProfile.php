<?php

namespace App\Services\It;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SlaProfile
{
    public const PAUSES = ['waiting_customer', 'waiting_vendor', 'waiting_internal', 'waiting_parts', 'scheduled_change', 'awaiting_approval', 'needs_revision'];

    public static function draft(): array
    {
        $weekly = [];
        foreach (range(1, 7) as $day) {
            $weekly[$day] = $day < 6 ? [['start' => '08:00', 'end' => '17:00']] : [];
        }

        return ['engine' => 'sla_v1', 'calendars' => [['key' => 'office', 'name' => 'Draft jam kerja — perlu ditinjau', 'timezone' => 'Asia/Jakarta', 'mode' => 'business', 'weekly' => $weekly, 'holidays' => [], 'exceptions' => []]], 'policies' => [], 'fallback' => ['incident' => null, 'service_request' => null], 'reopen_budget' => 'remaining'];
    }

    public function validate(array $sla, array $config, bool $publishing): void
    {
        Validator::make(['sla' => $sla], [
            'sla' => 'required|array:engine,calendars,policies,fallback,reopen_budget', 'sla.engine' => 'required|in:sla_v1', 'sla.reopen_budget' => 'required|in:remaining,full',
            'sla.calendars' => 'present|array|max:50', 'sla.calendars.*' => 'array:key,name,timezone,mode,weekly,holidays,exceptions',
            'sla.calendars.*.name' => 'required|string|max:150', 'sla.calendars.*.timezone' => 'required|timezone:all', 'sla.calendars.*.mode' => 'required|in:business,24x7',
            'sla.calendars.*.weekly' => 'present|array:1,2,3,4,5,6,7', 'sla.calendars.*.weekly.*' => 'array|max:4',
            'sla.calendars.*.holidays' => 'present|array|max:366', 'sla.calendars.*.holidays.*' => 'required|date_format:Y-m-d',
            'sla.calendars.*.exceptions' => 'present|array|max:366', 'sla.calendars.*.exceptions.*' => 'array:date,windows', 'sla.calendars.*.exceptions.*.date' => 'required|date_format:Y-m-d',
            'sla.calendars.*.exceptions.*.windows' => 'present|array|max:4',
            'sla.policies' => 'present|array|max:100', 'sla.policies.*' => 'array:key,name,record_type,catalog_key,service_key,category_key,calendar_key,targets,pause,warning_percent,escalations',
            'sla.policies.*.name' => 'required|string|max:150', 'sla.policies.*.record_type' => 'required|in:incident,service_request',
            'sla.policies.*.calendar_key' => 'required|string|max:50', 'sla.policies.*.targets' => 'required|array:P1,P2,P3,P4|size:4',
            'sla.policies.*.pause' => 'required|array:response,restore,fulfil', 'sla.policies.*.pause.*' => 'array|max:7', 'sla.policies.*.pause.*.*' => 'in:'.implode(',', self::PAUSES),
            'sla.policies.*.warning_percent' => 'required|integer|between:1,99', 'sla.policies.*.escalations' => 'present|array|max:10',
            'sla.policies.*.escalations.*' => 'array:key,at_percent,repeat_minutes,recipients,user_ids',
            'sla.policies.*.escalations.*.at_percent' => 'required|integer|between:1,500', 'sla.policies.*.escalations.*.repeat_minutes' => 'nullable|integer|between:15,1440',
            'sla.policies.*.escalations.*.recipients' => 'required|array|min:1|max:3', 'sla.policies.*.escalations.*.recipients.*' => 'in:assignee,coordinators,service_owner',
            'sla.policies.*.escalations.*.user_ids' => 'sometimes|array|max:20', 'sla.policies.*.escalations.*.user_ids.*' => 'integer|distinct|exists:users,id',
            'sla.fallback' => 'required|array:incident,service_request', 'sla.fallback.*' => 'nullable|string|max:50',
        ])->validate();
        $calendars = $this->unique($sla['calendars'], 'kalender');
        $policies = $this->unique($sla['policies'], 'kebijakan');
        foreach ($calendars as $c) {
            if (count($c['holidays']) !== count(array_unique($c['holidays']))) {
                $this->fail('Hari libur duplikat pada kalender.');
            }
            $minutes = 0;
            foreach ($c['weekly'] as $windows) {
                $minutes += $this->windows($windows);
            }
            if ($c['mode'] === 'business' && $minutes < 60) {
                $this->fail('Kalender kerja minimal menyediakan 60 menit per minggu.');
            }
            $dates = [];
            foreach ($c['exceptions'] as $e) {
                if (isset($dates[$e['date']])) {
                    $this->fail('Tanggal pengecualian kalender duplikat.');
                }$dates[$e['date']] = true;
                $this->windows($e['windows']);
            }
        }
        foreach ($policies as $p) {
            if (! isset($calendars[$p['calendar_key']])) {
                $this->fail('Kalender kebijakan tidak ditemukan.');
            }
            foreach (['catalog_key' => 'catalog', 'service_key' => 'services', 'category_key' => 'categories'] as $k => $list) {
                if (isset($p[$k]) && ! in_array($p[$k], array_column($config[$list], 'key'), true)) {
                    $this->fail('Referensi kebijakan tidak ditemukan: '.$k);
                }
            }
            $kind = $p['record_type'] === 'incident' ? 'restore' : 'fulfil';
            foreach (['P1', 'P2', 'P3', 'P4'] as $priority) {
                Validator::make($p['targets'][$priority] ?? [], ['response' => 'required|integer|between:1,43200', $kind => 'required|integer|between:1,43200'])->validate();
                if (array_diff(array_keys($p['targets'][$priority]), ['response', $kind])) {
                    $this->fail('Clock target tidak sesuai jenis catatan.');
                }
            }
            foreach (['response', 'restore', 'fulfil'] as $clock) {
                if (! array_key_exists($clock, $p['pause'])) {
                    $this->fail('Kebijakan pause per clock wajib eksplisit.');
                }
            }
            $this->unique($p['escalations'], 'eskalasi');
        }
        foreach (['incident', 'service_request'] as $type) {
            $key = $sla['fallback'][$type] ?? null;
            if ($key && ! isset($policies[$key])) {
                $this->fail('Fallback SLA tidak ditemukan.');
            }
            if ($key && $policies[$key]['record_type'] !== $type) {
                $this->fail('Jenis fallback SLA tidak sesuai.');
            }
            if ($key) {
                foreach (['catalog_key', 'service_key', 'category_key'] as $selector) {
                    if (isset($policies[$key][$selector])) {
                        $this->fail('Fallback harus berlaku umum untuk jenis catatan tersebut.');
                    }
                }
            }
            if ($publishing && collect($config['catalog'])->contains(fn ($c) => $c['is_active'] && $c['record_type'] === $type) && ! $key) {
                $this->fail('Katalog aktif membutuhkan fallback SLA '.$type.'.');
            }
        }
    }

    private function unique(array $items, string $label): array
    {
        if (! array_is_list($items)) {
            $this->fail('Daftar '.$label.' harus berurutan.');
        }$result = [];
        foreach ($items as $item) {
            Validator::make($item, ['key' => 'required|string|regex:/^[a-z][a-z0-9_]{0,49}$/'])->validate();
            if (isset($result[$item['key']])) {
                $this->fail('Kode '.$label.' duplikat.');
            }$result[$item['key']] = $item;
        }

return $result;
    }

    private function windows(array $windows): int
    {
        if (! array_is_list($windows)) {
            $this->fail('Interval harus berupa daftar.');
        }
        $last = -1;
        $minutes = 0;
        foreach ($windows as $w) {
            Validator::make($w, ['start' => ['required', 'regex:/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/'], 'end' => ['required', 'regex:/^(?:(?:[01][0-9]|2[0-3]):[0-5][0-9]|24:00)$/']])->validate();
            if (array_diff(array_keys($w), ['start', 'end'])) {
                $this->fail('Struktur interval tidak dikenal.');
            }
            $a = (int) substr($w['start'], 0, 2) * 60 + (int) substr($w['start'], 3, 2);
            $b = (int) substr($w['end'], 0, 2) * 60 + (int) substr($w['end'], 3, 2);
            if ($b <= $a || $a < $last) {
                $this->fail('Interval harus terurut, tidak tumpang tindih, dan selesai di hari yang sama.');
            }$minutes += $b - $a;
            $last = $b;
        }

return $minutes;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['profiles.sla' => $message]);
    }
}
