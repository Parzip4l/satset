@extends('partials.layouts.master')

@section('title', 'Jadwal Meeting | SatSet System')
@section('title-sub', 'Facility Management')
@section('pagetitle', 'Room Reservation')

@section('css')
    {{-- FullCalendar CSS --}}
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css' rel='stylesheet' />
    {{-- Select2 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
        /* Custom Calendar Override */
        .fc-toolbar-title { font-size: 1.25rem !important; font-weight: 800; color: #333; }
        .fc-button-primary { background-color: #3b71fe !important; border-color: #3b71fe !important; font-weight: 600; }
        .fc-button-active { background-color: #2f5bce !important; }
        .fc-daygrid-day.fc-day-today { background-color: rgba(59, 113, 254, 0.03) !important; }
        .fc-event { border: none; padding: 3px 5px; font-size: 0.85rem; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.1s; }
        .fc-event:hover { transform: scale(1.02); }
        
        /* Sidebar Legend */
        .room-legend { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .card-clean { border-radius: 12px; border: 0; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
    </style>
@endsection

@section('content')

<div class="container-fluid">
    <div class="row g-4">
        
        {{-- SIDEBAR: LEGEND & TOOLS --}}
        <div class="col-lg-3">
            
            {{-- Action Card --}}
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <div class="avatar-md bg-soft-primary text-primary rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="bi bi-calendar-plus fs-2"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold">Booking Cepat</h5>
                    <p class="text-muted small mb-4">Buat reservasi ruangan meeting baru dengan mudah.</p>
                    <button class="btn btn-primary w-100 shadow-sm py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#ModalBooking">
                        <i class="bi bi-plus-lg me-1"></i> Buat Reservasi
                    </button>
                </div>
            </div>

            {{-- Legend List --}}
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-uppercase fs-12 text-muted">Daftar Ruangan</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($rooms as $room)
                        <li class="list-group-item d-flex align-items-center justify-content-between py-3 px-4 border-bottom-0">
                            <div class="d-flex align-items-center">
                                <span class="room-legend" style="background-color: {{ $room->color }}"></span>
                                <span class="fw-semibold text-dark">{{ $room->name }}</span>
                            </div>
                            <span class="badge bg-light text-muted border">{{ $room->capacity }} Pax</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

        </div>

        {{-- MAIN CALENDAR --}}
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; min-height: 650px;">
                <div class="card-body p-4">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- MODAL BOOKING --}}
<div class="modal fade" id="ModalBooking" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Reservasi Ruangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="{{ route('booking.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Judul Meeting</label>
                        <input type="text" name="title" class="form-control" placeholder="Contoh: Weekly Sync" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Pilih Ruangan</label>
                        <select name="meeting_room_id" class="form-select select2-modal" style="width: 100%;" required>
                            <option value="">-- Pilih Ruangan --</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}">{{ $room->name }} (Kapasitas: {{ $room->capacity }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold">Mulai</label>
                            <input type="datetime-local" name="start_time" id="start_time" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold">Selesai</label>
                            <input type="datetime-local" name="end_time" id="end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold">Deskripsi / Catatan</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Opsional: Link zoom, agenda, dll..."></textarea>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary py-2 fw-bold shadow-sm">Konfirmasi Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DETAIL --}}
<div class="modal fade" id="ModalEventDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <span id="detail-badge" class="badge bg-primary px-3 py-2 rounded-pill shadow-sm">Room Name</span>
                </div>
                <h5 class="fw-bold mb-1 text-dark" id="detail-title">Meeting Title</h5>
                <p class="text-muted small mb-3 fw-medium" id="detail-time">Time</p>
                
                <div class="p-3 bg-light rounded-3 text-start mb-3 border border-dashed">
                    <small class="text-muted d-block fw-bold mb-1">DESKRIPSI:</small>
                    <span class="text-dark small" id="detail-desc">-</span>
                </div>

                <div class="d-flex align-items-center justify-content-center gap-2 text-muted small mb-4">
                    <i class="bi bi-person-circle"></i>
                    <span>Booked by: <strong class="text-dark" id="detail-user">User</strong></span>
                </div>
                
                {{-- Tombol Action --}}
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Tutup</button>
                    
                    {{-- Tombol Delete (Hidden by default, shown via JS) --}}
                    <button type="button" id="btn-delete-event" class="btn btn-danger flex-fill d-none">
                        <i class="bi bi-trash me-1"></i> Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Init Select2
        $('.select2-modal').select2({ theme: 'bootstrap-5', dropdownParent: $('#ModalBooking') });

        // Init Calendar
        var calendarEl = document.getElementById('calendar');
        let currentEventId = null;
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            themeSystem: 'bootstrap5',
            events: "{{ route('booking.events') }}",
            
            // Date Click -> Open Booking
            dateClick: function(info) {
                var clickedDate = info.dateStr + 'T09:00';
                var endDate = info.dateStr + 'T10:00';
                $('#start_time').val(clickedDate);
                $('#end_time').val(endDate);
                $('#ModalBooking').modal('show');
            },

            // Event Click -> Show Detail
            eventClick: function(info) {
                var event = info.event;
                var props = event.extendedProps;
                
                // Simpan ID event ke variable
                currentEventId = event.id;

                $('#detail-title').text(props.title_meeting || event.title); // Gunakan title asli jika ada
                $('#detail-user').text(props.user_name);
                $('#detail-desc').text(props.description || '-');
                
                // Format Waktu
                const options = { hour: '2-digit', minute: '2-digit' };
                const timeString = event.start.toLocaleTimeString([], options) + ' - ' + (event.end ? event.end.toLocaleTimeString([], options) : '');
                $('#detail-time').text(event.start.toLocaleDateString() + ' • ' + timeString);
                
                // Styling Badge
                $('#detail-badge').text(props.room_name).css('background-color', event.backgroundColor);
                
                // LOGIKA TOMBOL DELETE
                // Jika props.can_delete TRUE (dikirim dari controller), maka munculkan tombol
                if (props.can_delete) {
                    $('#btn-delete-event').removeClass('d-none');
                } else {
                    $('#btn-delete-event').addClass('d-none');
                }
                
                $('#ModalEventDetail').modal('show');
            }
        });
        calendar.render();

        $('#btn-delete-event').click(function() {
            if (!currentEventId) return;

            Swal.fire({
                title: 'Batalkan Meeting?',
                text: "Jadwal ini akan dihapus permanen.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/booking-delete/' + currentEventId,
                        type: 'DELETE', // Method DELETE
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#ModalEventDetail').modal('hide');
                                // Refresh Kalender otomatis tanpa reload page
                                calendar.refetchEvents(); 
                                Swal.fire('Terhapus!', response.message, 'success');
                            } else {
                                Swal.fire('Gagal!', response.message, 'error');
                            }
                        },
                        error: function(xhr) {
                            Swal.fire('Error!', 'Terjadi kesalahan server.', 'error');
                        }
                    });
                }
            });
        });

        // Flash Messages
        @if(session('success')) Swal.fire({ icon: 'success', title: 'Berhasil!', text: '{{ session('success') }}', timer: 2000, showConfirmButton: false }); @endif
        @if(session('error')) Swal.fire({ icon: 'error', title: 'Gagal!', text: '{{ session('error') }}' }); @endif
    });
</script>
@endsection