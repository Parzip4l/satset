<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Setting\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class userController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');  // Get the search term from the request

        $user = User::when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
        })->paginate(10);

        $role = $this->availableRolesFor(Auth::user());

        if ($request->ajax()) {
            return view('general.user.list', compact('user', 'role'))->render();  // Return the table rows without reloading the page
        }

        return view('general.user.list', compact('user', 'role'));
    }

    public function create()
    {
        $role = $this->availableRolesFor(Auth::user());

        return view('general.user.create',compact('role')); 
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|max:50',
            'password' => 'required|string|min:8',
        ]);

        // Jika validasi gagal, kembalikan response error
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->input('name'),
                'username' => $request->input('username'),
                'phone' => '0',
                'email' => $request->input('email'),
                'role' => $request->input('role'),
                'password' => Hash::make($request->input('password')),
                'remember_token' => Str::random(60), // Menghasilkan token acak untuk 'remember_token'
            ]);

            // Mengembalikan response sukses
            return redirect()->back()->with('success', 'User created successfully!');
            
        } catch (\Exception $e) {
            // Menangani error jika terjadi masalah saat membuat user
            return redirect()->back()->with('success', 'Failed to create user:' . $e->getMessage());
        }
    }

    public function show($id)
    {

    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'role' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->has('username')) {
                $user->username = $request->input('username');
            }

            if ($request->has('email')) {
                $user->email = $request->input('email');
            }

            if ($request->filled('password')) {
                $user->password = Hash::make($request->input('password'));
            }

            if (strtolower(Auth::user()->role ?? '') === 'admin' && $request->filled('role')) {
                $user->role = $request->input('role');
            }

            $user->save();

            return redirect()->back()->with('success', 'User updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }


    public function destroy($id)
    {
        $currentUser = auth()->user();
        if ($currentUser->role !== 'admin') {
            return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk menghapus user.');
        }

        try {
            $user = User::findOrFail($id);

            // Tambahan: Cegah admin menghapus dirinya sendiri (opsional)
            if ($currentUser->id === $user->id) {
                return redirect()->back()->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
            }

            $user->delete();

            return redirect()->back()->with('success', 'User berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus user: ' . $e->getMessage());
        }
    }

    private function availableRolesFor(?User $user): Collection
    {
        $roles = strtolower($user->role ?? '') === 'admin'
            ? Role::query()->pluck('name')
            : Role::query()->where('name', 'pelapor')->pluck('name');

        if (strtolower($user->role ?? '') === 'admin') {
            $roles = $roles->merge(['admin', 'pelapor', 'ga']);
        }

        if ($roles->isEmpty()) {
            $roles = collect(['pelapor']);
        }

        return $roles
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($name) => (object) ['name' => $name]);
    }

}
