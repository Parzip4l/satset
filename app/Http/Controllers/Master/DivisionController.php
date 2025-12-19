<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Model
use App\Models\Master\Divisions;

class DivisionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $divisi = Divisions::when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%');
        })->paginate(10);

        if ($request->ajax()) {
            return view('master.division.index', compact('divisi'))->render();
        }

        return view('master.division.index', compact('divisi'));
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
                'name' => 'required|string|max:100',
            ]);

            Divisions::create([
                'name' => $request->name,
            ]);

            return redirect()->back()->with('success', 'Divisi berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan data divisi: ' . $e->getMessage());
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
                'name' => 'required|string|max:100',
            ]);

            $divisi = Divisions::findOrFail($id);
            $divisi->update([
                'name' => $request->name,
            ]);

            return redirect()->back()->with('success', 'Divisi berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui data divisi: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $divisi = Divisions::findOrFail($id);
            $divisi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Divisi berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data divisi: ' . $e->getMessage()
            ]);
        }
    }
}
