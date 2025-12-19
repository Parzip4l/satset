<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Model
use App\Models\Master\Department;
use App\Models\Master\ProblemCategory;
use App\Models\Master\DepartmentCategory;

class DepartmentCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $data = DepartmentCategory::with(['department','category'])
            ->when($search, function($query, $search){
                $query->whereHas('department', function($q) use ($search){
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('category', function($q) use ($search){
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->paginate(10);
        $department = Department::all();
        $problem = ProblemCategory::all();
        if ($request->ajax()) {
            return view('master.departement.category_assignment', compact('data','department','problem'))->render();
        }
        return view('master.departement.category_assignment', compact('data','department','problem'));
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
                'category_id' => 'required|exists:problem_categories,id',
                'department_id' => 'required|exists:departments,id',
            ]);

            DepartmentCategory::create([
                'category_id' => $request->category_id,
                'department_id' => $request->department_id,
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
                'category_id' => 'required|exists:problem_categories,id',
                'department_id' => 'required|exists:departments,id',
            ]);

            $department = DepartmentCategory::findOrFail($id);
            $department->update([
                'category_id' => $request->category_id,
                'department_id' => $request->department_id,
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
            $department = DepartmentCategory::findOrFail($id);
            $department->delete();

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
