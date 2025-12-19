<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Model
use App\Models\Master\Priority;

class PriorityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $priority = Priority::when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%');
        })->paginate(10);

        if ($request->ajax()) {
            return view('master.priority.index', compact('priority'))->render();
        }

        return view('master.priority.index', compact('priority'));
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
                'code' => 'required|string|max:100',
            ]);

            Priority::create([
                'name' => $request->name,
                'code' => $request->code,
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
            ]);

            $priority = Priority::findOrFail($id);
            $priority->update([
                'name' => $request->name,
                'code' => $request->code,
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
            $priority = Priority::findOrFail($id);
            $priority->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ]);
        }
    }
}
