<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use App\Models\General\Menu;
use App\Models\Setting\Role;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search'); 

        // Query untuk mendapatkan data menu dengan filter pencarian jika ada
        $menu = Menu::when($search, function ($query, $search) {
            return $query->where('title', 'like', '%' . $search . '%');
        })
        ->paginate(30);

        // Jika permintaan AJAX, kembalikan hanya bagian tampilan yang perlu diperbarui
        if ($request->ajax()) {
            return view('general.menu.index', compact('menu'))->render(); 
        }

        // Untuk tampilan biasa, kirimkan data menu
        return view('general.menu.index', compact('menu', 'search'));
    }


    public function create()
    {   
        $menuData = Menu::whereNull('parent_id')->get();
        $roles = Role::all();
        return view('general.menu.create', compact('menuData','roles'));
    }

    public function store(Request $request)
    {
        
        // Validasi input
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:menus,id',
            'is_active' => 'required|in:1,0',
            'order' => 'nullable|integer',
        ]);

        try {
            // Simpan menu baru
            $menu = new Menu();
            $menu->title = $request->title;
            $menu->icon = $request->icon;
            $menu->url = $request->url;
            $menu->parent_id = $request->parent_id;
            $menu->is_active = $request->is_active;
            $menu->order = $request->order;
            $menu->role_id = $request->role_ids;
            $menu->save();

            // Redirect dengan pesan sukses
            return redirect()->route('menu.index')->with('success', 'Menu created successfully.');
        } catch (\Exception $e) {
            // Log error dan kembalikan error response
            Log::error('Error storing menu: ' . $e->getMessage());
            return back()->with('error', 'Failed to create menu. Please try again later.');
        }
    }

    public function edit($id)
    {
        try {
            // Ambil data menu berdasarkan id
            $roles = Role::all();
            $menu = Menu::findOrFail($id);
            // Ambil data menu parent untuk dropdown
            $menuData = Menu::whereNull('parent_id')->get();

            return view('general.menu.edit', compact('menu', 'menuData','roles'));
        } catch (Exception $e) {
            // Jika terjadi error, tampilkan pesan error
            return redirect()->route('general.menu.index')->with('error', 'Menu not found!');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'icon' => 'nullable|string|max:255',
                'url' => 'nullable|string|max:255',
                'parent_id' => 'nullable|exists:menus,id',
                'is_active' => 'required|boolean',
                'order' => 'required|integer',
            ]);

            // Update data menu
            $menu = Menu::findOrFail($id);
            $menu->title = $validated['title'];
            $menu->icon = $validated['icon'];
            $menu->url = $validated['url'];
            $menu->parent_id = $validated['parent_id'];
            $menu->is_active = $validated['is_active'];
            $menu->order = $validated['order'];
            $menu->role_id = $request->role_ids;
            $menu->save();

            // Redirect dengan pesan sukses
            return redirect()->route('menu.index')->with('success', 'Menu updated successfully!');
        } catch (Exception $e) {
            // Jika terjadi error, tampilkan pesan error
            return redirect()->route('menu.index')->with('error', 'An error occurred while updating the menu.');
        }
    }

    public function destroy($id)
    {
        try {
            // Cari menu berdasarkan ID
            $menu = Menu::findOrFail($id);

            // Hapus menu
            $menu->delete();

            // Mengembalikan response JSON dengan status sukses
            return response()->json([
                'success' => true,
                'message' => 'Menu has been deleted successfully.'
            ]);
        } catch (\Exception $e) {
            // Log error dan kembalikan error response JSON
            Log::error('Error deleting menu: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete menu. Please try again later. ' . $e->getMessage()
            ], 500); // Menggunakan status code 500 jika ada error server
        }
    }


    public function updateStatus(Request $request, $id)
    {

        // Validasi data yang dikirim
        $request->validate([
            'is_active' => 'required|boolean',
        ]);
        
        try {
            $data = Menu::findOrFail($id);

            // Perbarui status is_active
            $data->is_active = $request->is_active;
            $data->save();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

}
