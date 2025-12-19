<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Model
use App\Models\Master\ProblemCategory;
use App\Models\Master\Divisions;

class ProblemCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $problem = ProblemCategory::when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%');
        })->paginate(10);

        if ($request->ajax()) {
            return view('master.problem.index', compact('problem'))->render();
        }
        return view('master.problem.index', compact('problem'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:100',
                'code' => 'required|string|max:100'
            ]);

            ProblemCategory::create([
                'name' => $request->name,
                'code' => $request->code,
                'parent_id' => $request->parent_id,
                'description' => $request->description,
            ]);

            return redirect()->back()->with('success', 'Data berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
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
                'code' => 'required|string|max:100',
                'description' => 'required|string|max:100',
            ]);

            $problem = ProblemCategory::findOrFail($id);
            $problem->update([
                'name' => $request->name,
                'code' => $request->code,
                'parent_id' => $request->parent_id,
                'description' => $request->description
            ]);

            return redirect()->back()->with('success', 'Data berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $problem = ProblemCategory::findOrFail($id);

            // Hapus semua anak (recursive)
            $this->deleteChildren($problem->id);

            // Hapus parent terakhir
            $problem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data (beserta sub-kategori) berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Rekursif hapus child berdasarkan parent_id
     */
    private function deleteChildren($parentId)
    {
        $children = ProblemCategory::where('parent_id', $parentId)->get();

        foreach ($children as $child) {
            // panggil lagi untuk hapus cucu dll
            $this->deleteChildren($child->id);
            $child->delete();
        }
    }

}
