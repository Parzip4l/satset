<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Models\Master\DepartmentCategory;
use App\Models\Master\ProblemCategory;
use App\Models\Master\Status;
use App\Models\Master\TicketHistory;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Mail\TicketCreated;
use Carbon\Carbon;

// Import Resource
use App\Http\Resources\TicketHistoryResource;
use App\Http\Resources\TicketResource;

class TicketApiController extends Controller
{
    public function index(Request $request)
    {
        // ======================================================================
        // 1. IDENTIFIKASI USER 
        // ======================================================================
        $email = $request->input('email');

        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email wajib dikirim.'], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
        }

        // ======================================================================
        // 2. TANGKAP FILTER
        // ======================================================================
        $search       = $request->input('search');
        
        $priorityId   = $request->input('priority'); 
        $statusId     = $request->input('status');
        $categoryId   = $request->input('category');
        $departmentId = $request->input('department');

        // ======================================================================
        // 3. QUERY DATABASE
        // ======================================================================
        $tickets = Ticket::with(['requester', 'category', 'priority', 'status', 'impact', 'urgency', 'department'])
            
            ->where('requester_id', $user->id)

            // --- FILTER LOGIC ---
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                      ->orWhere('ticket_no', 'like', '%' . $search . '%');
                });
            })

            ->when($statusId, fn($q) => $q->where('status_id', $statusId))
            ->when($priorityId, fn($q) => $q->where('priority_id', $priorityId))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            
            ->latest()
            ->paginate(10);

        // ======================================================================
        // 4. RETURN RESPONSE
        // ======================================================================
        return TicketResource::collection($tickets)->additional([
            'success' => true,
            'message' => 'List Tickets Data',
            'user_email' => $user->email
        ]);
    }

    public function show(Request $request, $id)
    {
        // ======================================================================
        // 1. IDENTIFIKASI USER (Sama dengan Index & History)
        // ======================================================================
        $email = $request->input('email');

        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email wajib dikirim.'], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
        }

        // ======================================================================
        // 2. QUERY DATABASE (Get Detail)
        // ======================================================================
        $ticket = Ticket::with([
                'requester', 
                'category', 
                'priority', 
                'status', 
                'impact', 
                'urgency', 
                'department'
            ])
            ->find($id);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket tidak ditemukan.'], 404);
        }

        // ======================================================================
        // 3. SECURITY CHECK (Authorization)
        // ======================================================================
        if ($ticket->requester_id != $user->id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses ke tiket ini.'], 403);
        }

        // ======================================================================
        // 4. RETURN RESPONSE
        // ======================================================================
        return (new TicketResource($ticket))->additional([
            'success' => true,
            'message' => 'Detail Ticket Data',
            'user_email' => $user->email
        ]);
    }

    public function history(Request $request, $id)
    {
        // 1. IDENTIFIKASI USER (Via Email)
        $email = $request->input('email');
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email wajib dikirim.'], 400);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
        }

        // 2. CARI TIKET
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket tidak ditemukan.'], 404);
        }

        // 3. SECURITY CHECK
        // Pastikan user hanya bisa melihat history tiket miliknya sendiri
        if ($ticket->requester_id != $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this ticket.'], 403);
        }

        // 4. AMBIL HISTORY
        // Mengambil data history, urutkan dari yang terbaru (atau terlama sesuai kebutuhan)
        // Pastikan diload relasi 'user' agar tau siapa yang update history tsb
        $histories = $ticket->histories()
                            ->with(['status']) 
                            ->latest()
                            ->get();

        return TicketHistoryResource::collection($histories)->additional([
            'success' => true,
            'message' => 'List Ticket History'
        ]);
    }

    public function store(Request $request)
    {
        // 1. IDENTIFIKASI USER (Via Email)
        $email = $request->input('email');
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email wajib dikirim.'], 400);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
        }

        // 2. VALIDASI INPUT
        $validator = Validator::make($request->all(), [
            'category_id'        => 'required|exists:problem_categories,id',
            'title'              => 'required|string|max:150',
            'description'        => 'nullable|string',
            'priority_id'        => 'nullable|exists:priorities,id',
            'impact_id'          => 'nullable|exists:impacts,id',
            'urgency_id'         => 'nullable|exists:urgencies,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // 3. LOGIKA DEPARTMENT & NO TIKET
            
            // Ambil department terkait kategori
            $departments = DepartmentCategory::with('department')
                ->where('category_id', $request->category_id)
                ->get()
                ->pluck('department');

            $assignedDepartment = $departments->first();

            // Generate ticket no
            $ticketNo = $this->generateTicketNo($request->category_id);

            // Default Status (Open / ID 1)
            $defaultStatus = Status::where('name', 'Open')->first();
            $defaultStatusId = $defaultStatus ? $defaultStatus->id : 1;

            // 4. CREATE TICKET
            $ticket = Ticket::create([
                'ticket_no'              => $ticketNo,
                'requester_id'           => $user->id, // Pakai ID dari user email tadi
                'department_id'          => $assignedDepartment->id ?? null,
                'assigned_department_id' => $assignedDepartment->id ?? null,
                'category_id'            => $request->category_id,
                'title'                  => $request->title,
                'description'            => $request->description,
                'priority_id'            => $request->priority_id,
                'impact_id'              => $request->impact_id,
                'urgency_id'             => $request->urgency_id,
                'ticket_category_id'     => $request->ticket_category_id,
                'status_id'              => $defaultStatusId,
            ]);

            // 5. CREATE HISTORY
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id'   => $user->id,
                'status_id' => $ticket->status_id,
                'action'    => 'Ticket Created',
            ]);

            DB::commit();

            // 6. KIRIM EMAIL (Opsional - dalam try catch agar tidak error 500 jika mail server down)
            try {
                // Ke Requester
                // Mail::to($user->email)->send(new TicketCreated($ticket, 'requester'));
                
                // Ke Department
                /*
                foreach ($departments as $dep) {
                    if ($dep && $dep->email) {
                        Mail::to($dep->email)->send(new TicketCreated($ticket, 'department'));
                    }
                }
                */
            } catch (\Exception $e) {
                Log::error("Gagal kirim email: " . $e->getMessage());
            }

            return (new TicketResource($ticket))->additional([
                'success' => true,
                'message' => 'Ticket berhasil dibuat.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("API Store Ticket Error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat tiket.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // 1. IDENTIFIKASI USER
        $email = $request->input('email');
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email wajib dikirim.'], 400);
        }
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
        }

        // 2. CEK TIKET
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket tidak ditemukan.'], 404);
        }

        // 3. AUTHORIZATION (Hanya pemilik tiket yang boleh edit via API ini)
        if ($ticket->requester_id != $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        // 4. VALIDASI
        $validator = Validator::make($request->all(), [
            'category_id'        => 'required|exists:problem_categories,id',
            'title'              => 'required|string|max:150',
            'description'        => 'nullable|string',
            'priority_id'        => 'nullable|exists:priorities,id',
            'impact_id'          => 'nullable|exists:impacts,id',
            'urgency_id'         => 'nullable|exists:urgencies,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Cek perubahan kategori
            $categoryChanged = $ticket->category_id != $request->category_id;
            $assignedDepartmentId = $ticket->assigned_department_id;

            if ($categoryChanged) {
                $newDepartments = DepartmentCategory::with('department')
                    ->where('category_id', $request->category_id)
                    ->get()
                    ->pluck('department');
                
                $assignedDepartmentId = $newDepartments->first()->id ?? null;
            }

            $updateData = [
                'category_id'        => $request->category_id,
                'title'              => $request->title,
                'description'        => $request->description,
                'priority_id'        => $request->priority_id,
                'impact_id'          => $request->impact_id,
                'urgency_id'         => $request->urgency_id,
                'ticket_category_id' => $request->ticket_category_id,
            ];

            if ($categoryChanged) {
                $updateData['department_id'] = $assignedDepartmentId;
                $updateData['assigned_department_id'] = $assignedDepartmentId;
            }

            $ticket->update($updateData);

            // Create History jika ada perubahan
            if ($ticket->wasChanged()) {
                $msg = 'Ticket Updated via API';
                if ($categoryChanged) $msg .= ' (Category Changed)';

                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => $user->id,
                    'status_id' => $ticket->status_id,
                    'action'    => $msg,
                ]);
            }

            DB::commit();

            return (new TicketResource($ticket->refresh()))->additional([
                'success' => true,
                'message' => 'Ticket berhasil diperbarui.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("API Update Ticket Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui tiket.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Helper Private Function
    protected function generateTicketNo($categoryId)
    {
        $category = ProblemCategory::find($categoryId);
        $categoryCode = $category ? strtoupper($category->code) : 'GEN';

        $datePart = Carbon::now()->format('mdy'); // mmddyy

        // ambil ticket terakhir hari ini untuk kategori tsb
        $lastTicket = Ticket::whereDate('created_at', Carbon::today())
            ->where('category_id', $categoryId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastTicket) {
            // ambil 4 digit terakhir dari ticket_no
            $lastNumber = (int) substr($lastTicket->ticket_no, -4);
            $runningNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $runningNumber = '0001';
        }

        return "TCK-{$categoryCode}-{$datePart}-{$runningNumber}";
    }
}