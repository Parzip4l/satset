@extends('partials.layouts.master')

@section('title', 'Data Ticket | SatSet System')
@section('title-sub', 'Ticket Management')
@section('pagetitle', 'Ticket List')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- 1. HEADER CARD --}}
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">Ticket Management</h4>
                            <p class="text-muted mb-0">Pantau dan kelola semua permintaan bantuan teknis di sini.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('ticket.create') }}" class="btn btn-primary shadow-sm px-4">
                                <i class="bi bi-plus-lg me-1"></i> Buat Ticket Baru
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. FILTER & SEARCH TOOLBAR --}}
            <div class="card border-0 mb-4 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form id="filter-form" method="GET" action="{{ route('ticket.index') }}">
                        <div class="row g-3 align-items-center">
                            {{-- Search --}}
                            <div class="col-md-4">
                                <div class="position-relative">
                                    {{-- PENTING: Atribut 'name' harus ada --}}
                                    <input type="text" id="search-input" name="search" class="form-control ps-5" 
                                        value="{{ request('search') }}" 
                                        placeholder="Cari No. Ticket atau Judul..."> 
                                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                                        
                                    </span>
                                </div>
                            </div>

                            {{-- Filter Priority --}}
                            <div class="col-md-3">
                                <select class="form-select" id="priority-filter" name="priority_id" onchange="this.form.submit()">
                                    <option value="">Semua Prioritas</option>
                                    @foreach($priority as $p)
                                        <option value="{{ $p->id }}" {{ request('priority_id') == $p->id ? 'selected' : '' }}>
                                            {{ $p->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Filter Status --}}
                            <div class="col-md-3">
                                <select class="form-select" id="status-filter" name="status_id" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    @foreach($status as $s)
                                        <option value="{{ $s->id }}" {{ request('status_id') == $s->id ? 'selected' : '' }}>
                                            {{ $s->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <a href="{{ route('ticket.index') }}" class="btn btn-outline-danger w-100">Reset</a>
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
                                    <th class="ps-4 py-3">Informasi Ticket</th>
                                    <th class="py-3">Requester</th>
                                    <th class="py-3">Prioritas</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Tgl Dibuat</th>
                                    <th class="text-end pe-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="ticket-table-body" class="border-top-0">
                                @forelse($tickets as $ticket)
                                    @php
                                        // Warna Status
                                        $statusName = $ticket->status->name ?? 'Unknown';
                                        $statusColor = match($statusName) {
                                            'Open' => 'primary',
                                            'In Progress' => 'warning',
                                            'Closed' => 'success',
                                            'Rejected' => 'danger',
                                            default => 'secondary'
                                        };

                                        // Warna Priority
                                        $priorityName = $ticket->priority->name ?? 'Low';
                                        $priorityColor = match($priorityName) {
                                            'Urgent', 'High' => 'danger',
                                            'Medium' => 'warning',
                                            default => 'info'
                                        };
                                    @endphp
                                    <tr>
                                        {{-- Ticket Info --}}
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar-sm bg-soft-{{ $statusColor }} text-{{ $statusColor }} rounded d-flex align-items-center justify-content-center fw-bold" style="min-width: 40px; height: 40px;">
                                                    <i class="bi bi-ticket-perforated fs-5"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-0 text-dark">{{ $ticket->title }}</h6>
                                                    <small class="text-muted fw-medium">{{ $ticket->ticket_no }}</small>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Requester --}}
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-semibold text-dark">{{ $ticket->requester->name ?? '-' }}</span>
                                                <small class="text-muted">{{ $ticket->requester->department->name ?? 'General' }}</small>
                                            </div>
                                        </td>

                                        {{-- Priority --}}
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge badge-dot bg-{{ $priorityColor }}"></span>
                                                <span class="text-dark fw-medium">{{ $priorityName }}</span>
                                            </div>
                                        </td>

                                        {{-- Status --}}
                                        <td>
                                            <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} px-3 py-2 rounded-pill fw-bold" style="font-size: 10px;">
                                                {{ strtoupper($statusName) }}
                                            </span>
                                        </td>

                                        {{-- Timeline --}}
                                        <td>
                                            <div class="d-flex flex-column text-muted fs-12">
                                                <span class="text-dark fw-medium">{{ $ticket->created_at->format('d M Y') }}</span>
                                                <span>{{ $ticket->created_at->format('H:i') }} WIB</span>
                                            </div>
                                        </td>

                                        {{-- Action --}}
                                        <td class="text-end pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-icon btn-light rounded-circle border-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 10px;">
                                                    <li>
                                                        <a class="dropdown-item py-2" href="{{ route('ticket.show', $ticket->id) }}">
                                                            <i class="bi bi-eye me-2 text-primary"></i> Lihat Detail
                                                        </a>
                                                    </li>
                                                    @if ($statusName == 'Open')
                                                    <li><hr class="dropdown-divider opacity-50"></li>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmDelete('{{ $ticket->id }}', '{{ $ticket->ticket_no }}')">
                                                            <i class="bi bi-trash me-2"></i> Hapus Ticket
                                                        </button>
                                                    </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="mb-3">
                                                <i class="bi bi-inbox fs-1 text-muted opacity-25"></i>
                                            </div>
                                            <h6 class="text-muted fw-normal">Tidak ada data ticket ditemukan.</h6>
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
                            Menampilkan {{ $tickets->firstItem() ?? 0 }} sampai {{ $tickets->lastItem() ?? 0 }} dari {{ $tickets->total() }} data
                        </div>
                        <div class="pagination-container">
                            {{ $tickets->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // Auto-submit saat filter berubah
        $('#priority-filter, #status-filter').on('change', function() {
            $('#filter-form').submit();
        });

        // Delay submit untuk input search agar tidak terlalu berat
        let timer;
        $('#search-input').on('keyup', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                $('#filter-form').submit();
            }, 700);
        });
    });

    function confirmDelete(id, ticketNo) {
        Swal.fire({
            title: 'Hapus Ticket?',
            text: "Ticket " + ticketNo + " akan dihapus permanen. Tindakan ini tidak bisa dibatalkan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/ticket/' + id,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.success) {
                            Swal.fire('Berhasil!', 'Ticket telah dihapus.', 'success')
                            .then(() => location.reload());
                        } else {
                            Swal.fire('Gagal!', data.message || 'Terjadi kesalahan.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Terjadi kesalahan pada server.', 'error');
                    }
                });
            }
        });
    }
</script>

<style>
    /* Styling Tambahan untuk Look Modern */
    .bg-soft-primary { background-color: rgba(59, 113, 254, 0.1); }
    .bg-soft-warning { background-color: rgba(255, 184, 34, 0.1); }
    .bg-soft-success { background-color: rgba(28, 209, 100, 0.1); }
    .bg-soft-danger { background-color: rgba(255, 70, 70, 0.1); }
    
    .badge-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }

    .table thead th {
        letter-spacing: 0.05em;
        border-bottom: 1px solid #f1f5f9;
    }

    .form-select, .form-control {
        border-color: #e2e8f0;
        border-radius: 8px;
    }

    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 0 3px rgba(59, 113, 254, 0.1);
        border-color: #3b71fe;
    }
</style>
@endsection