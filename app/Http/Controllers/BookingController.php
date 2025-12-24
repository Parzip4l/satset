<?php 

namespace App\Http\Controllers;

use App\Models\MeetingBooking;
use App\Models\MeetingRoom;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    // Menampilkan halaman kalender
    public function index()
    {
        // Ambil ruangan yang aktif saja
        $rooms = MeetingRoom::where('is_active', 1)->get();
        return view('booking.calendar', compact('rooms'));
    }

    // API untuk data JSON ke FullCalendar
    public function getEvents(Request $request)
    {
        $bookings = MeetingBooking::with(['room', 'user'])
            ->whereDate('start_time', '>=', $request->start)
            ->whereDate('end_time', '<=', $request->end)
            ->get();

        $events = [];

        foreach ($bookings as $booking) {
            $events[] = [
                'id' => $booking->id,
                'title' => $booking->room->name . ' - ' . $booking->title,
                'start' => $booking->start_time->toIso8601String(),
                'end' => $booking->end_time->toIso8601String(),
                'backgroundColor' => $booking->room->color,
                'borderColor' => $booking->room->color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'room_name' => $booking->room->name,
                    'user_name' => $booking->user->name,
                    'description' => $booking->description ?? '-',
                    'title_meeting' => $booking->title,
                    // Tambahkan ini untuk cek hak akses di JS
                    'can_delete' => $booking->user_id == auth()->id() 
                ]
            ];
        }

        return response()->json($events);
    }

    // Simpan Booking Baru
    public function store(Request $request)
    {
        $request->validate([
            'meeting_room_id' => 'required',
            'title' => 'required',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $start = Carbon::parse($request->start_time);
        $end = Carbon::parse($request->end_time);

        // 1. Cek Tabrakan Jadwal (Overlapping Check)
        // Logika: Cari booking di ruangan yang sama, dimana waktunya beririsan
        $isBooked = MeetingBooking::where('meeting_room_id', $request->meeting_room_id)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_time', [$start, $end])
                      ->orWhereBetween('end_time', [$start, $end])
                      ->orWhere(function ($q) use ($start, $end) {
                          $q->where('start_time', '<=', $start)
                            ->where('end_time', '>=', $end);
                      });
            })->exists();

        if ($isBooked) {
            return back()->with('error', 'Gagal! Ruangan sudah terisi pada jam tersebut.');
        }

        // 2. Simpan jika aman
        MeetingBooking::create([
            'user_id' => auth()->id(),
            'meeting_room_id' => $request->meeting_room_id,
            'title' => $request->title,
            'start_time' => $start,
            'end_time' => $end,
            'description' => $request->description,
            'status' => 'booked'
        ]);

        return back()->with('success', 'Ruangan berhasil direservasi!');
    }

    public function destroy($id)
    {
        $booking = MeetingBooking::findOrFail($id);

        // Validasi: Hanya pembuat booking yang boleh menghapus
        if ($booking->user_id != auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berhak menghapus booking ini!'], 403);
        }

        $booking->delete();

        return response()->json(['success' => true, 'message' => 'Booking berhasil dibatalkan.']);
    }
}