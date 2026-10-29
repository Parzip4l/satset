@extends('partials.layouts.master')
@section('title','Tim IT')
@section('pagetitle','Tim IT')
@section('title-sub','Layanan IT')
@section('css') @include('it.configuration.style') @endsection
@section('content')
<div class="container-fluid it-config">
    <header class="config-header config-page-header"><div class="config-page-heading"><div class="config-eyebrow">Pengelolaan layanan IT</div><h1>Tim penanganan IT</h1><p class="mb-0">Kelola tim, anggota, dan koordinator yang menangani tiket IT.</p></div><nav class="config-page-actions" aria-label="Navigasi konfigurasi"><a href="{{ route('it.configuration.index') }}" class="config-page-action config-page-action-back"><i class="bi bi-arrow-left" aria-hidden="true"></i>Konfigurasi IT</a></nav></header>
    <div class="config-note"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Anggota tim aktif dapat mengakses tiket sesuai timnya. Tiket rahasia tetap memerlukan hak akses khusus.</div>
    <div class="alert alert-warning">Daftar pengguna di bawah berasal dari akun yang sudah tercatat di Satset. Daftar ini belum mengambil seluruh karyawan langsung dari Portal.</div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <details class="config-panel config-team-card"><summary><span class="config-team-summary"><strong>Tambah tim IT</strong><small>Buat tim baru sebagai draft</small></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary><div class="config-team-body"><form method="post" action="{{ route('it.configuration.teams.store') }}">@csrf
        <div class="builder-grid"><div><label for="new-team-code">Kode tim</label><input id="new-team-code" name="code" class="form-control" pattern="[a-z][a-z0-9_-]{0,49}" required maxlength="50" placeholder="contoh: service_desk"><p class="builder-help">Gunakan huruf kecil, angka, tanda hubung, atau garis bawah.</p></div><div><label for="new-team-name">Nama tim</label><input id="new-team-name" name="name" class="form-control" required maxlength="100" placeholder="Contoh: Service Desk"></div></div>
        <div class="team-reason"><label for="new-team-reason">Alasan pembuatan</label><textarea id="new-team-reason" name="reason" class="form-control mb-3" required minlength="5" maxlength="1000" placeholder="Jelaskan alasan pembuatan tim"></textarea><button class="btn btn-primary btn-sm">Buat tim draft</button></div>
    </form></div></details>
    <div class="config-header"><div><h2 class="mb-1">Daftar tim</h2><p class="config-small mb-0">Buka tim untuk mengatur status dan peran anggotanya.</p></div><span class="config-badge">{{ $teams->count() }} tim</span></div>
    @forelse($teams as $team)
    @php($current=$members->where('support_team_id',$team->id)->sortBy('user_id')->map(fn($m)=>['user_id'=>$m->user_id,'role'=>$m->role])->values()->all())
    <details class="config-panel config-team-card"><summary><span class="config-team-summary"><strong>{{ $team->name }}</strong><small>{{ $team->code }} · {{ count($current) }} anggota</small><span class="config-badge {{ $team->is_active?'active':'draft' }}">{{ $team->is_active ? 'Aktif' : 'Draft / nonaktif' }}</span></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary><div class="config-team-body"><form method="post" action="{{ route('it.configuration.teams.update',$team->id) }}">@csrf @method('PUT')
        <input type="hidden" name="fingerprint" value="{{ \App\Services\It\ConfigurationStore::checksum(['name'=>$team->name,'is_active'=>(bool)$team->is_active,'members'=>$current]) }}">
        <div class="builder-grid"><div><label for="team-name-{{ $team->id }}">Nama tim</label><input id="team-name-{{ $team->id }}" name="name" class="form-control" value="{{ $team->name }}" required maxlength="100"></div><div><label for="team-status-{{ $team->id }}">Status</label><select id="team-status-{{ $team->id }}" name="is_active" class="form-select"><option value="0" @selected(!$team->is_active)>Draft / nonaktif</option><option value="1" @selected($team->is_active)>Aktif</option></select></div></div>
        <div class="sla-subsection"><h3 class="sla-subsection-title">Anggota tim</h3><p class="sla-subsection-help">Cari pengguna, lalu pilih perannya. Pilih “Bukan anggota” untuk mengeluarkan pengguna dari tim.</p><div class="team-search"><label for="team-search-{{ $team->id }}">Cari pengguna</label><input id="team-search-{{ $team->id }}" type="search" class="form-control" placeholder="Cari nama atau ID" data-team-search></div><div class="team-members" data-team-members>@foreach($users as $user)
            @php($membership=$members->where('support_team_id',$team->id)->firstWhere('user_id',$user->id))
            <div class="team-member" data-team-member data-search="{{ mb_strtolower($user->name) }} {{ $user->id }}"><label for="team-{{ $team->id }}-user-{{ $user->id }}">{{ $user->name }} <span class="text-muted">· ID {{ $user->id }}</span></label><select id="team-{{ $team->id }}-user-{{ $user->id }}" class="form-select" name="roles[{{ $user->id }}]"><option value="">Bukan anggota</option><option value="technician" @selected($membership?->role==='technician')>Teknisi</option><option value="coordinator" @selected($membership?->role==='coordinator')>Koordinator</option></select></div>@endforeach</div><p class="team-empty" data-team-empty hidden>Tidak ada pengguna yang cocok.</p></div>
        <div class="team-reason"><label for="team-reason-{{ $team->id }}">Alasan perubahan</label><textarea id="team-reason-{{ $team->id }}" name="reason" class="form-control mb-2" minlength="5" maxlength="1000" required placeholder="Jelaskan perubahan yang dibuat"></textarea><p class="builder-help mb-3">Minimal 5 karakter. Alasan disimpan dalam riwayat tim.</p><button class="btn btn-primary btn-sm">Simpan tim</button></div>
    </form></div></details>
    @empty<div class="config-panel config-small">Belum ada tim. Buat tim draft untuk mulai menambahkan anggota.</div>@endforelse
</div>
@endsection
@section('js')
<script>
document.querySelectorAll('[data-team-search]').forEach(function(input){input.addEventListener('input',function(){var section=input.closest('.sla-subsection'),query=input.value.trim().toLocaleLowerCase('id'),count=0;section.querySelectorAll('[data-team-member]').forEach(function(item){var match=item.dataset.search.toLocaleLowerCase('id').includes(query);item.hidden=!match;if(match)count++;});section.querySelector('[data-team-empty]').hidden=count!==0;});});
</script>
@endsection
