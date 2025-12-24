<?php 

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;

class MeetingRoomController extends Controller
{
    public function index()
    {
        $rooms = MeetingRoom::all();
        return view('meeting-room.index', compact('rooms'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'capacity' => 'required|integer',
            'color' => 'required'
        ]);

        MeetingRoom::create($request->all());
        return back()->with('success', 'Ruangan berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $room = MeetingRoom::findOrFail($id);
        $room->update($request->all());
        return back()->with('success', 'Ruangan berhasil diperbarui');
    }

    public function destroy($id)
    {
        MeetingRoom::destroy($id);
        return back()->with('success', 'Ruangan dihapus');
    }
}