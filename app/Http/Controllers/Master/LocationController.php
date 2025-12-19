<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Model
use App\Models\Master\Locations;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $lokasi = Locations::when($search, function ($query, $search) {
            return $query->where('nama_lokasi', 'like', '%' . $search . '%')
            ->orwhere('kode', 'like', '%' . $search . '%');
        })->paginate(10);

        if ($request->ajax()) {
            return view('master.location.index', compact('lokasi'))->render();
        }

        return view('master.location.index', compact('lokasi'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('master.division.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'kode' => 'required|string|max:100',
                'nama_lokasi' => 'required|string|max:100',
            ]);

            Locations::create([
                'kode' => $request->kode,
                'nama_lokasi' => $request->nama_lokasi,
            ]);

            return redirect()->back()->with('success', 'Data Lokasi berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan data lokasi: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'kode' => 'required|string|max:100',
                'nama_lokasi' => 'required|string|max:100',
            ]);

            $lokasi = Locations::findOrFail($id);
            $lokasi->update([
                'kode' => $request->kode,
                'nama_lokasi' => $request->nama_lokasi,
            ]);

            return redirect()->back()->with('success', 'Data Lokasi berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui data lokasi: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $lokasi = Locations::findOrFail($id);
            $lokasi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Lokasi berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data Lokasi: ' . $e->getMessage()
            ]);
        }
    }
}
