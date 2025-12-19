<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Setting\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Master\Divisions;

class PicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $user = User::whereNotNull('division_id')
                    ->where('is_pic', 1)
                    ->when($search, function ($query, $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                        });
                    })
                    ->paginate(50);

        if ($request->ajax()) {
            return view('master.pic.index', compact('user'))->render();
        }

        $divisi = Divisions::all();
        $allUser = User::where('role','pelapor')
                    ->where('division_id', null)->where('is_pic', 0)->get();

        return view('master.pic.index', compact('user','divisi','allUser'));
    }

    public function updatePIC(Request $request)
    {
        $request->validate([
            'name' => 'required|exists:users,id',
            'divisi' => 'required|exists:divisions,id',
        ]);

        try {
            $user = User::findOrFail($request->name);

            $user->division_id = $request->divisi;
            $user->is_pic = 1;
            $user->role = 'pic';
            $user->save();

            return redirect()->back()->with('success', 'Data PIC berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
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
        //
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
        $request->validate([
            'name' => 'required|exists:users,id',
            'divisi' => 'required|exists:divisions,id',
        ]);

        try {
            $user = User::findOrFail($request->name);

            $user->division_id = $request->divisi;
            $user->is_pic = 1;
            $user->role = 'pic';
            $user->save();

            return redirect()->back()->with('success', 'Data PIC berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
