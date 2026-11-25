<div class="col-md-6">
    <label for="asset-location" class="form-label">Lokasi <span class="text-danger">*</span></label>
    <input name="location_name" id="asset-location" list="asset-location-options" class="form-control" maxlength="150" value="{{ old('location_name', $asset->location?->name) }}" placeholder="Ketik atau pilih lokasi" required autocomplete="off">
    <datalist id="asset-location-options">@foreach($locations as $location)<option value="{{ $location->name }}"></option>@endforeach</datalist>
    <div class="form-text">Pilih lokasi yang muncul. Nama baru otomatis dibuat saat disimpan.</div>
</div>
<div class="col-md-6">
    <label for="asset-status" class="form-label">Status aset <span class="text-danger">*</span></label>
    <select name="status" id="asset-status" class="form-select" required>@foreach(\App\Support\ItAssetAccess::STATUSES as $value => $definition)<option value="{{ $value }}" @selected(old('status', $asset->status) === $value)>{{ $definition['label'] }}</option>@endforeach</select>
</div>
<div class="col-12">
    <label for="asset-assignee" class="form-label">Pemegang / PIC karyawan</label>
    @php($selectedPortalEmployee = array_key_exists('assigned_employee_portal_id', session()->getOldInput()) ? (old('assigned_employee_portal_id') ? \App\Models\It\AssetEmployee::where('portal_id', old('assigned_employee_portal_id'))->first() : null) : $asset->assignee)
    @include('it.assets.employee-picker', ['employee' => $selectedPortalEmployee, 'pickerId' => 'asset-assignee', 'emptyLabel' => 'Tanpa pemegang. Ketik minimal 2 karakter untuk mencari di Portal.'])
    <div class="form-text">Status Digunakan membutuhkan pemegang/PIC. Pilih tanpa pemegang untuk status Tersedia atau Tidak Aktif.</div>
</div>
