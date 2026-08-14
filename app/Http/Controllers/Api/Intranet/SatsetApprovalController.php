<?php

namespace App\Http\Controllers\Api\Intranet;

use App\Http\Controllers\Controller;
use App\Models\Master\Approval;
use App\Models\Master\ApprovalAudit;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\SatsetApprovalDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SatsetApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'approver_email' => 'required|email|max:255',
            'status' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $status = strtolower((string) $request->query('status', 'pending'));
        $approverEmail = $validated['approver_email'];

        $approvals = Approval::query()
            ->with(['request.requester', 'request.status', 'approver', 'audits'])
            ->whereHas('request', fn ($query) => $query->whereIn('payload->request_type', ['atk_rtk', 'consumption']))
            ->when($status !== 'all', fn ($query) => $query->whereRaw('LOWER(status) = ?', [$status]))
            ->whereHas('approver', fn ($approver) => $approver->where('email', $approverEmail))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $approvals->through(fn (Approval $approval) => $this->serializeApproval($approval)),
        ]);
    }

    public function show(Request $request, Approval $approval): JsonResponse
    {
        $validated = $request->validate([
            'approver_email' => 'required|email|max:255',
        ]);

        $approval->load(['request.requester', 'request.status', 'approver', 'audits']);
        $this->ensureApproverEmail($approval, $validated['approver_email']);

        return response()->json([
            'success' => true,
            'data' => $this->serializeApproval($approval),
        ]);
    }

    public function decide(Request $request, Approval $approval, SatsetApprovalDecisionService $decisions): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:1000',
            'portal_reference_id' => 'nullable|string|max:120',
            'approver' => 'required|array',
            'approver.id' => 'nullable',
            'approver.email' => 'required|email|max:255',
            'approver.name' => 'nullable|string|max:255',
        ]);

        $approval->load(['request', 'approver']);
        $ticket = $approval->request;
        if (! $ticket instanceof Ticket) {
            abort(404, 'Ticket Satset untuk approval ini tidak ditemukan.');
        }

        $actor = $this->authorizedActor($approval, $validated['approver']);

        $updated = $decisions->decide(
            $ticket,
            $approval,
            $actor,
            $validated['status'],
            $validated['comment'] ?? null,
            'portal_intranet',
            $validated['portal_reference_id'] ?? null,
        );

        $freshApproval = Approval::with(['request.requester', 'request.status', 'approver', 'audits'])->findOrFail($approval->id);

        return response()->json([
            'success' => true,
            'message' => 'Approval Satset berhasil diproses dari Portal Intranet.',
            'data' => $this->serializeApproval($freshApproval),
            'ticket' => [
                'id' => $updated->id,
                'ticket_no' => $updated->ticket_no,
                'workflow_status' => data_get($updated->payload, 'workflow_status'),
            ],
        ]);
    }

    private function authorizedActor(Approval $approval, array $actor): User
    {
        $email = trim((string) ($actor['email'] ?? ''));
        $name = trim((string) ($actor['name'] ?? '')) ?: $email;
        $externalId = $actor['id'] ?? null;
        $approver = $approval->approver;

        $matchesEmail = $approver?->email && strcasecmp($approver->email, $email) === 0;
        $matchesId = $externalId !== null && (string) $approver?->id === (string) $externalId;

        if (! $matchesEmail && ! $matchesId) {
            abort(403, 'User Portal tidak berwenang memproses approval Satset ini.');
        }

        $approver->fill([
            'name' => $name,
            'email' => $email,
        ])->save();

        return $approver;
    }

    private function ensureApproverEmail(Approval $approval, string $email): void
    {
        if (! $approval->approver?->email || strcasecmp($approval->approver->email, $email) !== 0) {
            abort(403, 'User Portal tidak berwenang melihat approval Satset ini.');
        }
    }

    private function serializeApproval(Approval $approval): array
    {
        $ticket = $approval->request;

        return [
            'module' => 'satset',
            'satset_reference_id' => (string) $approval->id,
            'approval_id' => $approval->id,
            'level' => $approval->level,
            'status' => $approval->status,
            'comment' => $approval->notes,
            'decided_at' => optional($approval->decided_at)->toIso8601String(),
            'last_action_source' => $approval->last_action_source,
            'portal_reference_id' => $approval->portal_reference_id,
            'request_type' => data_get($ticket?->payload, 'request_type'),
            'amount' => $ticket ? $this->amount($ticket) : 0,
            'ticket' => $ticket ? [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'workflow_status' => data_get($ticket->payload, 'workflow_status'),
                'created_at' => optional($ticket->created_at)->toIso8601String(),
                'requester' => $ticket->requester ? [
                    'id' => $ticket->requester->id,
                    'name' => $ticket->requester->name,
                    'email' => $ticket->requester->email,
                ] : null,
            ] : null,
            'approver' => $approval->approver ? [
                'id' => $approval->approver->id,
                'name' => $approval->approver->name,
                'email' => $approval->approver->email,
            ] : null,
            'audit_trail' => $approval->audits
                ->sortByDesc('acted_at')
                ->map(fn (ApprovalAudit $audit) => [
                    'id' => $audit->id,
                    'source' => $audit->source,
                    'status' => $audit->status,
                    'comment' => $audit->comment,
                    'satset_reference_id' => $audit->satset_reference_id,
                    'external_reference_id' => $audit->external_reference_id,
                    'approver' => [
                        'id' => $audit->approver_id,
                        'name' => $audit->approver_name,
                        'email' => $audit->approver_email,
                    ],
                    'acted_at' => optional($audit->acted_at)->toIso8601String(),
                ])
                ->values(),
        ];
    }

    private function amount(Ticket $ticket): float
    {
        foreach (['total_estimated_amount', 'estimated_amount', 'estimated_cost', 'actual_cost', 'amount'] as $key) {
            $value = data_get($ticket->payload, $key);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return 0.0;
    }
}
