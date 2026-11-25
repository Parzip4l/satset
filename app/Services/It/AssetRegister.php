<?php

namespace App\Services\It;

use App\Models\It\Asset;
use App\Models\It\AssetEmployee;
use App\Models\It\AssetLocation;
use App\Models\It\AssetType;
use App\Models\User;
use App\Support\ItAssetAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AssetRegister
{
    public function __construct(private readonly PortalEmployeeDirectory $directory) {}

    private function metadata(array $input, ?Asset $asset = null): array
    {
        $input['asset_tag'] = Str::upper(Str::squish((string) ($input['asset_tag'] ?? '')));
        if (is_array($input['specifications'] ?? null)) {
            $input['specifications'] = array_values(array_filter($input['specifications'], fn ($row) => ! is_array($row) || filled($row['name'] ?? null) || filled($row['value'] ?? null)));
        }

        $data = Validator::make($input, [
            'asset_tag' => ['required', 'string', 'max:80', Rule::unique('it_assets', 'asset_tag')->ignore($asset?->id)],
            'name' => 'required|string|max:200', 'type_name' => 'required|string|max:150',
            'brand' => 'nullable|string|max:100', 'model' => 'nullable|string|max:150', 'serial_number' => 'nullable|string|max:150',
            'acquired_on' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'purchase_price' => 'nullable|numeric|min:0|max:999999999999999.99|decimal:0,2',
            'expires_on' => array_merge(['nullable', 'date_format:Y-m-d'], ! empty($input['acquired_on']) ? ['after_or_equal:acquired_on'] : []),
            'notes' => 'nullable|string|max:10000',
            'specifications' => 'nullable|array|max:100',
            'specifications.*' => 'required|array:name,value',
            'specifications.*.name' => 'required|string|max:100',
            'specifications.*.value' => 'required|string|max:1000',
        ], [
            'asset_tag.unique' => 'Kode aset sudah dipakai. Gunakan kode yang berbeda.',
            'required' => ':attribute wajib diisi.', 'string' => ':attribute harus berupa teks.',
            'max' => ['string' => ':attribute maksimal :max karakter.', 'array' => ':attribute maksimal :max baris.', 'numeric' => ':attribute maksimal :max.'],
            'min' => ':attribute tidak boleh kurang dari :min.',
            'numeric' => ':attribute harus berupa angka tanpa Rp atau pemisah ribuan.',
            'decimal' => ':attribute maksimal 2 angka desimal.',
            'date_format' => ':attribute harus berupa tanggal YYYY-MM-DD yang valid.',
            'before_or_equal' => ':attribute tidak boleh melewati hari ini.',
            'after_or_equal' => ':attribute tidak boleh sebelum tanggal perolehan.',
            'array' => ':attribute harus berisi nama dan nilai spesifikasi.',
        ], array_flip(AssetWorkbook::COLUMNS) + ['specifications' => 'Spesifikasi', 'specifications.*.name' => 'Nama spesifikasi', 'specifications.*.value' => 'Nilai spesifikasi'])->validate();
        $data['specifications'] = array_map(fn ($row) => ['name' => Str::squish($row['name']), 'value' => trim($row['value'])], $data['specifications'] ?? []);
        $names = array_map(fn ($row) => Str::lower($row['name']), $data['specifications']);
        if (count(array_unique($names)) !== count($names)) {
            throw ValidationException::withMessages(['specifications' => 'Nama spesifikasi tidak boleh berulang. Gabungkan nilainya dalam satu baris.']);
        }

        return $data;
    }

    private function placement(array $input, ?Asset $current = null, array $verifiedEmployees = []): array
    {
        $data = Validator::make($input, [
            'location_name' => 'required|string|max:150',
            'assigned_employee_portal_id' => 'nullable|uuid',
            'assigned_user_id' => 'prohibited', 'assigned_employee_id' => 'prohibited',
            'status' => ['required', Rule::in(array_keys(ItAssetAccess::STATUSES))],
        ], [
            'location_name.required' => 'Lokasi wajib diisi.', 'location_name.string' => 'Lokasi harus berupa teks.',
            'location_name.max' => 'Lokasi maksimal 150 karakter.', 'status.required' => 'Status wajib diisi.',
            'status.in' => 'Status tidak dikenali. Gunakan pilihan status pada template atau form aset.',
            'assigned_employee_portal_id.uuid' => 'Identitas karyawan Portal tidak valid.',
        ])->validate();
        $portalId = $data['assigned_employee_portal_id'] ?? null;
        if ($data['status'] === 'in_use' && ! $portalId) {
            throw ValidationException::withMessages(['assigned_employee_portal_id' => 'Pilih karyawan dari Portal untuk aset yang sedang digunakan.']);
        }
        if (in_array($data['status'], ['available', 'retired'], true) && $portalId) {
            throw ValidationException::withMessages(['assigned_employee_portal_id' => 'Kosongkan pemegang/PIC untuk aset berstatus Tersedia atau Tidak Aktif.']);
        }
        $employee = null;
        if ($portalId) {
            $existing = $current?->assignee;
            $employee = $existing && strcasecmp($existing->portal_id, $portalId) === 0
                ? $existing
                : ($verifiedEmployees[$portalId] ?? $this->directory->find($portalId));
        }

        return ['location_name' => $data['location_name'], 'status' => $data['status'], 'employee' => $employee];
    }

    private function persistPlacement(array $placement): array
    {
        $employee = $placement['employee'];
        if (is_array($employee)) {
            $employee = AssetEmployee::updateOrCreate(['portal_id' => $employee['id']], [
                'name' => $employee['name'], 'email' => $employee['email'], 'employee_number' => $employee['employee_number'] ?? null,
                'department' => $employee['department'] ?? null, 'is_active' => $employee['status'] === 'active', 'synced_at' => now(),
            ]);
        }

        return [
            'assigned_employee_id' => $employee?->id, 'status' => $placement['status'],
            'location_id' => $this->lookup(AssetLocation::class, $placement['location_name']),
        ];
    }

    private function lookup(string $model, string $name): int
    {
        $name = Str::squish($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Nama tipe atau lokasi tidak boleh kosong.']);
        }

        return $model::firstOrCreate(['name_key' => Str::lower($name)], ['name' => $name])->id;
    }

    public function create(User $actor, array $input): Asset
    {
        abort_unless(ItAssetAccess::allowed($actor), 403);
        $metadata = $this->metadata($input);
        $placement = $this->placement($input);

        return DB::transaction(fn () => $this->persistNew($actor, $metadata, $placement, 'Pencatatan awal aset.'));
    }

    public function createBatch(User $actor, array $rows): int
    {
        abort_unless(ItAssetAccess::allowed($actor), 403);
        if (! $rows || count($rows) > AssetWorkbook::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Isi antara 1 sampai 100 aset per file.']);
        }
        $prepared = $errors = $tags = $employees = [];
        foreach ($rows as $row => $input) {
            try {
                $metadata = $this->metadata($input);
                if (isset($tags[$metadata['asset_tag']])) {
                    throw ValidationException::withMessages(['asset_tag' => 'Kode aset berulang dengan baris '.$tags[$metadata['asset_tag']].'.']);
                }
                $tags[$metadata['asset_tag']] = $row;
                $email = Str::lower(trim((string) ($input['employee_email'] ?? '')));
                Validator::make(['employee_email' => $email], ['employee_email' => 'nullable|email|max:255'], [
                    'employee_email.email' => 'Isi email kerja karyawan yang valid, bukan nama karyawan.',
                    'employee_email.max' => 'Email pemegang maksimal 255 karakter.',
                ])->validate();
                $verified = [];
                if ($email !== '') {
                    $employee = $employees[$email] ??= $this->directory->findByEmail($email);
                    $input['assigned_employee_portal_id'] = $employee['id'];
                    $verified[$employee['id']] = $employee;
                }
                $prepared[] = [$metadata, $this->placement($input, null, $verified)];
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $errors['rows.'.$row.'.'.$field] = array_map(fn ($message) => 'Baris '.$row.': '.$message, $messages);
                }
                // A directory outage affects the whole file; avoid retrying it for every row.
                if (isset($exception->errors()['employee_directory'])) {
                    break;
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($actor, $prepared) {
            foreach ($prepared as [$metadata, $placement]) {
                $this->persistNew($actor, $metadata, $placement, 'Pencatatan awal aset melalui impor XLSX.');
            }

            return count($prepared);
        });
    }

    private function persistNew(User $actor, array $metadata, array $placement, string $reason): Asset
    {
        $placement = $this->persistPlacement($placement);
        $metadata['type_id'] = $this->lookup(AssetType::class, $metadata['type_name']);
        unset($metadata['type_name']);
        $asset = Asset::create($metadata + $placement + ['created_by' => $actor->id, 'lock_version' => 1]);
        $this->record($asset, $actor, 'created', $reason, null);

        return $asset;
    }

    public function update(User $actor, Asset $asset, array $input): Asset
    {
        abort_unless(ItAssetAccess::allowed($actor), 403);
        $control = Validator::make($input, [
            'lock_version' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:2000',
            'assigned_employee_portal_id' => 'prohibited', 'assigned_user_id' => 'prohibited', 'assigned_employee_id' => 'prohibited', 'location_name' => 'prohibited', 'status' => 'prohibited',
        ])->validate();
        $metadata = $this->metadata($input, $asset);

        return DB::transaction(function () use ($actor, $asset, $control, $metadata) {
            $current = Asset::lockForUpdate()->findOrFail($asset->id);
            $this->version($current, $control['lock_version']);
            $before = $this->snapshot($current);
            $metadata['type_id'] = $this->lookup(AssetType::class, $metadata['type_name']);
            unset($metadata['type_name']);
            $current->fill($metadata);
            if (! $current->isDirty()) {
                throw ValidationException::withMessages(['reason' => 'Belum ada perubahan data aset.']);
            }
            $current->lock_version++;
            $current->save();
            $this->record($current, $actor, 'updated', $control['reason'], $before);

            return $current;
        });
    }

    public function move(User $actor, Asset $asset, array $input): Asset
    {
        abort_unless(ItAssetAccess::allowed($actor), 403);
        $control = Validator::make($input, ['lock_version' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:2000'])->validate();
        $placement = $this->placement($input, $asset);

        return DB::transaction(function () use ($actor, $asset, $placement, $control) {
            $current = Asset::lockForUpdate()->findOrFail($asset->id);
            $this->version($current, $control['lock_version']);
            $before = $this->snapshot($current);
            $current->fill($this->persistPlacement($placement));
            if (! $current->isDirty()) {
                throw ValidationException::withMessages(['reason' => 'Belum ada perubahan pemegang, lokasi, atau status.']);
            }
            $action = $current->isDirty('assigned_employee_id') ? 'transferred' : ($current->isDirty('location_id') ? 'relocated' : 'status_changed');
            $current->lock_version++;
            $current->save();
            $this->record($current, $actor, $action, $control['reason'], $before);

            return $current;
        });
    }

    private function version(Asset $asset, int $version): void
    {
        abort_unless($asset->lock_version === $version, 409, 'Data aset telah berubah. Muat ulang halaman sebelum menyimpan.');
    }

    private function snapshot(Asset $asset): array
    {
        $asset->load(['type', 'location', 'assignee']);

        return [
            'asset_tag' => $asset->asset_tag, 'name' => $asset->name,
            'type_id' => $asset->type_id, 'type_name' => $asset->type?->name,
            'location_id' => $asset->location_id, 'location_name' => $asset->location?->name,
            'assigned_employee_id' => $asset->assigned_employee_id, 'assignee_portal_id' => $asset->assignee?->portal_id, 'assignee_name' => $asset->assignee?->name,
            'assignee_email' => $asset->assignee?->email, 'status' => $asset->status,
            'brand' => $asset->brand, 'model' => $asset->model, 'serial_number' => $asset->serial_number,
            'acquired_on' => $asset->acquired_on?->toDateString(), 'purchase_price' => $asset->purchase_price,
            'expires_on' => $asset->expires_on?->toDateString(), 'notes' => $asset->notes,
            'specifications' => $asset->specifications ?? [],
        ];
    }

    private function record(Asset $asset, User $actor, string $action, string $reason, ?array $before): void
    {
        $asset->histories()->create([
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'sequence' => $asset->lock_version,
            'action' => $action, 'reason' => $reason, 'before_state' => $before, 'after_state' => $this->snapshot($asset), 'created_at' => now(),
        ]);
    }
}
