<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\TicketCreated;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

// Model
use App\Models\Master\Status;
use App\Models\Master\Priority;
use App\Models\Master\ProblemCategory;
use App\Models\Master\DepartmentCategory;
use App\Models\Master\Impact;
use App\Models\Master\Urgency;
use App\Models\Master\Department;
use App\Models\Master\Approval;
use App\Models\User;
use App\Models\Master\TicketCategory;


use App\Models\Master\TicketHistory;

class TicketController extends Controller
{
    /**
     * Display all tickets
     */
    public function index(Request $request)
    {
        $user         = auth()->user();
        $search       = $request->get('search');
        $statusId     = $request->get('status_id');
        $priorityId   = $request->get('priority_id');
        $categoryId   = $request->get('category_id');
        $departmentId = $request->get('department_id');

        $tickets = Ticket::with(['requester', 'category', 'priority', 'status', 'impact', 'urgency'])
            ->when($user->role !== 'admin', fn($q) => $q->where('requester_id', $user->id)) // Hanya milik user non-admin
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('ticket_no', 'like', '%' . $search . '%')
                    ->orWhereHas('requester', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%');
                    });
                });
            })
            ->when($statusId, fn($q) => $q->where('status_id', $statusId))
            ->when($priorityId, fn($q) => $q->where('priority_id', $priorityId))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            ->latest()
            ->paginate(10);

        // Ambil filter data sebelum return
        $users       = User::all();
        $priority    = Priority::all();
        $status      = Status::all();
        $categories  = ProblemCategory::all();
        $departments = Department::all();

        if ($request->ajax()) {
            return view('ticket.index', compact('tickets','status','priority','categories','users','departments'))->render();
        }

        return view('ticket.index', compact('tickets','status','priority','categories','users','departments'));
    }




    public function create()
    {
        $priority = Priority::all();
        $status = Status::all();
        $categories = ProblemCategory::all();
        $priorities = Priority::all();
        $impacts = Impact::all();
        $urgencies = Urgency::all();
        $statuses = Status::all();
        $categoryticket = TicketCategory::all();

        return view('ticket.create', compact('status','priority','categories','priorities','impacts','urgencies','statuses','categoryticket'));
    }

    /**
     * Store a newly created ticket
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id'    => 'required|exists:problem_categories,id',
            'title'          => 'required|string|max:150',
            'description'    => 'nullable|string',
            'priority_id'    => 'nullable|exists:priorities,id',
            'impact_id'      => 'nullable|exists:impacts,id',
            'urgency_id'     => 'nullable|exists:urgencies,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
        ]);

        // Ambil semua department terkait kategori
        $departments = DepartmentCategory::with('department')
            ->where('category_id', $request->category_id)
            ->get()
            ->pluck('department');

        // Ambil department pertama untuk assign tiket (jika ada)
        $assignedDepartment = $departments->first();

        // Generate ticket no
        $ticketNo = $this->generateTicketNo($request->category_id);

        // Create tiket
        $ticket = Ticket::create([
            'ticket_no'     => $ticketNo,
            'requester_id'  => auth()->id(),
            'department_id' => $assignedDepartment->id ?? null,
            'assigned_department_id' => $assignedDepartment->id ?? null,
            'category_id'   => $request->category_id,
            'title'         => $request->title,
            'description'   => $request->description,
            'priority_id'   => $request->priority_id,
            'impact_id'     => $request->impact_id,
            'urgency_id'    => $request->urgency_id,
            'ticket_category_id'    => $request->ticket_category_id,
            'status_id'     => Status::where('name', 'Open')->first()->id ?? 1,
        ]);

        // Create history
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => auth()->id(),
            'status_id' => $ticket->status_id,
            'action'    => 'Ticket dibuat',
        ]);

        // Kirim email ke pengaju
        try {
            Mail::to($ticket->requester->email)
                ->send(new TicketCreated($ticket, 'requester'));
            Log::info("Email ticket terkirim ke pengaju: {$ticket->requester->email}");
        } catch (\Exception $e) {
            Log::error("Gagal kirim email ke pengaju: {$ticket->requester->email}. Error: ".$e->getMessage());
        }

        // Kirim email ke semua department terkait kategori
        foreach ($departments as $dep) {
            if ($dep && $dep->email) {
                try {
                    Mail::to($dep->email)
                        ->send(new TicketCreated($ticket, 'department'));
                    Log::info("Email ticket terkirim ke departemen: {$dep->email}");
                } catch (\Exception $e) {
                    Log::error("Gagal kirim email ke departemen: {$dep->email}. Error: ".$e->getMessage());
                }
            }
        }

        return redirect()->back()->with('success', 'Ticket berhasil dibuat dan email dikirim.');
    }


    /**
     * Show single ticket
     */
    public function show(Ticket $ticket)
    {
        $ticket->load(['requester', 'priority', 'status', 'department', 'assignedUser', 'assignedDepartment', 'histories.user', 'comments.user']);
        $users = User::all();
        $departments = Department::all();
        $statuses = Status::all();

        return view('ticket.show', compact('ticket', 'users', 'departments', 'statuses'));
    }
    /**
     * Update ticket
     */
    public function update(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $ticket->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Ticket berhasil diperbarui.',
            'data'    => $ticket
        ]);
    }

    /**
     * Delete ticket
     */
    public function destroy($id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ticket berhasil dihapus.'
        ]);
    }

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

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $request->validate([
            'status_id' => 'required|exists:statuses,id',
        ]);

        // update status ticket
        $ticket->status_id = $request->status_id;
        $ticket->save();

        // simpan ke history
        $ticket->histories()->create([
            'user_id'   => auth()->id(),
            'status_id' => $ticket->status_id,
            'action'    => 'Status diupdate',
        ]);

        // Kirim email ke pengaju tiket untuk update status
        try {
            Mail::to($ticket->requester->email)
                ->send(new TicketCreated($ticket, 'requester', 'status_updated'));
            Log::info("Email update status terkirim ke pengaju: {$ticket->requester->email}");
        } catch (\Exception $e) {
            Log::error("Gagal kirim email update status ke pengaju: {$ticket->requester->email}. Error: ".$e->getMessage());
        }

        return redirect()->back()->with('success', 'Status tiket berhasil diupdate dan email dikirim.');
    }

    public function showAssignForm(Ticket $ticket)
    {
        $users = User::all(); // list teknisi
        $departments = Department::all();

        return view('tickets.assign', compact('ticket', 'users', 'departments'));
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $request->validate([
            'assigned_user_id' => 'nullable|exists:users,id',
            'assigned_department_id' => 'nullable|exists:departments,id',
        ]);

        $ticket->assigned_user_id = $request->assigned_user_id;
        $ticket->assigned_department_id = $request->assigned_department_id;
        $ticket->save();

        // ambil nama user dan department kalau ada
        $assignedUser = $request->assigned_user_id 
            ? User::find($request->assigned_user_id)->name 
            : null;

        $assignedDept = $request->assigned_department_id 
            ? Department::find($request->assigned_department_id)->name 
            : null;

        // catat history
        $action = 'Tiket ditugaskan';
        if ($assignedUser) {
            $action .= ' kepada ' . $assignedUser;
        }
        if ($assignedDept) {
            $action .= ' di departemen ' . $assignedDept;
        }

        $ticket->histories()->create([
            'user_id' => auth()->id(),
            'action'  => $action,
        ]);

        return redirect()->back()->with('success', $action . '.');
    }


    public function comment(Request $request, Ticket $ticket)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        try {
            $ticket->comments()->create([
                'user_id' => auth()->id(),
                'message' => $request->message
            ]);

            return redirect()->back()->with('success', 'Komentar berhasil ditambahkan.');
        } catch (\Exception $e) {
            // Bisa log error juga
            \Log::error('Gagal menambahkan komentar: '.$e->getMessage());

            return redirect()->back()->with('error', 'Terjadi kesalahan saat menambahkan komentar.');
        }
    }

    public function approve(Request $request, Ticket $ticket)
    {
        $request->validate([
            'approval_id' => 'required|exists:approvals,id',
            'status' => 'required|in:approved,rejected',
            'note' => 'nullable|string',
        ]);

        $approval = Approval::find($request->approval_id);
        $approval->update([
            'status' => $request->status,
            'note' => $request->note,
            'decided_at' => now(),
        ]);

        $ticket->histories()->create([
            'user_id' => Auth::id(),
            'action' => ucfirst($request->status) . " approval at level " . $approval->level,
        ]);

        return redirect()->back()->with('success', 'Persetujuan berhasil dicatat.');
    }

}
