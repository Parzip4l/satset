<?php

namespace App\Http\Controllers\Api\Mobile\Satset;

use App\Http\Controllers\Controller;
use App\Models\Master\Approval;
use App\Models\Master\Ticket;
use App\Services\MobileSatsetTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSatsetTicketController extends Controller
{
    public function __construct(private readonly MobileSatsetTicketService $tickets) {}

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'phone' => $user->phone,
            ],
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->tickets->bootstrap($request->user())]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Ticket::with(['requester', 'category', 'priority', 'status', 'impact', 'urgency', 'department'])
            ->where(function ($builder) use ($user) {
                $builder->where('requester_id', $user->id)
                    ->orWhere('assigned_user_id', $user->id)
                    ->orWhereHas('approvals', fn ($approval) => $approval->where('approver_id', $user->id));
            })
            ->when($request->query('request_type'), fn ($builder, $type) => $builder->where('payload->request_type', $type))
            ->when($request->query('status_id'), fn ($builder, $statusId) => $builder->where('status_id', $statusId))
            ->when($request->query('search'), function ($builder, $search) {
                $builder->where(fn ($q) => $q->where('ticket_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate((int) $request->query('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $query->through(fn (Ticket $ticket) => $this->tickets->serializeTicket($ticket)),
        ]);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);

        return response()->json(['success' => true, 'data' => $this->tickets->serializeTicket($ticket)]);
    }

    public function history(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);
        $histories = $ticket->histories()->with(['user', 'status'])->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $histories->map(fn ($history) => [
                'id' => $history->id,
                'action' => $history->action,
                'user' => $history->user ? ['id' => $history->user->id, 'name' => $history->user->name, 'email' => $history->user->email] : null,
                'status' => $history->status ? ['id' => $history->status->id, 'name' => $history->status->name, 'code' => $history->status->code] : null,
                'created_at' => optional($history->created_at)->toIso8601String(),
            ]),
        ]);
    }

    public function storeGeneral(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:problem_categories,id',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'priority_id' => 'nullable|exists:priorities,id',
            'impact_id' => 'nullable|exists:impacts,id',
            'urgency_id' => 'nullable|exists:urgencies,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'payload' => 'nullable|array',
        ]);

        return $this->created($request, 'general', $validated);
    }

    public function storeConsumption(Request $request): JsonResponse
    {
        $this->normalizePayload($request, ['activity_name', 'event_type', 'event_date', 'start_time', 'end_time', 'location', 'participant_count', 'consumption_type', 'request_reason', 'supervisor_id', 'organizer_unit', 'pic_contact', 'consumption_notes']);
        $validated = $request->validate([
            'priority_id' => 'nullable|exists:priorities,id',
            'impact_id' => 'nullable|exists:impacts,id',
            'urgency_id' => 'nullable|exists:urgencies,id',
            'payload' => 'required|array',
            'payload.activity_name' => 'required|string|max:150',
            'payload.event_type' => 'required|string|max:100',
            'payload.event_date' => 'required|date',
            'payload.start_time' => 'required|date_format:H:i',
            'payload.end_time' => 'required|date_format:H:i',
            'payload.location' => 'required|string|max:150',
            'payload.participant_count' => 'required|integer|min:1',
            'payload.consumption_type' => 'required|string|max:100',
            'payload.request_reason' => 'required|string',
            'payload.supervisor_id' => 'nullable|exists:users,id',
            'payload.organizer_unit' => 'nullable|string|max:150',
            'payload.pic_contact' => 'nullable|string|max:150',
            'payload.consumption_notes' => 'nullable|string',
            'attendance_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
            'documentation_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,zip,rar|max:5120',
            'activity_report_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'training_material_file' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,xls,xlsx|max:5120',
        ]);

        return $this->created($request, 'consumption', $validated);
    }

    public function storeAtkRtk(Request $request): JsonResponse
    {
        $this->normalizePayload($request, ['request_subject', 'item_type', 'items', 'supervisor_id', 'needed_date', 'delivery_location', 'recipient_pic']);
        $validated = $request->validate([
            'priority_id' => 'nullable|exists:priorities,id',
            'impact_id' => 'nullable|exists:impacts,id',
            'urgency_id' => 'nullable|exists:urgencies,id',
            'payload' => 'required|array',
            'payload.request_subject' => 'required|string|max:150',
            'payload.item_type' => 'required|string|max:100',
            'payload.items' => 'required|array|min:1',
            'payload.items.*.item_id' => 'required|exists:consumable_items,id',
            'payload.items.*.quantity' => 'required|integer|min:1',
            'payload.supervisor_id' => 'nullable|exists:users,id',
            'payload.needed_date' => 'required|date',
            'payload.delivery_location' => 'required|string|max:150',
            'payload.recipient_pic' => 'nullable|string|max:150',
        ]);

        return $this->created($request, 'atk_rtk', $validated);
    }

    public function storeGaRequestFinding(Request $request): JsonResponse
    {
        $this->normalizePayload($request, ['report_type', 'location', 'detail_location', 'description', 'expected_action', 'reporter_phone']);
        $validated = $request->validate([
            'priority_id' => 'nullable|exists:priorities,id',
            'impact_id' => 'nullable|exists:impacts,id',
            'urgency_id' => 'nullable|exists:urgencies,id',
            'payload' => 'required|array',
            'payload.report_type' => 'required|in:Permintaan,Temuan',
            'payload.location' => 'required|string|max:150',
            'payload.detail_location' => 'nullable|string|max:150',
            'payload.description' => 'required|string',
            'payload.expected_action' => 'nullable|string|max:255',
            'payload.reporter_phone' => 'nullable|string|max:30',
            'evidence_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        return $this->created($request, 'ga_request_finding', $validated);
    }

    public function comment(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);
        $validated = $request->validate(['message' => 'required|string|max:2000']);
        $comment = $this->tickets->addComment($request->user(), $ticket, $validated['message']);

        return response()->json(['success' => true, 'data' => $comment], 201);
    }

    public function approve(Request $request, Ticket $ticket, Approval $approval): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:1000',
        ]);

        $updated = $this->tickets->approve($request->user(), $ticket, $approval, $validated['status'], $validated['note'] ?? null);

        return response()->json(['success' => true, 'data' => $this->tickets->serializeTicket($updated)]);
    }

    public function uploadAttachment(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,zip,rar|max:5120',
            'attachment_type' => 'nullable|string|max:80',
        ]);

        $attachment = $this->tickets->storeAttachment($request->user(), $ticket, $validated['file'], $validated['attachment_type'] ?? 'mobile_attachment');

        return response()->json(['success' => true, 'data' => $attachment], 201);
    }

    private function created(Request $request, string $requestType, array $validated): JsonResponse
    {
        $ticket = $this->tickets->create($request->user(), $requestType, $validated, $request);

        return response()->json(['success' => true, 'message' => 'Ticket berhasil dibuat.', 'data' => $this->tickets->serializeTicket($ticket)], 201);
    }

    private function authorizeTicket(Request $request, Ticket $ticket): void
    {
        if (! $this->tickets->canAccess($request->user(), $ticket)) {
            abort(403, 'User tidak memiliki akses ke ticket ini.');
        }
    }

    private function normalizePayload(Request $request, array $keys): void
    {
        $payload = $request->input('payload', []);
        $payload = is_array($payload) ? $payload : [];

        foreach ($keys as $key) {
            if ($request->has($key) && ! array_key_exists($key, $payload)) {
                $payload[$key] = $request->input($key);
            }
        }

        $request->merge(['payload' => $payload]);
    }
}
