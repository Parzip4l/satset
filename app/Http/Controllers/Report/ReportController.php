<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use App\Notifications\LaporanTerkirimNotification;
use Illuminate\Support\Facades\Notification;

// Model
use App\Models\user;
use App\Models\Report\Reports;
use App\Models\Report\ReportsHistories;
use App\Models\Report\ReportAssignment;
use App\Models\Report\ReportFollowUp;
use App\Models\Master\Locations;
use App\Models\Master\Employee;
use App\Models\Master\Observation;
use App\Models\Master\Hazard;
use App\Models\Master\Divisions;
use App\Models\Master\KategoriBahaya;
use App\Models\Master\Notification as NotificationModel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status'); 
        $lokasi = $request->query('lokasi'); 
        $perPage = $request->query('per_page', 10);

        $user = Auth::user();
        $pic = Employee::where('nik', $user->nik)->first();

        $query = Reports::with(['user', 'location', 'observationType', 'division', 'hazardPotential']);

        // Jika pelapor, tampilkan hanya laporan miliknya
        if (strtolower($user->role) === 'pelapor') {
            $query->where('user_id', $user->id);
        }

        // Jika PIC, hanya tampilkan laporan dari divisi dia
        if (strtolower($user->role) === 'pic') {
            $query->where('division_id', $user->division_id);
        }

        // Filter pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', '%' . $search . '%')
                ->orWhere('nomor_laporan', 'like', '%' . $search . '%');
            });
        }

        // Filter berdasarkan status
        if ($status && $status !== '') {
            $query->where('status', $status);
        }

        // Filter Lokasi
        if ($lokasi && $lokasi !== '') {
            $query->where('location_id', $lokasi);
        }

        $reports = $query->orderBy('id', 'desc')->paginate($perPage);
        $divisi = Divisions::all();
        $lokasi = Locations::all();

        return view('report.index', compact('reports', 'divisi', 'status', 'search','lokasi'));
    }



    public function create()
    {
        $pengamatan = Observation::all();
        $lokasi = Locations::all();
        $hazard = Hazard::all();
        $bahaya = KategoriBahaya::all();
        
        return view('report.create',compact('lokasi','pengamatan','hazard','bahaya'));
    }

    public function store(Request $request)
    {
        try {
            // Validasi input dari user
            $validator = Validator::make($request->all(), [
                'judul' => 'required|string|max:255',
                'observation_type_id' => 'required|exists:observation_types,id',
                'location_id' => 'required|exists:locations,id',
                'detail_lokasi' => 'required|string|max:255',
                'keterangan' => 'required|string',
                'hazard_potential_id' => 'nullable|exists:hazard_potentials,id',
                'perlu_tindak_lanjut' => 'required|boolean',
                'division_id' => 'nullable|exists:divisions,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Ambil data validasi
            $data = $validator->validated();

            // Buat nomor laporan secara langsung di controller
            $lokasi = Locations::find($data['location_id']);
            $kodeLokasi = $lokasi ? $lokasi->kode : '000';
            $tanggal = now()->format('ym'); // Contoh: 2507
            $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            $nomorSurat = "LRTJ/{$kodeLokasi}/{$tanggal}/{$random}";

            if ($request->has('foto_base64')) {
                $fotoData = $request->foto_base64;
                $fotoData = str_replace('data:image/jpeg;base64,', '', $fotoData);
                $fotoData = str_replace(' ', '+', $fotoData);
                $fileName = 'foto_' . time() . '.jpg';
                Storage::put('public/laporan_foto/' . $fileName, base64_decode($fotoData));
            }

            $report = Reports::create([
                'user_id' => Auth::id(),
                'judul' => $request->judul,
                'observation_type_id' => $request->observation_type_id,
                'location_id' => $request->location_id,
                'detail_lokasi' => $request->detail_lokasi,
                'keterangan' => $request->keterangan,
                'hazard_potential_id' => $request->hazard_potential_id,
                'perlu_tindak_lanjut' => $request->perlu_tindak_lanjut,
                'bahaya_id' => $request->perlu_tindak_lanjut,
                'nomor_laporan' => $nomorSurat,
                'kode_perusahaan' => 'LRTJ',
                'foto' => $fileName,
                'tanggal_laporan' => now(),
            ]);

            $hashid = hashid_encode($report->id);

            ReportsHistories::create([
                'report_id' => $report->id,
                'user_id' => Auth::id(),
                'tipe' => 'open',
                'catatan' => 'Laporan dibuat oleh ' . Auth::user()->name,
                'tanggal' => now(),
            ]);

            $qsheUsers = User::where('role', 'qshe')->get();

            foreach ($qsheUsers as $qsheUser) {
                NotificationModel::create([
                    'user_id' => $qsheUser->id,
                    'title' => 'Laporan Baru Dibuat',
                    'message' => 'Laporan "' . $report->judul . '" telah dibuat dan menunggu pemeriksaan.',
                    'url' => route('laporan.show', $hashid),
                ]);
            }

            try {
                Auth::user()->notify(new LaporanTerkirimNotification($nomorSurat));
                Log::info('Email notifikasi berhasil dikirim ke: ' . Auth::user()->email . ' | Nomor Laporan: ' . $nomorSurat);
            } catch (\Exception $e) {
                Log::error('Gagal mengirim email notifikasi ke: ' . Auth::user()->email . ' | Error: ' . $e->getMessage());
            }

            return redirect()->route('laporan.index')->with('success', 'Laporan berhasil dibuat!');

        } catch (\Exception $e) {

            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show($hashid)
    {
        $id = hashid_decode($hashid);
        if (!$id) {
            return redirect()->back()->with('error', 'Laporan tidak valid.');
        }

        $data = Reports::find($id);

        if (!$data) {
            return redirect()->back()->with('error', 'Laporan tidak ditemukan.');
        }

        $history = ReportsHistories::where('report_id', $id)->get();
        $followup = ReportFollowUp::where('report_id', $id)->first();
        $divisi = Divisions::all();


        return view('report.show', compact('data','history','followup','divisi'));
    }

    public function reviewByQshe(Request $request, $hashid)
    {
        $id = hashid_decode($hashid);
        if (!$id) {
            return redirect()->back()->with('error', 'Laporan tidak valid.');
        }

        $report = Reports::find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }

        $user = Auth::user();
        
        if (!in_array($user->role->name ?? $user->role, ['qshe','admin'])) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk melakukan review.'], 403);
        }
        $request->validate([
            'action' => 'required|in:approve,reject',
            'catatan' => 'nullable|string',
        ]);

        if ($request->action === 'reject') {
            $report->update(['status' => 'rejected_by_qshe']);

            ReportsHistories::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'tipe' => 'rejected_by_qshe',
                'catatan' => $request->catatan ?? 'Ditolak oleh QSHE',
                'tanggal' => now(),
            ]);

            // Notifikasi
            NotificationModel::create([
                'user_id' => $report->user_id,
                'title' => 'Laporan Ditolak QSHE',
                'message' => 'Laporan "' . $report->judul . '" ditolak oleh QSHE. Catatan: ' . ($request->catatan ?? '-'),
                'url' => route('laporan.show', $hashid),
            ]);
            
            return redirect()->route('laporan.index')->with('success', 'Laporan berhasil ditolak dan dikembalikan ke pelapor!');
        }

        if ($request->action === 'approve') {
            

            $report->update([
                'status' => 'assigned_to_division',
                'division_id' => $request->division_id,
                'assigned_to' => null,
            ]);

            // Set to Report Assing
            ReportAssignment::create([
                'report_id' => $report->id,
                'assigned_by' => $user->id,
                'assigned_to' => null,
                'division_id' => $request->division_id,
                'is_agree' => null,
                'note' => $request->catatan ?? null,
            ]);

            ReportsHistories::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'tipe' => 'assigned_to_division',
                'catatan' => $request->catatan ?? 'Disetujui oleh QSHE, Menunggu Approval dari PIC',
                'tanggal' => now(),
            ]);

            // Notifikasi
            NotificationModel::create([
                'user_id' => $report->user_id,
                'title' => 'Laporan Disetujui QSHE',
                'message' => 'Laporan "' . $report->judul . '" disetujui QSHE dan diteruskan ke divisi terkait.',
                'url' => route('laporan.show', $hashid),
            ]);

            return redirect()->route('laporan.index')->with('success', 'Laporan disetujui dan diteruskan ke divisi/PIC.');
        }

        return redirect()->back()->with('error', 'Terjadi Kesalahan.');
    }

    public function reviewByPic(Request $request, $hashid)
    {
        $id = hashid_decode($hashid);
        if (!$id) {
            return redirect()->back()->with('error', 'Laporan tidak valid.');
        }

        $report = Reports::find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }
        
        $user = Auth::user();
        $pic = User::where('division_id', $user->division_id)->first();

        if ($report->division_id !== $pic->division_id) {
            return response()->json(['message' => 'Anda bukan PIC yang ditugaskan.'], 403);
        }

        $request->validate([
            'action' => 'required|in:approve,reject',
            'catatan' => 'nullable|string'
        ]);

        if ($request->action === 'reject') {
            $report->update([
                'status' => 'rejected_by_pic',
                'division_id' => null,
                'assigned_to' => null,
            ]);

            ReportsHistories::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'tipe' => 'rejected_by_pic',
                'catatan' => $request->catatan ?? 'Ditolak oleh PIC',
                'tanggal' => now(),
            ]);

            NotificationModel::create([
                'user_id' => $report->user_id,
                'title' => 'Laporan Ditolak PIC',
                'message' => 'Laporan Anda ditolak oleh PIC. Silakan cek dan perbaiki jika diperlukan.',
                'url' => route('laporan.show', $hashid),
                'is_read' => false,
            ]);

            return redirect()->route('laporan.index')->with('success', 'Laporan berhasil ditolak.');
        }

        if ($request->action === 'approve') {
            $report->update(['status' => 'follow_up_submitted']);

            ReportsHistories::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'tipe' => 'follow_up_submitted',
                'catatan' => $request->catatan ?? 'Diterima oleh PIC',
                'tanggal' => now(),
            ]);

            NotificationModel::create([
                'user_id' => $report->user_id, // atau user_id tujuan notifikasi
                'title' => 'Laporan Diterima PIC',
                'message' => 'Laporan Anda telah diterima dan akan segera ditindaklanjuti.',
                'url' => route('laporan.show', $hashid),
                'is_read' => false,
            ]);

            return redirect()->route('laporan.index')->with('success', 'Laporan berhasil diterima silahkan update progress !');
        }

        return response()->json(['message' => 'Aksi tidak valid.'], 400);
    }

    public function progresByPic(Request $request, $hashid)
    {
        $id = hashid_decode($hashid);
        if (!$id) {
            return redirect()->back()->with('error', 'Laporan tidak valid.');
        }

        $report = Reports::find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }
        
        $user = Auth::user();
        $pic = User::where('division_id', $user->division_id)->first();

        if ($report->division_id !== $pic->division_id) {
            return response()->json(['message' => 'Anda bukan PIC yang ditugaskan.'], 403);
        }

        $request->validate([
            'action' => 'required|in:1',
            'catatan' => 'nullable|string'
        ]);

        if ($request->has('foto_base64')) {
            $fotoData = $request->foto_base64;
            $fotoData = str_replace('data:image/jpeg;base64,', '', $fotoData);
            $fotoData = str_replace(' ', '+', $fotoData);
            $fileName = 'foto_' . time() . '.jpg';
            Storage::put('public/laporan_foto/' . $fileName, base64_decode($fotoData));
        }

        if ($request->action === '1') {
            $report->update(['status' => 'under_review_by_qshe']);

            ReportsHistories::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'tipe' => 'under_review_by_qshe',
                'catatan' => $request->catatan ?? 'Progress PIC selesai sedang direview oleh QSHE',
                'tanggal' => now(),
            ]);

            $followUp = ReportFollowUp::firstOrNew(['report_id' => $report->id]);

            $followUp->fill([
                'pic_id' => $report->division_id,
                'description' => $request->description,
                'attachment' => $fileName,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'is_approved' => null,
                'note' => null,
            ]);

            $followUp->save();

            return redirect()->route('laporan.index')->with('success', 'Laporan berhasil dikirim untuk direview QSHE !');
        }

        return response()->json(['message' => 'Aksi tidak valid.'], 400);
    }

    public function reviewProgress(Request $request, $hashid)
    {
        $id = hashid_decode($hashid);
        if (!$id) {
            return redirect()->back()->with('error', 'Laporan tidak valid.');
        }

        $report = Reports::find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }

        $reportFollowup = ReportFollowUp::where('report_id', $id)->first();
        $user = Auth::user();

        $request->validate([
            'is_approved' => 'required',
            'note' => 'nullable|string'
        ]);

        if ($request->is_approved == '0') {
            // Jika ditolak
            $report->update([
                'status' => 'follow_up_rejected',
            ]);

            $reportFollowup->update([
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'is_approved' => $request->is_approved,
                'note' => $request->note,
            ]);

            ReportsHistories::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'tipe' => 'follow_up_rejected',
                'catatan' => $request->note ?? 'Ditolak oleh QSHE dikembalikan ke PIC',
                'tanggal' => now(),
            ]);

            return redirect()->route('laporan.index')->with('success', 'Laporan berhasil ditolak.');
        }

        if ($request->is_approved == '1') {
            // Jika disetujui
            $report->update(['status' => 'closed']);

            $reportFollowup->update([
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'is_approved' => $request->is_approved,
                'note' => $request->note,
            ]);

            ReportsHistories::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'tipe' => 'closed',
                'catatan' => $request->note ?? 'Closed',
                'tanggal' => now(),
            ]);

            return redirect()->route('laporan.index')->with('success', 'Laporan berhasil diterima, silakan update progress!');
        }

        return response()->json(['message' => 'Aksi tidak valid.'], 400);
    }


    public function getPicByDivision($id)
    {
        $employees = Employee::where('division_id', $id)
            ->where('is_pic', true)
            ->with('user:id,name,email')
            ->get();

       return response()->json($employees);
    }

    public function destroy($hashid)
    {
        try {
            $id = hashid_decode($hashid);
            if (!$id) {
                return redirect()->back()->with('error', 'Laporan tidak valid.');
            }
            $report = Reports::findOrFail($id);
            
            // Hapus foto jika ada
            if ($report->foto && Storage::exists('public/laporan_foto/' . $report->foto)) {
                Storage::delete('public/laporan_foto/' . $report->foto);
            }

            // Hapus histori terkait
            ReportsHistories::where('report_id', $report->id)->delete();

            // Hapus laporan
            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data laporan berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menghapus laporan', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data laporan: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    public function sendReminder(Request $request)
    {
        try {
            $reportIds = $request->input('report_ids');

            $reports = Reports::whereIn('id', $reportIds)
                ->where('status', 'assigned_to_division')
                ->get();

            if ($reports->isEmpty()) {
                return response()->json(['message' => 'Tidak ada laporan yang bisa di-reminder'], 404);
            }

            foreach ($reports as $report) {
                // Ambil user yang menjadi PIC di divisi laporan
                $picUsers = User::where('division_id', $report->division_id)
                    ->where('is_pic', 1)
                    ->get();

                if ($picUsers->isEmpty()) {
                    Log::warning("Tidak ada PIC untuk divisi {$report->division_id} pada laporan {$report->id}");
                    continue;
                }

                foreach ($picUsers as $user) {
                    // Simpan notifikasi ke DB
                    NotificationModel::create([
                        'user_id' => $user->id,
                        'title'   => 'Reminder Tindak Lanjut Laporan',
                        'message' => 'Laporan "' . $report->judul . '" masih belum ditindaklanjuti.',
                        'url'     => route('laporan.show', hashid_encode($report->id)),
                    ]);

                    // Kirim email
                    try {
                        $user->notify(new \App\Notifications\ReminderLaporanNotification($report));
                        Log::info("Reminder berhasil dikirim ke email: {$user->email} untuk laporan: {$report->judul}");
                    } catch (\Exception $e) {
                        Log::error("Gagal kirim email reminder ke {$user->email}: " . $e->getMessage());
                    }
                }
            }

            return response()->json(['message' => 'Reminder berhasil dikirim!']);

        } catch (\Exception $e) {
            Log::error("Error saat kirim reminder: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
