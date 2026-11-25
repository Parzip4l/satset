<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\It\Asset;
use App\Models\It\AssetEmployee;
use App\Models\It\AssetLocation;
use App\Models\It\AssetType;
use App\Services\It\AssetRegister;
use App\Services\It\AssetWorkbook;
use App\Services\It\PortalEmployeeDirectory;
use App\Support\ItAssetAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    private function check(Request $request): void
    {
        abort_unless(ItAssetAccess::allowed($request->user()), 403);
        abort_unless(Schema::hasTable('it_assets'), 503, 'Modul Aset IT belum diaktifkan. Hubungi administrator.');
    }

    private function options(): array
    {
        return [
            'types' => AssetType::orderBy('name')->get(), 'locations' => AssetLocation::orderBy('name')->get(),
        ];
    }

    public function index(Request $request)
    {
        $this->check($request);
        $filters = $request->validate([
            'search' => 'nullable|string|max:200', 'type_id' => 'nullable|integer|exists:it_asset_types,id',
            'location_id' => 'nullable|integer|exists:it_asset_locations,id', 'assigned_employee_portal_id' => 'nullable|uuid',
            'status' => ['nullable', Rule::in(array_keys(ItAssetAccess::STATUSES))],
            'sort' => 'nullable|in:newest,oldest_asset,asset_tag',
        ]);
        $query = Asset::query()->with(['type', 'location', 'assignee'])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where('asset_tag', 'like', $term)->orWhere('name', 'like', $term)->orWhere('serial_number', 'like', $term)
                    ->orWhere('brand', 'like', $term)->orWhere('model', 'like', $term)
                    ->orWhereHas('assignee', fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term));
            }));
        foreach (['type_id', 'location_id', 'status'] as $field) {
            $query->when($request->filled($field), fn ($query) => $query->where($field, $filters[$field]));
        }
        $query->when($request->filled('assigned_employee_portal_id'), fn ($query) => $query->whereHas('assignee', fn ($employee) => $employee->where('portal_id', $filters['assigned_employee_portal_id'])));
        $selectedEmployee = $request->filled('assigned_employee_portal_id') ? AssetEmployee::where('portal_id', $filters['assigned_employee_portal_id'])->first() : null;
        $summary = [
            'total' => (clone $query)->count(),
            'in_use' => (clone $query)->where('status', 'in_use')->count(),
            'available' => (clone $query)->where('status', 'available')->count(),
            'maintenance' => (clone $query)->whereIn('status', ['maintenance', 'damaged'])->count(),
            'known_price_count' => (clone $query)->whereNotNull('purchase_price')->count(),
            'known_price_total' => (clone $query)->sum('purchase_price'),
        ];
        match ($filters['sort'] ?? 'newest') {
            'oldest_asset' => $query->orderByRaw('acquired_on IS NULL')->orderBy('acquired_on')->orderBy('id'),
            'asset_tag' => $query->orderBy('asset_tag')->orderBy('id'),
            default => $query->latest('id'),
        };
        $assets = $query->paginate(20)->withQueryString();

        return view('it.assets.index', $this->options() + compact('assets', 'summary', 'filters', 'selectedEmployee'));
    }

    public function employees(Request $request, PortalEmployeeDirectory $directory)
    {
        $this->check($request);
        $input = $request->validate(['search' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1']);
        try {
            $result = $directory->search($input['search'] ?? '', $input['page'] ?? 1);
        } catch (ValidationException $exception) {
            return response()->json(['message' => collect($exception->errors())->flatten()->first()], 503);
        }
        $employees = collect($result['employees'])->where('status', 'active')->values();
        foreach ($employees as $employee) {
            AssetEmployee::updateOrCreate(['portal_id' => $employee['id']], [
                'name' => $employee['name'], 'email' => $employee['email'], 'employee_number' => $employee['employee_number'] ?? null,
                'department' => $employee['department'] ?? null, 'is_active' => true, 'synced_at' => now(),
            ]);
        }

        return response()->json(['employees' => $employees, 'page' => $result['page'], 'has_more' => $result['has_more']]);
    }

    public function create(Request $request)
    {
        $this->check($request);
        $asset = new Asset(['asset_tag' => 'IT-'.Str::upper((string) Str::ulid()), 'status' => 'available']);

        return view('it.assets.form', $this->options() + compact('asset'));
    }

    public function template(Request $request)
    {
        $this->check($request);

        return response()->download(resource_path('templates/it-assets.xlsx'), 'template-aset-it.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function importForm(Request $request)
    {
        $this->check($request);

        return view('it.assets.import');
    }

    public function import(Request $request, AssetWorkbook $workbook, AssetRegister $register)
    {
        $this->check($request);
        $request->validate(['file' => 'required|file|mimes:xlsx|extensions:xlsx|max:5120'], [
            'file.required' => 'Pilih file XLSX yang akan diimpor.', 'file.file' => 'Unggahan harus berupa file XLSX.',
            'file.mimes' => 'Gunakan file Excel dengan format .xlsx.', 'file.extensions' => 'Ekstensi file harus .xlsx.',
            'file.max' => 'Ukuran file maksimal 5 MB.', 'file.uploaded' => 'File gagal diunggah. Periksa ukuran file lalu coba lagi.',
        ]);
        $rows = $workbook->read($request->file('file')->getRealPath());
        try {
            $count = $register->createBatch($request->user(), $rows);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['file' => 'Data berubah saat impor berlangsung. Tidak ada aset dari file ini yang disimpan. Periksa kode aset lalu unggah ulang.']);
        }

        return redirect()->route('it.assets.index')->with('success', $count.' aset berhasil diimpor. Pencatatan awal tersimpan dalam riwayat masing-masing aset.');
    }

    public function store(Request $request, AssetRegister $register)
    {
        $this->check($request);
        $asset = $register->create($request->user(), $request->all());

        return redirect()->route('it.assets.show', $asset)->with('success', 'Aset IT berhasil dicatat.');
    }

    public function show(Request $request, Asset $asset)
    {
        $this->check($request);
        $asset->load(['type', 'location', 'assignee']);
        $histories = $asset->histories()->orderByDesc('sequence')->paginate(15)->withQueryString();

        return view('it.assets.show', $this->options() + compact('asset', 'histories'));
    }

    public function edit(Request $request, Asset $asset)
    {
        $this->check($request);
        $asset->load(['type', 'location', 'assignee']);

        return view('it.assets.form', $this->options() + compact('asset'));
    }

    public function update(Request $request, Asset $asset, AssetRegister $register)
    {
        $this->check($request);
        $register->update($request->user(), $asset, $request->all());

        return redirect()->route('it.assets.show', $asset)->with('success', 'Data aset diperbarui dan tersimpan dalam riwayat.');
    }

    public function move(Request $request, Asset $asset, AssetRegister $register)
    {
        $this->check($request);
        $register->move($request->user(), $asset, $request->all());

        return redirect()->route('it.assets.show', $asset)->with('success', 'Mutasi / perubahan status aset berhasil dicatat.');
    }
}
