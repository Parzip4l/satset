<?php

namespace App\Http\Controllers;

use App\Models\Master\ConsumableItem;
use App\Models\Master\Impact;
use App\Models\Master\Priority;
use App\Models\Master\ProblemCategory;
use App\Models\Master\Status;
use App\Models\Master\Ticket;
use App\Models\Master\TicketCategory;
use App\Models\Master\Urgency;
use App\Models\User;
use App\Services\ConsumableStockService;
use App\Services\LrtjSpaceApprovalResolverService;
use App\Services\SatsetInitialApprovalService;
use App\Support\ProcurementAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProcurementController extends Controller
{
    private function profile(User $user): array
    {
        $department = trim((string) $user->getAttribute('department'));
        if ($department === '') {
            $probe = new Ticket(['payload' => ['request_type' => ProcurementAccess::TYPE]]);
            $probe->setRelation('requester', $user);
            $portal = app(LrtjSpaceApprovalResolverService::class)->resolveRequester($probe);
            $department = trim((string) (data_get($portal, 'organization.department.name')
                ?: data_get($portal, 'department.name')
                ?: (is_string($portal['department'] ?? null) ? $portal['department'] : '')));
        }
        if ($department === '') {
            throw ValidationException::withMessages(['department' => 'Departemen akun belum tersedia. Lengkapi data organisasi pemohon di Portal.']);
        }

        return ['requester_name' => $user->name, 'requester_department' => $department];
    }

    public function create(Request $request)
    {
        $profile = ['requester_name' => $request->user()->name, 'requester_department' => ''];
        $profileError = null;
        try {
            $profile = $this->profile($request->user());
        } catch (ValidationException $exception) {
            $profileError = collect($exception->errors())->flatten()->first();
        }

        return view('procurement.create', compact('profile', 'profileError'));
    }

    public function index(Request $request)
    {
        abort_unless(ProcurementAccess::allowed($request->user()), 403);
        $tickets = Ticket::with(['requester', 'status'])->where('payload->request_type', ProcurementAccess::TYPE)
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $query->where('ticket_no', 'like', '%'.$request->string('search').'%')
                    ->orWhere('title', 'like', '%'.$request->string('search').'%');
            }))->latest()->paginate(15)->withQueryString();

        return view('procurement.index', compact('tickets'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'submission_token' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*' => 'required|array:need,description,location,quantity,estimated_budget',
            'items.*.need' => 'required|string|max:150',
            'items.*.description' => 'required|string|max:2000',
            'items.*.location' => 'required|string|max:150',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'items.*.estimated_budget' => 'required|numeric|min:0|max:999999999999.99|decimal:0,2',
        ]);
        $existing = Ticket::where('submission_token', $data['submission_token'])->first();
        if ($existing) {
            abort_unless(ProcurementAccess::is($existing) && (int) $existing->requester_id === (int) $request->user()->id, 403);

            return redirect()->route('ticket.show', $existing)->with('success', 'Pengajuan sudah tercatat: '.$existing->ticket_no);
        }
        $profile = $this->profile($request->user());
        try {
            $ticket = DB::transaction(function () use ($data, $profile, $request) {
                $category = ProblemCategory::firstOrCreate(['code' => 'PGB'], ['name' => 'Pengajuan Pengadaan Barang']);
                $ticketCategory = TicketCategory::firstOrCreate(['code' => 'PROC'], ['name' => 'Layanan Procurement']);
                $ticket = Ticket::create([
                    'ticket_no' => 'PGB-'.now()->format('Ymd').'-'.strtoupper((string) Str::ulid()),
                    'submission_token' => $data['submission_token'],
                    'requester_id' => $request->user()->id,
                    'category_id' => $category->id,
                    'ticket_category_id' => $ticketCategory->id,
                    'title' => Str::limit('Pengadaan Barang - '.$data['items'][array_key_first($data['items'])]['need'], 150, ''),
                    'priority_id' => Priority::where('name', 'Medium')->value('id') ?? Priority::value('id'),
                    'impact_id' => Impact::where('name', 'Medium')->value('id') ?? Impact::value('id'),
                    'urgency_id' => Urgency::where('name', 'Medium')->value('id') ?? Urgency::value('id'),
                    'status_id' => Status::where('name', 'Open')->firstOrFail()->id,
                    'payload' => $profile + [
                        'request_type' => ProcurementAccess::TYPE,
                        'request_label' => 'Pengajuan Pengadaan Barang',
                        'workflow_status' => 'WAITING_MANAGER_APPROVAL',
                        'submitted_at' => now()->toDateTimeString(),
                        'items' => array_values($data['items']),
                        // The budget entered for each row is its total, including all requested units.
                        'total_estimated_amount' => round(array_sum(array_column($data['items'], 'estimated_budget')), 2),
                        'fulfillments' => [],
                    ],
                ]);
                $ticket->histories()->create(['user_id' => $request->user()->id, 'status_id' => $ticket->status_id, 'action' => 'Pengajuan Pengadaan Barang dibuat; menunggu kadep/manager.']);
                app(SatsetInitialApprovalService::class)->start($ticket);

                return $ticket;
            });
        } catch (QueryException $exception) {
            $ticket = Ticket::where('submission_token', $data['submission_token'])->first();
            if (! $ticket || ! ProcurementAccess::is($ticket) || (int) $ticket->requester_id !== (int) $request->user()->id) {
                throw $exception;
            }
        }

        return redirect()->route('ticket.show', $ticket)->with('success', 'Pengajuan dikirim untuk persetujuan kadep/manager.');
    }

    public function fulfill(Request $request, Ticket $ticket, ConsumableStockService $stock)
    {
        abort_unless(ProcurementAccess::is($ticket), 404);
        abort_unless(ProcurementAccess::allowed($request->user()), 403);
        $data = $request->validate([
            'submission_token' => 'required|uuid',
            'line' => 'required|integer|min:0',
            'method' => 'required|in:waiting,stock,funds',
            'quantity' => 'required_unless:method,waiting|nullable|integer|min:1|max:1000000',
            'item_id' => ['required_if:method,stock', 'nullable', Rule::exists('consumable_items', 'id')->where('is_active', true)],
            'amount' => 'required_if:method,funds|nullable|numeric|min:0.01|max:999999999999.99|decimal:0,2',
            'recipient' => 'required_unless:method,waiting|nullable|string|max:150',
            'notes' => 'required|string|max:2000',
            'evidence' => 'required_unless:method,waiting|nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        $path = null;
        try {
            DB::transaction(function () use ($ticket, $data, $request, $stock, &$path) {
                $current = Ticket::lockForUpdate()->findOrFail($ticket->id);
                $payload = $current->payload;
                if (collect($payload['fulfillments'] ?? [])->contains('submission_token', $data['submission_token'])) {
                    return;
                }
                abort_unless(in_array($payload['workflow_status'], ['WAITING_PROCUREMENT_REVIEW', 'WAITING_PROCUREMENT', 'PARTIALLY_FULFILLED'], true), 409, 'Pengajuan belum disetujui atau sudah selesai.');
                abort_unless($current->approvals()->where('level', 1)->whereRaw('LOWER(status) = ?', ['approved'])->exists(), 409, 'Persetujuan kadep/manager wajib tersedia.');
                $line = (int) $data['line'];
                abort_unless(isset($payload['items'][$line]), 422, 'Baris kebutuhan tidak ditemukan.');
                $item = &$payload['items'][$line];
                $qty = $data['method'] === 'waiting' ? 0 : (int) $data['quantity'];
                $remaining = (int) $item['quantity'] - (int) ($item['fulfilled_quantity'] ?? 0);
                if ($remaining <= 0 || $qty > $remaining) {
                    throw ValidationException::withMessages(['quantity' => 'Jumlah melebihi sisa kebutuhan atau baris sudah terpenuhi.']);
                }
                if ($data['method'] === 'stock') {
                    try {
                        $stock->decreaseSmall(ConsumableItem::findOrFail($data['item_id']), $qty, ProcurementAccess::TYPE, $current->id, $data['notes'], $request->user()->id);
                    } catch (\InvalidArgumentException $exception) {
                        throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
                    }
                }
                $attachment = null;
                if ($data['method'] !== 'waiting') {
                    $file = $request->file('evidence');
                    $path = $file->store('procurement/'.$current->id, 'public');
                    throw_if(! $path, \RuntimeException::class, 'Bukti penyerahan gagal disimpan.');
                    $attachment = $current->attachments()->create([
                        'uploaded_by' => $request->user()->id, 'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                        'attachment_type' => 'procurement_handover_evidence', 'uploaded_at' => now(),
                    ]);
                }
                $item['fulfilled_quantity'] = (int) ($item['fulfilled_quantity'] ?? 0) + $qty;
                $item['fulfillment_status'] = $item['fulfilled_quantity'] >= (int) $item['quantity'] ? 'FULFILLED' : ($data['method'] === 'waiting' ? 'WAITING' : 'PARTIAL');
                unset($item);
                $payload['fulfillments'][] = [
                    'submission_token' => $data['submission_token'], 'line' => $line, 'method' => $data['method'],
                    'quantity' => $qty, 'amount' => $data['method'] === 'funds' ? round((float) $data['amount'], 2) : null,
                    'item_id' => $data['method'] === 'stock' ? (int) $data['item_id'] : null,
                    'recipient' => $data['method'] === 'waiting' ? null : $data['recipient'], 'notes' => $data['notes'],
                    'attachment_id' => $attachment?->id, 'actor_name' => $request->user()->name,
                    'actor_id' => $request->user()->id, 'recorded_at' => now()->toDateTimeString(),
                ];
                $complete = collect($payload['items'])->every(fn ($row) => ($row['fulfilled_quantity'] ?? 0) >= $row['quantity']);
                $any = collect($payload['items'])->sum('fulfilled_quantity') > 0;
                $payload['workflow_status'] = $complete ? 'CLOSED' : ($any ? 'PARTIALLY_FULFILLED' : 'WAITING_PROCUREMENT');
                $current->update(['payload' => $payload, 'status_id' => Status::where('name', $complete ? 'Closed' : 'In Progress')->firstOrFail()->id,
                    'resolved_at' => $complete ? now() : null, 'closed_at' => $complete ? now() : null]);
                $current->histories()->create(['user_id' => $request->user()->id, 'status_id' => $current->status_id,
                    'action' => 'Procurement baris '.($line + 1).': '.['stock' => 'penyerahan barang', 'funds' => 'penyerahan dana', 'waiting' => 'menunggu ketersediaan'][$data['method']].'.']);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('ticket.show', $ticket)->with('success', 'Tindak lanjut Procurement berhasil dicatat.');
    }
}
