<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Models\Master\Observation;

class ObservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $observation = Observation::when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%');
        })->paginate(20);

        if ($request->ajax()) {
            return view('master.observation.index', compact('observation'))->render();
        }

        return view('master.observation.index', compact('observation'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:50'
            ]);

            Observation::create([
                'name' => $request->name,
            ]);

            return redirect()->back()->with('success', 'Data kategori pengamatan berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan data kategori pengamatan: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:50',
            ]);

            $observation = Observation::findOrFail($id);
            $observation->update([
                'name' => $request->name,
            ]);

            return redirect()->back()->with('success', 'Data kategori pengamatan berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui data kategori pengamatan: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
       try {
            $observation = Observation::findOrFail($id);
            $observation->delete();

            return response()->json([
                'success' => true,
                'message' => 'data kategori pengamatan berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data kategori pengamatan: ' . $e->getMessage()
            ]);
        }
    }
}
