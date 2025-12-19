<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting\Role;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search'); 

        $role = Role::when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%');
        })
        ->paginate(10);

        if ($request->ajax()) {
            return view('auth.role.index', compact('role'))->render(); 
        }

        return view('auth.role.index', compact('role'));
    }

    public function create()
    {
        return view('auth.role.create');
    }

    public function store(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            // Simpan role baru
            $role = new Role();
            $role->name = $request->name;
            $role->save();

            // Redirect dengan pesan sukses
            return redirect()->route('role.index')->with('success', 'Menu created successfully.');
        } catch (\Exception $e) {
            // Log error dan kembalikan error response
            return back()->with('error', 'Failed to create role. Please try again later.'. $e->getMessages());
        }
    }

    public function edit($id)
    {
        try {
            // Ambil data menu berdasarkan id
            $roles = Role::findOrFail($id);

            return view('auth.role.edit', compact('roles'));
        } catch (Exception $e) {
            // Jika terjadi error, tampilkan pesan error
            return redirect()->route('auth.role.index')->with('error', 'Menu not found!');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Update data menu
            $role = Role::findOrFail($id);
            $role->name = $validated['name'];
            $role->save();

            // Redirect dengan pesan sukses
            return redirect()->route('role.index')->with('success', 'role updated successfully!');
        } catch (Exception $e) {
            // Jika terjadi error, tampilkan pesan error
            return redirect()->route('role.index')->with('error', 'An error occurred while updating the role.');
        }
    }

    public function destroy($id)
    {
        try {
            // Cari menu berdasarkan ID
            $role = Role::findOrFail($id);

            // Hapus menu
            $role->delete();

            // Mengembalikan response JSON dengan status sukses
            return response()->json([
                'success' => true,
                'message' => 'Menu has been deleted successfully.'
            ]);
        } catch (\Exception $e) {
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete menu. Please try again later. ' . $e->getMessage()
            ], 500); // Menggunakan status code 500 jika ada error server
        }
    }

}
