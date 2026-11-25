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
    <select name="assigned_employee_portal_id" id="asset-assignee" class="form-select" data-portal-employee data-portal-url="{{ route('it.assets.employees') }}">
        <option value="">Persediaan / tanpa pemegang</option>
        @if($selectedPortalEmployee)<option value="{{ $selectedPortalEmployee->portal_id }}" selected>{{ $selectedPortalEmployee->name }} · {{ $selectedPortalEmployee->email }}{{ $selectedPortalEmployee->is_active ? '' : ' (nonaktif)' }}</option>@endif
    </select>
    <div class="portal-employee-search mt-2">
        <label for="portal-employee-search" class="visually-hidden">Cari karyawan dari Portal</label>
        <div class="input-group"><input id="portal-employee-search" type="search" class="form-control" placeholder="Cari nama, email, atau NIK dari Portal" maxlength="150" autocomplete="off" data-portal-search><button type="button" class="btn btn-outline-secondary" data-portal-find>Cari Portal</button></div>
        <div class="asset-note mt-2" data-portal-message role="status" aria-live="polite">Nama karyawan bersumber dari Portal. Ketik nama untuk mencari.</div>
        <div class="asset-suggestions portal-employee-results" role="listbox" hidden data-portal-results aria-label="Hasil karyawan Portal"></div>
        <button type="button" class="btn btn-link btn-sm px-0" hidden data-portal-more>Muat hasil berikutnya</button>
    </div>
    <div class="form-text">Status Digunakan membutuhkan pemegang/PIC. Pilih tanpa pemegang untuk status Tersedia atau Tidak Aktif.</div>
</div>
