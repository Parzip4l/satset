<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Master\TicketFormSchema;
use App\Models\Master\TicketCategory;
use Illuminate\Support\Facades\DB;

class TicketFormSchemaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Mengambil schema beserta nama kategori tiketnya
        $schemas = TicketFormSchema::with('ticketCategory')->get();
        
        return view('master.form-schema.index', compact('schemas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Hanya ambil kategori yang BELUM punya schema agar tidak duplikat
        // Menggunakan whereDoesntHave atau check existing ID
        $existingCategoryIds = TicketFormSchema::pluck('ticket_category_id')->toArray();
        
        $categories = TicketCategory::whereNotIn('id', $existingCategoryIds)->get();

        return view('master.form-schema.create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'ticket_category_id' => 'required|unique:ticket_form_schemas,ticket_category_id',
            // Validasi schema harus berupa array (karena dari Form Builder JS biasanya kirim JSON/Array)
            'schema'             => 'required|array', 
            'schema.*.name'      => 'required|string', 
            'schema.*.label'     => 'required|string',
            'schema.*.type'      => 'required|string',
            'schema.*.options'   => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            TicketFormSchema::create([
                'ticket_category_id' => $request->ticket_category_id,
                'schema'             => $request->schema, // Laravel otomatis cast ke JSON
            ]);

            DB::commit();

            return redirect()->route('form-schema.index')
                             ->with('success', 'Schema Form berhasil dibuat.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['msg' => 'Gagal menyimpan: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $formSchema = TicketFormSchema::findOrFail($id);
        
        // Untuk edit, kita tampilkan kategori dia sendiri + kategori lain yg belum punya schema
        $existingCategoryIds = TicketFormSchema::where('id', '!=', $id)
                                ->pluck('ticket_category_id')
                                ->toArray();

        $categories = TicketCategory::whereNotIn('id', $existingCategoryIds)->get();

        return view('master.form-schema.edit', compact('formSchema', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $formSchema = TicketFormSchema::findOrFail($id);

        $request->validate([
            // Unique tapi abaikan ID yang sedang diedit
            'ticket_category_id' => 'required|unique:ticket_form_schemas,ticket_category_id,' . $id,
            'schema'             => 'required|array',
            'schema.*.name'      => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $formSchema->update([
                'ticket_category_id' => $request->ticket_category_id,
                'schema'             => $request->schema,
            ]);

            DB::commit();

            return redirect()->route('form-schema.index')
                             ->with('success', 'Schema Form berhasil diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['msg' => 'Gagal update: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $formSchema = TicketFormSchema::findOrFail($id);
        $formSchema->delete();

        return redirect()->route('form-schema.index')
                         ->with('success', 'Schema Form berhasil dihapus.');
    }

    /**
     * API: Mengambil Schema berdasarkan Category ID
     * Pengganti Route::get closure Anda.
     */
    public function getSchemaApi($categoryId)
    {
        $schemaModel = TicketFormSchema::where('ticket_category_id', $categoryId)->first();

        if (!$schemaModel) {
            return response()->json([], 200);
        }

        // Ambil data schema asli
        $rawSchema = $schemaModel->schema;

        // Transformasi data menggunakan Collection
        $formattedSchema = collect($rawSchema)->map(function ($item) {
            // Cek apakah tipe field adalah 'select'
            if (isset($item['type']) && $item['type'] === 'select') {
                
                // Cek apakah 'options' ada dan berupa STRING (contoh: "A,B,C")
                if (isset($item['options']) && is_string($item['options'])) {
                    // Ubah string menjadi array
                    $item['options'] = array_map('trim', explode(',', $item['options']));
                }
                
                // (Opsional) Jika options kosong/null, pastikan tetap return array kosong [] agar mobile tidak error
                if (empty($item['options'])) {
                    $item['options'] = [];
                }
            }

            return $item;
        });

        // Return hasil yang sudah diformat
        return response()->json($formattedSchema, 200);
    }
}