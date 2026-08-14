<?php

namespace App\Services;

use App\Mail\TicketCreated;
use App\Models\Master\Approval;
use App\Models\Master\Attachment;
use App\Models\Master\Comment;
use App\Models\Master\ConsumableItem;
use App\Models\Master\Department;
use App\Models\Master\DepartmentCategory;
use App\Models\Master\Impact;
use App\Models\Master\Priority;
use App\Models\Master\ProblemCategory;
use App\Models\Master\Status;
use App\Models\Master\Ticket;
use App\Models\Master\TicketCategory;
use App\Models\Master\TicketHistory;
use App\Models\Master\Urgency;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MobileSatsetTicketService
{
    public function __construct(
        private readonly LrtjSpaceMobileNotificationService $notifications,
        private readonly LrtjSpaceApprovalResolverService $approvalResolver,
        private readonly SatsetApprovalDecisionService $approvalDecisions,
    ) {}

    public function bootstrap(User $user): array
    {
        return [
            'request_types' => [
                ['code' => 'general', 'name' => 'Request Umum'],
                ['code' => 'consumption', 'name' => 'Permintaan Konsumsi'],
                ['code' => 'atk_rtk', 'name' => 'Permintaan ATK/RTK'],
                ['code' => 'ga_request_finding', 'name' => 'GA Permintaan & Temuan'],
            ],
            'problem_categories' => ProblemCategory::orderBy('name')->get(['id', 'name', 'code', 'parent_id']),
            'ticket_categories' => TicketCategory::orderBy('name')->get(['id', 'name', 'code']),
            'priorities' => Priority::orderBy('id')->get(['id', 'name', 'code']),
            'impacts' => Impact::orderBy('id')->get(['id', 'name', 'code']),
            'urgencies' => Urgency::orderBy('id')->get(['id', 'name', 'code']),
            'statuses' => Status::orderBy('id')->get(['id', 'name', 'code']),
            'consumable_items' => ConsumableItem::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'category', 'large_uom', 'small_uom', 'conversion_qty', 'unit_price', 'small_stock', 'current_stock', 'location']),
            'supervisors' => User::where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'defaults' => [
                'priority_id' => $this->getDefaultPriorityId(),
                'impact_id' => $this->getDefaultImpactId(),
                'urgency_id' => $this->getDefaultUrgencyId(),
                'ticket_category_id' => $this->getDefaultTicketCategoryId(),
                'bum_ticket_category_id' => $this->getBumTicketCategoryId(),
            ],
        ];
    }

    public function create(User $user, string $requestType, array $validated, Request $request): Ticket
    {
        return DB::transaction(function () use ($user, $requestType, $validated, $request) {
            $validated['payload'] = $validated['payload'] ?? [];

            if ($requestType === 'consumption') {
                $validated['category_id'] = $this->getConsumptionCategoryId();
                $validated['ticket_category_id'] = $this->getBumTicketCategoryId();
            } elseif ($requestType === 'atk_rtk') {
                $validated['category_id'] = $this->getAtkRtkCategoryId();
                $validated['ticket_category_id'] = $this->getBumTicketCategoryId();
                $validated['payload'] = $this->enrichAtkRtkPayload($validated['payload']);
            } elseif ($requestType === 'ga_request_finding') {
                $validated['category_id'] = $this->getGaRequestFindingCategoryId();
                $validated['ticket_category_id'] = $this->getBumTicketCategoryId();
            }

            $payload = $this->buildPayload($requestType, array_merge($validated['payload'], [
                'submitted_from' => 'lrtj_space_mobile',
                'reporter_name' => $user->name,
                'reporter_email' => $user->email,
            ]));

            if (in_array($requestType, ['consumption', 'atk_rtk'], true) && ! empty($payload['supervisor_id'])) {
                $payload['supervisor_name'] = User::whereKey($payload['supervisor_id'])->value('name');
            }

            $assignedDepartment = $this->assignedDepartment((int) $validated['category_id'], $requestType);
            $ticket = Ticket::create([
                'ticket_no' => $this->generateTicketNo((int) $validated['category_id']),
                'requester_id' => $user->id,
                'department_id' => $assignedDepartment->id ?? null,
                'assigned_department_id' => $assignedDepartment->id ?? null,
                'category_id' => $validated['category_id'],
                'title' => $this->resolveTitle($requestType, $validated, $payload),
                'description' => $this->resolveDescription($requestType, $validated, $payload),
                'priority_id' => $validated['priority_id'] ?? $this->getDefaultPriorityId(),
                'impact_id' => $validated['impact_id'] ?? $this->getDefaultImpactId(),
                'urgency_id' => $validated['urgency_id'] ?? $this->getDefaultUrgencyId(),
                'ticket_category_id' => $validated['ticket_category_id'] ?? $this->getDefaultTicketCategoryId(),
                'payload' => $payload,
                'status_id' => Status::where('name', 'Open')->value('id') ?? 1,
            ]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'status_id' => $ticket->status_id,
                'action' => 'Ticket dibuat dari LRTJ Space Mobile ('.$this->getRequestTypeLabel($requestType).')',
            ]);

            if (data_get($payload, 'workflow_status') === 'WAITING_MANAGER_APPROVAL') {
                $this->createManagerApproval($ticket);
            }

            $this->storeRequestFiles($request, $ticket, $user, $requestType);
            $this->sendTicketEmails($ticket, (int) $validated['category_id']);

            return $ticket->fresh(['requester', 'category', 'priority', 'status', 'impact', 'urgency', 'department', 'attachments']);
        });
    }

    public function addComment(User $user, Ticket $ticket, string $message): Comment
    {
        $comment = $ticket->comments()->create([
            'user_id' => $user->id,
            'message' => $message,
        ]);

        $ticket->histories()->create([
            'user_id' => $user->id,
            'status_id' => $ticket->status_id,
            'action' => 'Komentar ditambahkan',
        ]);

        return $comment;
    }

    public function approve(User $user, Ticket $ticket, Approval $approval, string $status, ?string $note): Ticket
    {
        if ((int) $ticket->requester_id === (int) $user->id) {
            abort(403, 'User tidak boleh approve request miliknya sendiri.');
        }

        if ((int) $approval->request_id !== (int) $ticket->id) {
            abort(404, 'Approval tidak ditemukan untuk ticket ini.');
        }

        if ((int) $approval->approver_id !== (int) $user->id && ! in_array($user->role ?? null, ['admin', 'manager'], true)) {
            abort(403, 'User bukan approver ticket ini.');
        }

        return $this->approvalDecisions->decide($ticket, $approval, $user, $status, $note, 'satset_mobile');
    }

    public function storeAttachment(User $user, Ticket $ticket, UploadedFile $file, string $attachmentType): Attachment
    {
        $path = $file->store('request-attachments/'.$ticket->id, 'public');

        return Attachment::create([
            'request_id' => $ticket->id,
            'uploaded_by' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'attachment_type' => $attachmentType,
            'uploaded_at' => now(),
        ]);
    }

    public function canAccess(User $user, Ticket $ticket): bool
    {
        if ((int) $ticket->requester_id === (int) $user->id || (int) $ticket->assigned_user_id === (int) $user->id) {
            return true;
        }

        return Approval::where('request_id', $ticket->id)
            ->where('approver_id', $user->id)
            ->exists();
    }

    public function serializeTicket(Ticket $ticket): array
    {
        $ticket->loadMissing([
            'requester',
            'category',
            'priority',
            'status',
            'impact',
            'urgency',
            'department',
            'assignedUser',
            'assignedDepartment',
            'attachments',
            'approvals.approver',
            'comments.user',
            'histories.user',
            'histories.status',
        ]);

        return [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'request_type' => data_get($ticket->payload, 'request_type', 'general'),
            'title' => $ticket->title,
            'description' => $ticket->description,
            'payload' => $ticket->payload ?? [],
            'created_at' => optional($ticket->created_at)->toIso8601String(),
            'requester' => $this->userSummary($ticket->requester),
            'status' => $this->modelSummary($ticket->status),
            'priority' => $this->modelSummary($ticket->priority),
            'category' => $this->modelSummary($ticket->category),
            'department' => $this->modelSummary($ticket->department),
            'impact' => $this->modelSummary($ticket->impact),
            'urgency' => $this->modelSummary($ticket->urgency),
            'assigned_user' => $this->userSummary($ticket->assignedUser),
            'assigned_department' => $this->modelSummary($ticket->assignedDepartment),
            'attachments' => $ticket->attachments->map(fn (Attachment $attachment) => [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'attachment_type' => $attachment->attachment_type,
                'uploaded_at' => optional($attachment->uploaded_at)->toIso8601String(),
                'url' => Storage::disk('public')->url($attachment->file_path),
            ])->values(),
            'comments' => $ticket->comments
                ->sortBy('created_at')
                ->map(fn (Comment $comment) => [
                    'id' => $comment->id,
                    'message' => $comment->message,
                    'created_at' => optional($comment->created_at)->toIso8601String(),
                    'user' => $this->userSummary($comment->user),
                ])
                ->values(),
            'history' => $ticket->histories
                ->sortByDesc('created_at')
                ->map(fn (TicketHistory $history) => [
                    'id' => $history->id,
                    'action' => $history->action,
                    'created_at' => optional($history->created_at)->toIso8601String(),
                    'user' => $history->user ? [
                        'id' => $history->user->id,
                        'name' => $history->user->name,
                        'email' => $history->user->email,
                    ] : null,
                    'status' => $history->status ? [
                        'id' => $history->status->id,
                        'name' => $history->status->name,
                        'code' => $history->status->code,
                    ] : null,
                ])
                ->values(),
            'approvals' => $ticket->approvals->map(fn (Approval $approval) => [
                'id' => $approval->id,
                'level' => $approval->level,
                'status' => $approval->status,
                'notes' => $approval->notes,
                'decided_at' => optional($approval->decided_at)->toIso8601String(),
                'approver' => $this->userSummary($approval->approver),
            ])->values(),
        ];
    }

    private function storeRequestFiles(Request $request, Ticket $ticket, User $user, string $requestType): void
    {
        $map = match ($requestType) {
            'consumption' => [
                'attendance_file' => 'attendance',
                'documentation_file' => 'documentation',
                'activity_report_file' => 'activity_report',
                'training_material_file' => 'training_material',
            ],
            'ga_request_finding' => ['evidence_file' => 'ga_report_evidence'],
            default => [],
        };

        foreach ($map as $field => $type) {
            if ($request->hasFile($field)) {
                $this->storeAttachment($user, $ticket, $request->file($field), $type);
            }
        }
    }

    private function assignedDepartment(int $categoryId, string $requestType): ?Department
    {
        $department = DepartmentCategory::with('department')
            ->where('category_id', $categoryId)
            ->get()
            ->pluck('department')
            ->filter()
            ->first();

        if (! $department && in_array($requestType, ['consumption', 'atk_rtk', 'ga_request_finding'], true)) {
            return $this->findDepartmentByKeywords(['umum', 'general affairs', 'ga', 'procurement']);
        }

        return $department;
    }

    private function sendTicketEmails(Ticket $ticket, int $categoryId): void
    {
        try {
            Mail::to($ticket->requester->email)->send(new TicketCreated($ticket, 'requester'));
        } catch (\Exception $exception) {
            Log::error('Gagal kirim email ticket mobile ke pengaju: '.$exception->getMessage());
        }

        DepartmentCategory::with('department')
            ->where('category_id', $categoryId)
            ->get()
            ->pluck('department')
            ->filter()
            ->each(function (Department $department) use ($ticket) {
                if (! $department->email) {
                    return;
                }
                try {
                    Mail::to($department->email)->send(new TicketCreated($ticket, 'department'));
                } catch (\Exception $exception) {
                    Log::error('Gagal kirim email ticket mobile ke departemen: '.$exception->getMessage());
                }
            });
    }

    private function getDefaultPriorityId(): ?int
    {
        return Priority::where('name', 'Medium')->value('id') ?? Priority::value('id');
    }

    private function getDefaultImpactId(): ?int
    {
        return Impact::where('name', 'Medium')->value('id') ?? Impact::value('id');
    }

    private function getDefaultUrgencyId(): ?int
    {
        return Urgency::where('name', 'Medium')->value('id') ?? Urgency::value('id');
    }

    private function getDefaultTicketCategoryId(): ?int
    {
        return TicketCategory::where('code', 'SR')->value('id')
            ?? TicketCategory::where('name', 'Service Request')->value('id')
            ?? TicketCategory::value('id');
    }

    private function getBumTicketCategoryId(): int
    {
        return TicketCategory::firstOrCreate(
            ['code' => 'BUM'],
            [
                'name' => 'Layanan BUM',
                'description' => 'Layanan Bagian Umum untuk ATK/RTK, konsumsi rapat, dan barang habis pakai.',
            ]
        )->id;
    }

    private function getConsumptionCategoryId(): int
    {
        $query = ProblemCategory::query();
        foreach (['konsumsi', 'umum', 'general', 'ga', 'support'] as $index => $keyword) {
            $query->{$index === 0 ? 'where' : 'orWhere'}('name', 'like', '%'.$keyword.'%');
        }

        return $query->value('id')
            ?? ProblemCategory::firstOrCreate(
                ['code' => 'KONSUMSI'],
                ['name' => 'Permintaan Konsumsi Rapat', 'description' => 'Kategori request BUM untuk konsumsi rapat dan kegiatan.']
            )->id;
    }

    private function getAtkRtkCategoryId(): int
    {
        return ProblemCategory::where('code', 'ATKRTK')->value('id')
            ?? ProblemCategory::where('name', 'like', '%ATK%')->value('id')
            ?? ProblemCategory::firstOrCreate(
                ['code' => 'ATKRTK'],
                ['name' => 'Permintaan ATK/RTK', 'description' => 'Kategori request BUM untuk alat tulis kantor dan rumah tangga kantor.']
            )->id;
    }

    private function getGaRequestFindingCategoryId(): int
    {
        return ProblemCategory::where('code', 'GAQR')->value('id')
            ?? ProblemCategory::where('name', 'like', '%Permintaan dan Temuan%')->value('id')
            ?? ProblemCategory::firstOrCreate(
                ['code' => 'GAQR'],
                ['name' => 'GA Permintaan dan Temuan', 'description' => 'Kategori laporan QR Code Bagian Umum untuk dukungan permintaan dan temuan.']
            )->id;
    }

    private function getRequestTypeLabel(string $requestType): string
    {
        return match ($requestType) {
            'consumption' => 'Permintaan Konsumsi',
            'atk_rtk' => 'ATK / RTK',
            'ga_request_finding' => 'GA Permintaan & Temuan',
            default => 'General',
        };
    }

    private function getWorkflowSteps(string $requestType): array
    {
        return match ($requestType) {
            'consumption' => ['Karyawan mengajukan form permintaan konsumsi', 'Atasan melakukan approval', 'Bagian Umum melakukan verifikasi', 'Proses selesai'],
            'atk_rtk' => ['Pengajuan kebutuhan ATK/RTK dibuat', 'Permintaan diverifikasi', 'Barang diproses', 'Barang diterima dan request ditutup'],
            'ga_request_finding' => ['Pelapor mengisi laporan', 'Bagian Umum memonitor laporan', 'PIC menindaklanjuti', 'Ticket ditutup'],
            default => ['Request dibuat', 'Request ditindaklanjuti', 'Status diperbarui sampai selesai'],
        };
    }

    private function buildPayload(string $requestType, array $payload): array
    {
        $payload = array_merge($payload, [
            'request_type' => $requestType,
            'request_label' => $this->getRequestTypeLabel($requestType),
            'submitted_at' => now()->toDateTimeString(),
            'workflow' => $this->getWorkflowSteps($requestType),
        ]);

        if ($requestType === 'atk_rtk') {
            $total = array_sum(array_map(fn ($item) => (float) ($item['line_total'] ?? 0), $payload['items'] ?? []));
            $totalQty = array_sum(array_map(fn ($item) => (int) ($item['quantity'] ?? 0), $payload['items'] ?? []));
            $threshold = config('bum.atk_rtk_manager_approval_threshold', 100000);
            $payload['total_estimated_amount'] = $total;
            $payload['total_quantity'] = $totalQty;
            $payload['approval_threshold'] = $threshold;
            $payload['workflow_status'] = $total >= $threshold ? 'WAITING_MANAGER_APPROVAL' : 'WAITING_BUM_REVIEW';
        } elseif ($requestType === 'consumption') {
            $payload['workflow_status'] = 'WAITING_MANAGER_APPROVAL';
        } elseif ($requestType === 'ga_request_finding') {
            $payload['workflow_status'] = 'WAITING_BUM_REVIEW';
        } else {
            $payload['workflow_status'] = 'SUBMITTED';
        }

        return $payload;
    }

    private function resolveTitle(string $requestType, array $validated, array $payload): string
    {
        return match ($requestType) {
            'consumption' => 'Permintaan Konsumsi - '.($payload['activity_name'] ?? 'Kegiatan'),
            'atk_rtk' => 'Permintaan ATK/RTK - '.($payload['request_subject'] ?? 'Kebutuhan Operasional'),
            'ga_request_finding' => 'GA '.($payload['report_type'] ?? 'Laporan').' - '.($payload['location'] ?? 'Lokasi'),
            default => $validated['title'],
        };
    }

    private function resolveDescription(string $requestType, array $validated, array $payload): ?string
    {
        if ($requestType === 'consumption') {
            return sprintf('Permintaan konsumsi untuk %s pada %s di %s. Kebutuhan: %s.', $payload['activity_name'] ?? 'kegiatan', isset($payload['event_date']) ? Carbon::parse($payload['event_date'])->format('d M Y') : '-', $payload['location'] ?? '-', $payload['consumption_notes'] ?? ($payload['consumption_type'] ?? '-'));
        }
        if ($requestType === 'atk_rtk') {
            $itemSummary = collect($payload['items'] ?? [])->map(fn ($item) => ($item['item_name'] ?? 'Barang').' x '.($item['quantity'] ?? 0))->implode(', ');

            return sprintf('Permintaan %s untuk %s. Total qty: %s. Estimasi: Rp%s.', $payload['item_type'] ?? 'barang', $payload['delivery_location'] ?? '-', $payload['total_quantity'] ?? '-', number_format((float) ($payload['total_estimated_amount'] ?? 0), 0, ',', '.')).($itemSummary ? ' Item: '.$itemSummary.'.' : '');
        }
        if ($requestType === 'ga_request_finding') {
            return sprintf('%s Bagian Umum di %s. Detail: %s. Ekspektasi tindak lanjut: %s.', $payload['report_type'] ?? 'Laporan', $payload['location'] ?? '-', $payload['description'] ?? '-', $payload['expected_action'] ?? '-');
        }

        return $validated['description'] ?? null;
    }

    private function enrichAtkRtkPayload(array $payload): array
    {
        $rows = collect($payload['items'] ?? [])->filter(fn ($row) => ! empty($row['item_id']) && (int) ($row['quantity'] ?? 0) > 0)->values();
        $masterItems = ConsumableItem::whereIn('id', $rows->pluck('item_id')->all())->get()->keyBy('id');

        $payload['items'] = $rows->map(function ($row) use ($masterItems) {
            $item = $masterItems->get((int) $row['item_id']);
            if (! $item) {
                return null;
            }
            $quantity = (int) $row['quantity'];
            $smallUnitPrice = (float) $item->unit_price;

            return [
                'item_id' => $item->id,
                'item_code' => $item->code,
                'item_name' => $item->name,
                'large_uom' => $item->large_uom,
                'small_uom' => $item->small_uom,
                'conversion_qty' => max(1, (int) $item->conversion_qty),
                'quantity' => $quantity,
                'unit_price' => $smallUnitPrice,
                'large_unit_price' => $smallUnitPrice * max(1, (int) $item->conversion_qty),
                'line_total' => $quantity * $smallUnitPrice,
                'small_stock_at_request' => (int) $item->small_stock,
                'big_stock_at_request' => (int) $item->current_stock,
            ];
        })->filter()->values()->all();

        $payload['quantity'] = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $payload['items']));
        $payload['item_id'] = data_get($payload, 'items.0.item_id');
        $payload['item_name'] = data_get($payload, 'items.0.item_name');

        return $payload;
    }

    private function findDepartmentByKeywords(array $keywords): ?Department
    {
        $query = Department::query();
        foreach ($keywords as $index => $keyword) {
            $query->{$index === 0 ? 'where' : 'orWhere'}('name', 'like', '%'.$keyword.'%');
        }

        return $query->first();
    }

    private function createManagerApproval(Ticket $ticket): void
    {
        $approver = $this->approvalResolver->resolveFirstApprover($ticket);

        $approval = Approval::firstOrCreate([
            'request_id' => $ticket->id,
            'level' => 1,
        ], [
            'approver_id' => $approver->id,
            'status' => 'Pending',
        ]);

        if ($approval->wasRecentlyCreated) {
            $this->notifications->notifyApprovalRequested($ticket, $approval);
        }
    }

    private function generateTicketNo(int $categoryId): string
    {
        $category = ProblemCategory::find($categoryId);
        $categoryCode = $category ? strtoupper($category->code) : 'GEN';
        $datePart = Carbon::now()->format('mdy');
        $lastTicket = Ticket::whereDate('created_at', Carbon::today())->where('category_id', $categoryId)->latest()->first();
        $runningNumber = $lastTicket ? str_pad(((int) substr($lastTicket->ticket_no, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';

        return "TCK-{$categoryCode}-{$datePart}-{$runningNumber}";
    }

    private function userSummary(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null;
    }

    private function modelSummary(mixed $model): ?array
    {
        return $model ? ['id' => $model->id, 'name' => $model->name, 'code' => $model->code ?? null] : null;
    }
}
