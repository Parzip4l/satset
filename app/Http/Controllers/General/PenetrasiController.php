<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\General\Penetrasi;
use App\Models\Setting\Slack;

class PenetrasiController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->get('search'); 

        // Query untuk mendapatkan data menu dengan filter pencarian jika ada
        $penetrasi = Penetrasi::when($search, function ($query, $search) {
            return $query->where('product', 'like', '%' . $search . '%');
        })
        ->orderBy('created_at', 'desc') // Urutkan berdasarkan created_at, dari terbaru ke terlama
        ->paginate(50);

        // Jika permintaan AJAX, kembalikan hanya bagian tampilan yang perlu diperbarui
        if ($request->ajax()) {
            return view('general.lab.penetrasi', compact('penetrasi'))->render(); 
        }

        // Untuk tampilan biasa, kirimkan data menu
        return view('general.lab.penetrasi', compact('penetrasi', 'search'));
    }

    public function store(Request $request)
    {
        try {
            // Simpan data pembelian
            $purchase = new Penetrasi(); // Generate UUID
            $purchase->batch = $request->batch;
            $purchase->product = $request->product;
            $purchase->p_process = $request->p_process;
            $purchase->k_process = $request->k_process;
            $purchase->k_fng = $request->k_fng;
            $purchase->p_fng = $request->p_fng;
            $purchase->checker = $request->checker;
            $purchase->save();

            $slackChannel = Slack::where('channel', 'QC')->first();
            $slackWebhookUrl = $slackChannel->url;
            $today = now()->toDateString();
            $data = [
                'text' => "Update Penetrasi Produksi {$today}",
                'attachments' => [
                    [
                        'title' => 'Data Check Penetrasi',
                        'fields' => [
                            [
                                'title' => 'Batch Number',
                                'value' => $request->batch,
                                'short' => true,
                            ],
                            [
                                'title' => 'Product',
                                'value' => $request->product,
                                'short' => true,
                            ],
                            [
                                'title' => 'Penetrasi Proses',
                                'value' => $request->p_process,
                                'short' => true,
                            ],
                            [
                                'title' => 'Keterangan Proses',
                                'value' => $request->k_process,
                                'short' => true,
                            ],
                            [
                                'title' => 'Penetrasi Finish Goods',
                                'value' => $request->p_fng,
                                'short' => true,
                            ],
                            [
                                'title' => 'Keterangan Finish Goods',
                                'value' => $request->k_fng,
                                'short' => true,
                            ],
                            [
                                'title' => 'Checker',
                                'value' => $request->checker,
                                'short' => true,
                            ],
                            [
                                'title' => 'Lihat Detail Data Di Champoil Portal',
                                'value' => '(https://dashboard.champoil.co.id/rnd-check)',
                                'short' => true,
                            ]
                        ],
                    ],
                ],
                
            ];

            $data_string = json_encode($data);

            $ch = curl_init($slackWebhookUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
            ]);

            $result = curl_exec($ch);

            if ($result === false) {
                // Penanganan kesalahan jika Curl gagal
                $error = curl_error($ch);
                // Handle the error here
                return redirect()->back()->with('error', 'Terjadi kesalahan saat mengirim data ke Slack: ' . $error);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200) {
                // Penanganan kesalahan jika Slack merespons selain status 200 OK
                // Handle the error here
                return redirect()->back()->with('error', 'Terjadi kesalahan saat mengirim data ke Slack. Kode status: ' . $httpCode);
            }

            curl_close($ch);
    
            return redirect()->route('rnd-check.index')->with('success', 'Data berhasil disimpan.');
        } catch (\Exception $e) {
            // Tangani kesalahan yang mungkin terjadi
            return redirect()->back()->with('error', 'Terjadi kesalahan saat menyimpan Data: ' . $e->getMessage());
        }
    }

    public function indexVolt()
    {
        return view('general.maintenance.voltage');
    }
}
