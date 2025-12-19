<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\General\Distributor;


class DistributorController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $province = $request->get('provinsi'); // Ambil filter provinsi dari request
        $city = $request->get('kabupaten');   // Ambil filter kota dari request

        $distributor = Distributor::when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('city', 'like', '%' . $search . '%');
        })
        ->when($province, function ($query, $province) {
            return $query->where('province', $province);
        })
        ->when($city, function ($query, $city) {
            return $query->where('city', $city);
        })
        ->paginate(10);

        if ($request->ajax()) {
            return view('users.seller.list', compact('distributor'))->render();
        }

        return view('users.seller.list', compact('distributor'));
    }


    public function create()
    {
        return view('users.seller.create');
    }

    public function store(Request $request)
    {

        try {
            // Create a new distributor record
            $distributor = new Distributor();
            $distributor->name = $request->name;
            $distributor->email = $request->email;
            $distributor->phone = $request->phone;
            $distributor->province = $request->provinsi;
            $distributor->city = $request->kabupaten;
            $distributor->address_details = $request->alamat;

            // Save the distributor information to the database
            $distributor->save();

            // Redirect with a success message
            return redirect()->route('distributor.index')->with('success', 'Distributor information saved successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to save distributor information. Please try again. Error: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $distributor = Distributor::findOrFail($id);

            return view('users.seller.details', compact('distributor'));
        } catch (Exception $e) {
            // Jika terjadi error, tampilkan pesan error
            return redirect()->route('users.seller.list')->with('error', 'Distributor not found!');
        }
    }

    public function edit($id)
    {
        try {
            $distributor = Distributor::findOrFail($id);

            return view('users.seller.edit', compact('distributor'));
        } catch (Exception $e) {
            // Jika terjadi error, tampilkan pesan error
            return redirect()->route('users.seller.list')->with('error', 'Distributor not found!');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Find the distributor record by ID
            $distributor = Distributor::findOrFail($id);

            // Update the distributor information
            $distributor->name = $request->name;
            $distributor->email = $request->email;
            $distributor->phone = $request->phone;
            $distributor->province = $request->provinsi;
            $distributor->city = $request->kabupaten;
            $distributor->address_details = $request->alamat;

            // Save the changes to the database
            $distributor->save();

            // Redirect with a success message
            return redirect()->route('distributor.index')->with('success', 'Distributor information updated successfully!');
        } catch (\Exception $e) {
            // Handle any exceptions that occur during the process
            return back()->withErrors(['error' => 'Failed to update distributor information. Please try again. Error: ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        try {
            // Cari menu berdasarkan ID
            $distributor = Distributor::findOrFail($id);

            // Hapus menu
            $distributor->delete();

            // Mengembalikan response JSON dengan status sukses
            return response()->json([
                'success' => true,
                'message' => 'Distributor has been deleted successfully.'
            ]);
        } catch (\Exception $e) {
            // Log error dan kembalikan error response JSON
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete distributor. Please try again later. ' . $e->getMessage()
            ], 500); // Menggunakan status code 500 jika ada error server
        }
    }
}
