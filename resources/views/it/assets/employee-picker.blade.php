<div class="asset-employee-picker" data-portal-picker data-portal-url="{{ route('it.assets.employees') }}">
    <input type="hidden" name="assigned_employee_portal_id" value="{{ $employee?->portal_id }}" data-portal-employee>
    <div class="input-group">
        <input id="{{ $pickerId }}" type="search" class="form-control" maxlength="150" placeholder="Cari nama, email, atau NIK di Portal" value="{{ $employee ? $employee->name.' · '.$employee->email : '' }}" aria-label="Pemegang / PIC karyawan dari Portal" autocomplete="off" data-portal-search>
        <button type="button" class="btn btn-outline-secondary" data-portal-find aria-label="Cari karyawan Portal"><i class="bi bi-search"></i></button>
        <button type="button" class="btn btn-outline-secondary" data-portal-clear aria-label="Kosongkan pemegang"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="asset-note mt-1" data-portal-message role="status" aria-live="polite">{{ $employee ? 'Karyawan dipilih dari Portal.' : $emptyLabel }}</div>
    <div class="asset-suggestions portal-employee-results" hidden data-portal-results aria-label="Hasil karyawan Portal"></div>
    <button type="button" class="btn btn-link btn-sm px-0" hidden data-portal-more>Muat hasil berikutnya</button>
</div>
