@extends('partials.layouts.master')

@section('title', 'Data Ruang Meeting | SatSet System')
@section('title-sub', 'Facility Management')
@section('pagetitle', 'Meeting Rooms')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- 1. HEADER CARD --}}
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">Master Ruang Meeting</h4>
                            <p class="text-muted mb-0">Kelola daftar ruangan, kapasitas, dan warna identitas kalender.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#ModalRoom">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Ruangan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. FILTER & SEARCH TOOLBAR --}}
            <div class="card border-0 mb-4 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form id="filter-form" method="GET" action="{{ route('meeting-rooms.index') }}">
                        <div class="row g-3 align-items-center">
                            {{-- Search --}}
                            <div class="col-md-6">
                                <div class="position-relative">
                                    <input type="text" id="search-input" name="search" class="form-control ps-5" 
                                           value="{{ request('search') }}" 
                                           placeholder="Cari nama ruangan atau lokasi..."> 
                                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-2 ms-auto">
                                <a href="{{ route('meeting-rooms.index') }}" class="btn btn-outline-danger w-100">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- 3. DATA TABLE --}}
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted text-uppercase fs-11 fw-bold">
                                <tr>
                                    <th class="ps-4 py-3">Informasi Ruangan</th>
                                    <th class="py-3">Kapasitas</th>
                                    <th class="py-3">Lokasi</th>
                                    <th class="py-3">Status</th>
                                    <th class="text-end pe-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="room-table-body" class="border-top-0">
                                @forelse($rooms as $room)
                                    <tr>
                                        {{-- Room Info --}}
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                {{-- Color Dot Representation --}}
                                                <div class="rounded-circle shadow-sm d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px; background-color: {{ $room->color }}20; border: 1px solid {{ $room->color }};">
                                                    <i class="bi bi-easel2-fill" style="color: {{ $room->color }}"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0 text-dark">{{ $room->name }}</h6>
                                                    <small class="text-muted fw-medium d-flex align-items-center gap-1">
                                                        <span class="badge-dot" style="background-color: {{ $room->color }}"></span>
                                                        Color ID
                                                    </small>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Capacity --}}
                                        <td>
                                            <span class="fw-semibold text-dark">{{ $room->capacity }} Orang</span>
                                        </td>

                                        {{-- Location --}}
                                        <td>
                                            <span class="text-muted">{{ $room->location ?? '-' }}</span>
                                        </td>

                                        {{-- Status --}}
                                        <td>
                                            @if($room->is_active)
                                                <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold" style="font-size: 10px;">ACTIVE</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-bold" style="font-size: 10px;">INACTIVE</span>
                                            @endif
                                        </td>

                                        {{-- Action --}}
                                        <td class="text-end pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-icon btn-light rounded-circle border-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#ModalRoomUpdate{{ $room->id }}">
                                                            <i class="bi bi-pencil me-2 text-primary"></i> Edit Data
                                                        </button>
                                                    </li>
                                                    <li><hr class="dropdown-divider opacity-50"></li>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmDelete('{{ $room->id }}', '{{ $room->name }}')">
                                                            <i class="bi bi-trash me-2"></i> Hapus Ruangan
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-inbox fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Belum ada data ruangan meeting.</h6>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 4. PAGINATION FOOTER --}}
                <div class="card-footer bg-white border-top py-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div class="text-muted small fw-medium">
                            Menampilkan {{ $rooms->count() }} data ruangan
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

{{-- MODAL CREATE --}}
<div class="modal fade" id="ModalRoom" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Tambah Ruangan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('meeting-rooms.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Nama Ruangan</label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Meeting Room A">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold">Kapasitas (Org)</label>
                            <input type="number" name="capacity" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold">Warna Kalender</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="#3b71fe" title="Pilih warna untuk kalender">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Lokasi</label>
                        <input type="text" name="location" class="form-control" placeholder="Lantai / Gedung...">
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- MODAL EDIT (Looping) --}}
@foreach($rooms as $room)
<div class="modal fade" id="ModalRoomUpdate{{ $room->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Ruangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('meeting-rooms.update', $room->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Nama Ruangan</label>
                        <input type="text" name="name" class="form-control" value="{{ $room->name }}" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold">Kapasitas</label>
                            <input type="number" name="capacity" class="form-control" value="{{ $room->capacity }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold">Warna Kalender</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="{{ $room->color }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Lokasi</label>
                        <input type="text" name="location" class="form-control" value="{{ $room->location }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Status</label>
                        <select name="is_active" class="form-select">
                            <option value="1" {{ $room->is_active ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ !$room->is_active ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // Flash Messages
        @if(session('success')) Swal.fire({ icon: 'success', title: 'Berhasil!', text: '{{ session('success') }}', timer: 3000, showConfirmButton: false }); @endif
        @if(session('error')) Swal.fire({ icon: 'error', title: 'Gagal!', text: '{{ session('error') }}' }); @endif

        // Search Auto-submit
        let timer;
        $('#search-input').on('keyup', function() {
            clearTimeout(timer);
            timer = setTimeout(function() { $('#filter-form').submit(); }, 700);
        });
    });

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Hapus Ruangan?',
            text: "Ruang '" + name + "' akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/meeting-rooms/' + id,
                    type: 'POST',
                    data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
                    success: function(data) {
                        if (data.success) { Swal.fire('Terhapus!', 'Ruangan berhasil dihapus.', 'success').then(() => location.reload()); }
                        else { Swal.fire('Gagal!', data.message, 'error'); }
                    },
                    error: function() { Swal.fire('Error!', 'Terjadi kesalahan server.', 'error'); }
                });
            }
        });
    }
</script>

<style>
    /* Styling Konsisten dengan Ticket List */
    .bg-soft-primary { background-color: rgba(59, 113, 254, 0.1); }
    .badge-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
    .table thead th { letter-spacing: 0.05em; border-bottom: 1px solid #f1f5f9; }
    .form-control:focus, .form-select:focus { box-shadow: 0 0 0 3px rgba(59, 113, 254, 0.1); border-color: #3b71fe; }
</style>
@endsection