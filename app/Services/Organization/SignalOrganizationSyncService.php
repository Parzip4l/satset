<?php

namespace App\Services\Organization;

use App\Models\Master\Department;
use App\Models\Master\Divisions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SignalOrganizationSyncService
{
    public function sync(): array
    {
        $tree = $this->payloadItems($this->fetch(config('services.signal_organization.tree_url')));
        $departments = $this->payloadItems($this->fetch(config('services.signal_organization.departments_url')));

        return DB::transaction(function () use ($tree, $departments) {
            $divisionMap = $this->syncDivisions($tree);
            $departmentCount = $this->syncDepartments($departments, $divisionMap);

            if ($departmentCount === 0) {
                $departmentCount = $this->syncDepartmentsFromTree($tree, $divisionMap);
            }

            return [
                'divisions' => $divisionMap->count(),
                'departments' => $departmentCount,
            ];
        });
    }

    private function fetch(?string $url): array
    {
        if (!$url) {
            throw new RuntimeException('URL organisasi Signal belum dikonfigurasi.');
        }

        $response = Http::timeout(config('services.signal_organization.timeout', 15))
            ->withOptions(['verify' => $this->shouldVerifySsl()])
            ->acceptJson()
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException("Gagal mengambil data organisasi Signal ({$response->status()}): {$url}");
        }

        return $response->json() ?? [];
    }

    private function payloadItems(array $payload): Collection
    {
        $items = $payload['data'] ?? $payload;

        return collect(is_array($items) ? $items : [])
            ->filter(fn ($item) => is_array($item));
    }

    private function shouldVerifySsl(): bool
    {
        return filter_var(config('services.signal_organization.verify_ssl', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function syncDivisions(Collection $divisions): Collection
    {
        $map = collect();

        foreach ($divisions as $division) {
            if (!$this->isActive($division) || empty($division['id']) || empty($division['name'])) {
                continue;
            }

            $model = Divisions::query()
                ->where('signal_id', $division['id'])
                ->orWhere('name', $division['name'])
                ->first() ?? new Divisions();

            $model->fill([
                'signal_id' => $division['id'],
                'code' => $this->nullableCode($division['ldap_code'] ?? null),
                'name' => trim($division['name']),
            ]);
            $model->save();

            $map->put($division['id'], $model);
        }

        return $map;
    }

    private function syncDepartments(Collection $departments, Collection $divisionMap): int
    {
        $count = 0;

        foreach ($departments as $department) {
            if (!$this->isActive($department) || empty($department['id']) || empty($department['name'])) {
                continue;
            }

            $divisionId = $department['division_id'] ?? data_get($department, 'division.id');
            $division = $divisionMap->get($divisionId);

            if (!$division && is_array($department['division'] ?? null)) {
                $division = $this->syncDivisions(collect([$department['division']]))->get(data_get($department, 'division.id'));
            }

            if (!$division) {
                continue;
            }

            $code = $this->departmentCode($department);
            $model = Department::query()
                ->where('signal_id', $department['id'])
                ->orWhere('code', $code)
                ->first() ?? new Department();

            $model->fill([
                'signal_id' => $department['id'],
                'division_id' => $division->id,
                'code' => $code,
                'name' => trim($department['name']),
                'email' => $department['email'] ?? $model->email,
            ]);
            $model->save();
            $count++;
        }

        return $count;
    }

    private function syncDepartmentsFromTree(Collection $divisions, Collection $divisionMap): int
    {
        $count = 0;

        foreach ($divisions as $division) {
            if (empty($division['id']) || !$divisionMap->has($division['id'])) {
                continue;
            }

            $count += $this->syncDepartments(
                collect($division['departments'] ?? [])->map(fn ($department) => $department + ['division_id' => $division['id']]),
                $divisionMap
            );
        }

        return $count;
    }

    private function isActive(array $row): bool
    {
        return !array_key_exists('is_active', $row) || (bool) $row['is_active'];
    }

    private function nullableCode(mixed $value): ?string
    {
        $code = trim((string) $value);

        return $code === '' ? null : Str::upper(Str::limit($code, 30, ''));
    }

    private function departmentCode(array $department): string
    {
        $code = $this->nullableCode($department['ldap_code'] ?? null);

        if ($code) {
            return $code;
        }

        return 'SIG-' . Str::upper(substr((string) $department['id'], -10));
    }
}
