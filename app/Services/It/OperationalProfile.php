<?php

namespace App\Services\It;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Version 2 adds bounded, declarative workflow settings. No executable expressions. */
class OperationalProfile
{
    public static function defaults(): array
    {
        $matrix = [];
        foreach (['high', 'medium', 'low'] as $i => $impact) {
            foreach (['high', 'medium', 'low'] as $j => $urgency) {
                $matrix[$impact.'.'.$urgency] = 'P'.min(4, 1 + $i + $j);
            }
        }

        return ['workflow' => 'it_v1', 'routing' => [], 'approval' => [], 'priority_matrix' => $matrix,
            'reopen_days' => 7, 'sla' => 'phase4_pending', 'submission_enabled' => false];
    }

    public function validate(array $p, array $config, bool $publishing = false): void
    {
        Validator::make(['profiles' => $p], [
            'profiles' => 'required|array:workflow,routing,approval,priority_matrix,reopen_days,sla,submission_enabled',
            'profiles.workflow' => 'required|in:it_v1', 'profiles.sla' => ($config['schema_version'] === 3 ? 'required|array' : 'required|in:phase4_pending'),
            'profiles.submission_enabled' => 'required|declined', 'profiles.reopen_days' => 'required|integer|between:1,30',
            'profiles.routing' => 'present|array|max:100', 'profiles.approval' => 'present|array|max:100',
            'profiles.routing.*' => 'array:key,catalog_key,service_key,category_key,record_type,team_id',
            'profiles.approval.*' => 'array:key,catalog_key,service_key,category_key,record_type,resolver,approver_id',
            'profiles.routing.*.team_id' => 'required|integer|exists:support_teams,id',
            'profiles.approval.*.resolver' => 'required|in:portal,user',
            'profiles.approval.*.approver_id' => 'nullable|integer|exists:users,id',
            'profiles.priority_matrix' => 'required|array|size:9', 'profiles.priority_matrix.*' => 'required|in:P1,P2,P3,P4',
        ])->validate();
        if ($config['schema_version'] === 3) {
            app(SlaProfile::class)->validate($p['sla'], $config, $publishing);
        }
        $expected = array_keys(self::defaults()['priority_matrix']);
        if (array_diff($expected, array_keys($p['priority_matrix']))) {
            $this->fail('Matriks harus memuat sembilan kombinasi impact.urgency.');
        }
        foreach (['routing', 'approval'] as $section) {
            if (! array_is_list($p[$section])) {
                $this->fail('Aturan harus berupa daftar terurut.');
            }
            $keys = [];
            foreach ($p[$section] as $rule) {
                Validator::make($rule, ['key' => 'required|string|regex:/^[a-z][a-z0-9_]{0,49}$/', 'record_type' => 'sometimes|in:incident,service_request'])->validate();
                if (isset($keys[$rule['key']])) {
                    $this->fail('Kode aturan harus unik.');
                }
                $keys[$rule['key']] = true;
                foreach (['catalog_key' => 'catalog', 'service_key' => 'services', 'category_key' => 'categories'] as $field => $list) {
                    if (isset($rule[$field]) && ! in_array($rule[$field], array_column($config[$list], 'key'), true)) {
                        $this->fail('Referensi aturan tidak ditemukan: '.$field);
                    }
                }
                if ($section === 'approval' && $rule['resolver'] === 'user' && empty($rule['approver_id'])) {
                    $this->fail('Approver harus dipilih untuk resolver user.');
                }
                if ($section === 'approval' && $rule['resolver'] === 'portal' && ! empty($rule['approver_id'])) {
                    $this->fail('Resolver portal tidak boleh menetapkan approver manual.');
                }
                if ($publishing && $section === 'routing') {
                    $this->readyTeam((int) $rule['team_id']);
                }
            }
        }
        if ($publishing) {
            foreach ($config['services'] as $service) {
                if ($service['is_active']) {
                    if (empty($service['owner_id']) || empty($service['fallback_team_id'])) {
                        $this->fail('Layanan aktif membutuhkan owner dan tim fallback sebelum publikasi workflow.');
                    }
                    $this->readyTeam((int) $service['fallback_team_id']);
                }
            }
        }
    }

    public function readyTeam(int $id): void
    {
        if (! DB::table('support_teams')->where('id', $id)->where('is_active', true)->exists()
            || ! DB::table('support_team_members')->where('support_team_id', $id)->whereIn('role', ['technician', 'coordinator'])->exists()) {
            $this->fail('Tim harus aktif dan memiliki teknisi/koordinator.');
        }
    }

    public function matches(array $rule, array $context): bool
    {
        foreach (['catalog_key', 'service_key', 'category_key', 'record_type'] as $key) {
            if (isset($rule[$key]) && $rule[$key] !== ($context[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['profiles' => $message]);
    }
}
