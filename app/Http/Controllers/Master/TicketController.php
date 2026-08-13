<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Ticket;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Mail\TicketCreated;
use App\Mail\SystemTestEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Illuminate\Support\Str;

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
use App\Models\Master\TicketFormSchema; 
use App\Models\Master\Attachment;
use App\Models\Master\ConsumableItem;
use App\Services\ConsumableStockService;
use App\Services\LrtjSpaceMobileNotificationService;


use App\Models\Master\TicketHistory;

class TicketController extends Controller
{
    /**
     * Display requests landing menu.
     */
    public function index()
    {
        $user = auth()->user();

        $baseQuery = Ticket::query()
            ->when(($user->role ?? null) !== 'admin', fn($query) => $query->where('requester_id', $user->id));

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->whereHas('status', fn($query) => $query->where('name', 'Open'))->count(),
            'in_progress' => (clone $baseQuery)->whereHas('status', fn($query) => $query->where('name', 'In Progress'))->count(),
            'completed' => (clone $baseQuery)->whereHas('status', fn($query) => $query->whereIn('name', ['Resolved', 'Closed']))->count(),
        ];

        return view('ticket.menu', compact('stats'));
    }

    /**
     * Display general ticket listing.
     */
    public function generalIndex(Request $request)
    {
        $user         = auth()->user();
        $search       = $request->get('search');
        $statusId     = $request->get('status_id');
        $priorityId   = $request->get('priority_id');
        $categoryId   = $request->get('category_id');
        $departmentId = $request->get('department_id');

        $tickets = Ticket::with(['requester', 'category', 'priority', 'status', 'impact', 'urgency'])
            ->when(($user->role ?? null) !== 'admin', fn($query) => $query->where('requester_id', $user->id))
            ->when($search, function ($query, $searchValue) {
                $query->where(function ($builder) use ($searchValue) {
                    $builder->where('title', 'like', '%' . $searchValue . '%')
                        ->orWhere('ticket_no', 'like', '%' . $searchValue . '%')
                        ->orWhereHas('requester', function ($subQuery) use ($searchValue) {
                            $subQuery->where('name', 'like', '%' . $searchValue . '%');
                        });
                });
            })
            ->when($statusId, fn($query) => $query->where('status_id', $statusId))
            ->when($priorityId, fn($query) => $query->where('priority_id', $priorityId))
            ->when($categoryId, fn($query) => $query->where('category_id', $categoryId))
            ->when($departmentId, fn($query) => $query->where('department_id', $departmentId))
            ->latest()
            ->paginate(10);

        $users       = User::all();
        $priority    = Priority::all();
        $status      = Status::all();
        $categories  = ProblemCategory::all();
        $departments = Department::all();

        if ($request->ajax()) {
            return view('ticket.index', compact('tickets', 'status', 'priority', 'categories', 'users', 'departments'))->render();
        }

        return view('ticket.index', compact('tickets', 'status', 'priority', 'categories', 'users', 'departments'));
    }

    public function create()
    {
        return view('ticket.create', $this->getTicketFormData());
    }

    public function createConsumption()
    {
        return view('ticket.create-consumption', $this->getTicketFormData([
            'serviceRequestId' => $this->getBumTicketCategoryId(),
            'consumptionCategoryId' => $this->getConsumptionCategoryId(),
            'approvers' => $this->getApproverUsers(),
            'mediumPriorityId' => Priority::where('name', 'Medium')->value('id'),
            'mediumImpactId' => Impact::where('name', 'Medium')->value('id'),
            'mediumUrgencyId' => Urgency::where('name', 'Medium')->value('id'),
        ]));
    }

    public function createPublicRequests()
    {
        return view('ticket.public-requests');
    }

    public function createPublicConsumption()
    {
        return view('ticket.create-consumption', $this->getTicketFormData([
            'isPublic' => true,
            'serviceRequestId' => $this->getBumTicketCategoryId(),
            'consumptionCategoryId' => $this->getConsumptionCategoryId(),
            'mediumPriorityId' => Priority::where('name', 'Medium')->value('id'),
            'mediumImpactId' => Impact::where('name', 'Medium')->value('id'),
            'mediumUrgencyId' => Urgency::where('name', 'Medium')->value('id'),
        ]));
    }

    public function createAtkRtk()
    {
        return view('ticket.create-atk-rtk', $this->getTicketFormData([
            'serviceRequestId' => $this->getBumTicketCategoryId(),
            'atkRtkCategoryId' => $this->getAtkRtkCategoryId(),
            'approvers' => $this->getApproverUsers(),
            'mediumPriorityId' => Priority::where('name', 'Medium')->value('id'),
            'mediumImpactId' => Impact::where('name', 'Medium')->value('id'),
            'mediumUrgencyId' => Urgency::where('name', 'Medium')->value('id'),
            'consumableItems' => ConsumableItem::where('is_active', true)->orderBy('name')->get(),
            'approvalThreshold' => config('bum.atk_rtk_manager_approval_threshold', 100000),
        ]));
    }

    public function createPublicAtkRtk()
    {
        return view('ticket.create-atk-rtk', $this->getTicketFormData([
            'isPublic' => true,
            'serviceRequestId' => $this->getBumTicketCategoryId(),
            'atkRtkCategoryId' => $this->getAtkRtkCategoryId(),
            'mediumPriorityId' => Priority::where('name', 'Medium')->value('id'),
            'mediumImpactId' => Impact::where('name', 'Medium')->value('id'),
            'mediumUrgencyId' => Urgency::where('name', 'Medium')->value('id'),
            'consumableItems' => ConsumableItem::where('is_active', true)->orderBy('name')->get(),
            'approvalThreshold' => config('bum.atk_rtk_manager_approval_threshold', 100000),
        ]));
    }

    public function createGaRequestFinding()
    {
        return view('ticket.create-ga-permintaan-temuan', $this->getTicketFormData([
            'serviceRequestId' => $this->getBumTicketCategoryId(),
            'gaRequestFindingCategoryId' => $this->getGaRequestFindingCategoryId(),
            'mediumPriorityId' => Priority::where('name', 'Medium')->value('id'),
            'mediumImpactId' => Impact::where('name', 'Medium')->value('id'),
            'mediumUrgencyId' => Urgency::where('name', 'Medium')->value('id'),
        ]));
    }

    public function createPublicGaRequestFinding()
    {
        return view('ticket.create-ga-permintaan-temuan', $this->getTicketFormData([
            'isPublic' => true,
            'serviceRequestId' => $this->getBumTicketCategoryId(),
            'gaRequestFindingCategoryId' => $this->getGaRequestFindingCategoryId(),
            'mediumPriorityId' => Priority::where('name', 'Medium')->value('id'),
            'mediumImpactId' => Impact::where('name', 'Medium')->value('id'),
            'mediumUrgencyId' => Urgency::where('name', 'Medium')->value('id'),
        ]));
    }

    public function warehouseAtkRtk()
    {
        $atkTicketsQuery = Ticket::with(['requester', 'status'])
            ->where('payload->request_type', 'atk_rtk');

        $stats = [
            'waiting_review' => (clone $atkTicketsQuery)->where('payload->workflow_status', 'WAITING_BUM_REVIEW')->count(),
            'waiting_procurement' => (clone $atkTicketsQuery)->where('payload->workflow_status', 'WAITING_PROCUREMENT')->count(),
            'ready_to_handover' => (clone $atkTicketsQuery)->where('payload->workflow_status', 'READY_TO_HANDOVER')->count(),
            'handed_over' => (clone $atkTicketsQuery)->where('payload->workflow_status', 'HANDED_OVER')->count(),
        ];

        $activeTickets = (clone $atkTicketsQuery)
            ->whereIn('payload->workflow_status', [
                'WAITING_BUM_REVIEW',
                'STOCK_CHECKED',
                'WAITING_PROCUREMENT',
                'READY_TO_HANDOVER',
            ])
            ->latest()
            ->paginate(10);

        $lowStockItems = ConsumableItem::where('is_active', true)
            ->whereColumn('small_stock', '<=', 'minimum_stock')
            ->orderBy('name')
            ->take(8)
            ->get();

        return view('ticket.warehouse-atk-rtk', compact('stats', 'activeTickets', 'lowStockItems'));
    }

    public function sendTestEmail()
    {
        $user = auth()->user();

        if (!$user || empty($user->email)) {
            return redirect()->back()->with('error', 'Email user login belum tersedia, jadi test email tidak bisa dikirim.');
        }

        try {
            Mail::to($user->email)->send(new SystemTestEmail($user));
            Log::info('Test email berhasil dikirim ke user login: ' . $user->email);

            return redirect()->back()->with('success', 'Test email berhasil dikirim ke ' . $user->email);
        } catch (\Exception $e) {
            Log::error('Gagal mengirim test email ke user login: ' . $user->email . '. Error: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Test email gagal dikirim: ' . $e->getMessage());
        }
    }

    private function flattenCategories($categories, $prefix = '')
    {
        $result = [];

        foreach ($categories as $category) {
            // Masukkan kategori saat ini ke array baru
            $result[] = [
                'id' => $category->id,
                'name' => $prefix . $category->name // Gabungkan prefix (strip) dengan nama
            ];

            // Jika punya anak, panggil fungsi ini lagi (Rekursif)
            // Tambahkan prefix '— ' untuk level anak
            if ($category->children->count() > 0) {
                $result = array_merge(
                    $result, 
                    $this->flattenCategories($category->children, $prefix . '— ')
                );
            }
        }

        return $result;
    }

    private function getTicketFormData(array $extra = [])
    {
        $rootCategories = ProblemCategory::whereNull('parent_id')
            ->with('children')
            ->get();

        return array_merge([
            'status' => Status::all(),
            'priority' => Priority::all(),
            'categories' => $this->flattenCategories($rootCategories),
            'priorities' => Priority::all(),
            'impacts' => Impact::all(),
            'urgencies' => Urgency::all(),
            'statuses' => Status::all(),
            'categoryticket' => TicketCategory::all(),
        ], $extra);
    }

    private function getDefaultPriorityId()
    {
        return Priority::where('name', 'Medium')->value('id') ?? Priority::value('id');
    }

    private function getDefaultImpactId()
    {
        return Impact::where('name', 'Medium')->value('id') ?? Impact::value('id');
    }

    private function getDefaultUrgencyId()
    {
        return Urgency::where('name', 'Medium')->value('id') ?? Urgency::value('id');
    }

    private function getDefaultTicketCategoryId()
    {
        return TicketCategory::where('code', 'SR')->value('id')
            ?? TicketCategory::where('name', 'Service Request')->value('id')
            ?? TicketCategory::value('id');
    }

    private function getBumTicketCategoryId()
    {
        return TicketCategory::firstOrCreate(
            ['code' => 'BUM'],
            [
                'name' => 'Layanan BUM',
                'description' => 'Layanan Bagian Umum untuk ATK/RTK, konsumsi rapat, dan barang habis pakai.',
            ]
        )->id;
    }

    private function getApproverUsers()
    {
        $users = User::where('id', '!=', auth()->id())
            ->whereIn('role', ['approver', 'manager', 'admin'])
            ->orderBy('name')
            ->get();

        if ($users->isNotEmpty()) {
            return $users;
        }

        return User::where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get();
    }

    private function getConsumptionCategoryId()
    {
        $query = ProblemCategory::query();

        foreach (['konsumsi', 'umum', 'general', 'ga', 'support'] as $index => $keyword) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $query->{$method}('name', 'like', '%' . $keyword . '%');
        }

        return $query->value('id')
            ?? ProblemCategory::firstOrCreate(
                ['code' => 'KONSUMSI'],
                [
                    'name' => 'Permintaan Konsumsi Rapat',
                    'description' => 'Kategori request BUM untuk konsumsi rapat dan kegiatan.',
                ]
            )->id;
    }

    private function getAtkRtkCategoryId()
    {
        return ProblemCategory::where('code', 'ATKRTK')->value('id')
            ?? ProblemCategory::where('name', 'like', '%ATK%')->value('id')
            ?? ProblemCategory::firstOrCreate(
                ['code' => 'ATKRTK'],
                [
                    'name' => 'Permintaan ATK/RTK',
                    'description' => 'Kategori request BUM untuk alat tulis kantor dan rumah tangga kantor.',
                ]
            )->id;
    }

    private function getGaRequestFindingCategoryId()
    {
        return ProblemCategory::where('code', 'GAQR')->value('id')
            ?? ProblemCategory::where('name', 'like', '%Permintaan dan Temuan%')->value('id')
            ?? ProblemCategory::firstOrCreate(
                ['code' => 'GAQR'],
                [
                    'name' => 'GA Permintaan dan Temuan',
                    'description' => 'Kategori laporan QR Code Bagian Umum untuk dukungan permintaan dan temuan.',
                ]
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
        if ($requestType === 'consumption') {
            return [
                'Karyawan mengajukan form permintaan konsumsi',
                'Atasan melakukan approval',
                'Bagian Umum melakukan verifikasi',
                'Approval Bagian Umum menentukan lanjut ke vendor atau revisi',
                'Vendor melakukan pemesanan dan pesanan diterima',
                'Karyawan menyerahkan laporan pertanggungjawaban ke Bagian Umum',
                'Proses selesai',
            ];
        }

        if ($requestType === 'atk_rtk') {
            return [
                'Pengajuan kebutuhan ATK/RTK dibuat oleh pemohon',
                'Permintaan diverifikasi oleh unit terkait',
                'Pengadaan atau distribusi barang diproses',
                'Barang diterima dan request ditutup',
            ];
        }

        if ($requestType === 'ga_request_finding') {
            return [
                'Pelapor scan QR dan mengisi laporan permintaan atau temuan',
                'Resepsionis atau admin Bagian Umum memonitor laporan masuk',
                'PIC Bagian Umum memberikan arahan tindak lanjut',
                'Petugas terkait menyelesaikan permintaan atau temuan',
                'Bukti penyelesaian dilaporkan dan diverifikasi PIC Bagian Umum',
                'Admin memperbarui rekap dan ticket ditutup',
            ];
        }

        return [
            'Request umum dibuat oleh pemohon',
            'Request ditindaklanjuti oleh unit terkait',
            'Status diperbarui sampai selesai',
        ];
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
            if ($total <= 0) {
                $total = ((int) ($payload['quantity'] ?? 0)) * ((float) ($payload['unit_price'] ?? 0));
                $totalQty = (int) ($payload['quantity'] ?? 0);
            }
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
        if ($requestType === 'consumption') {
            return 'Permintaan Konsumsi - ' . ($payload['activity_name'] ?? 'Kegiatan');
        }

        if ($requestType === 'atk_rtk') {
            return 'Permintaan ATK/RTK - ' . ($payload['request_subject'] ?? 'Kebutuhan Operasional');
        }

        if ($requestType === 'ga_request_finding') {
            $typeLabel = $payload['report_type'] ?? 'Laporan';
            return 'GA ' . $typeLabel . ' - ' . ($payload['location'] ?? 'Lokasi');
        }

        return $validated['title'];
    }

    private function resolveDescription(string $requestType, array $validated, array $payload): ?string
    {
        if ($requestType === 'consumption') {
            return sprintf(
                'Permintaan konsumsi untuk %s pada %s di %s. Kebutuhan: %s.',
                $payload['activity_name'] ?? 'kegiatan',
                isset($payload['event_date']) ? Carbon::parse($payload['event_date'])->format('d M Y') : '-',
                $payload['location'] ?? '-',
                $payload['consumption_notes'] ?? ($payload['consumption_type'] ?? '-')
            );
        }

        if ($requestType === 'atk_rtk') {
            $itemSummary = collect($payload['items'] ?? [])
                ->map(fn ($item) => ($item['item_name'] ?? 'Barang') . ' x ' . ($item['quantity'] ?? 0))
                ->implode(', ');

            return sprintf(
                'Permintaan %s untuk %s. Total qty: %s. Estimasi: Rp%s.',
                $payload['item_type'] ?? 'barang',
                $payload['delivery_location'] ?? '-',
                $payload['total_quantity'] ?? '-',
                number_format((float) ($payload['total_estimated_amount'] ?? 0), 0, ',', '.')
            ) . ($itemSummary ? ' Item: ' . $itemSummary . '.' : '');
        }

        if ($requestType === 'ga_request_finding') {
            return sprintf(
                '%s Bagian Umum di %s. Detail: %s. Ekspektasi tindak lanjut: %s.',
                $payload['report_type'] ?? 'Laporan',
                $payload['location'] ?? '-',
                $payload['description'] ?? '-',
                $payload['expected_action'] ?? '-'
            );
        }

        return $validated['description'] ?? null;
    }

    private function enrichAtkRtkPayload(array $payload): array
    {
        $rows = collect($payload['items'] ?? [])
            ->filter(fn ($row) => !empty($row['item_id']) && (int) ($row['quantity'] ?? 0) > 0)
            ->values();

        if ($rows->isEmpty() && !empty($payload['item_id'])) {
            $rows = collect([[
                'item_id' => $payload['item_id'],
                'quantity' => $payload['quantity'] ?? 1,
            ]]);
        }

        $masterItems = ConsumableItem::whereIn('id', $rows->pluck('item_id')->all())
            ->get()
            ->keyBy('id');

        $payload['items'] = $rows
            ->map(function ($row) use ($masterItems) {
                $item = $masterItems->get((int) $row['item_id']);
                if (!$item) {
                    return null;
                }

                $quantity = (int) $row['quantity'];
                $smallUnitPrice = (float) $item->unit_price;
                $conversionQty = max(1, (int) $item->conversion_qty);
                $largeUnitPrice = $smallUnitPrice * $conversionQty;

                return [
                    'item_id' => $item->id,
                    'item_code' => $item->code,
                    'item_name' => $item->name,
                    'large_uom' => $item->large_uom,
                    'small_uom' => $item->small_uom,
                    'conversion_qty' => $conversionQty,
                    'quantity' => $quantity,
                    'unit_price' => $smallUnitPrice,
                    'large_unit_price' => $largeUnitPrice,
                    'line_total' => $quantity * $smallUnitPrice,
                    'small_stock_at_request' => (int) $item->small_stock,
                    'big_stock_at_request' => (int) $item->current_stock,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $payload['quantity'] = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $payload['items']));
        $payload['unit_price'] = null;
        $payload['item_id'] = data_get($payload, 'items.0.item_id');
        $payload['item_name'] = data_get($payload, 'items.0.item_name');

        return $payload;
    }

    private function findDepartmentByKeywords(array $keywords): ?Department
    {
        $query = Department::query();

        foreach ($keywords as $index => $keyword) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $query->{$method}('name', 'like', '%' . $keyword . '%');
        }

        return $query->first();
    }

    private function storeRequestAttachment(Request $request, Ticket $ticket, string $fieldName, string $attachmentType, ?User $uploader = null): void
    {
        if (!$request->hasFile($fieldName)) {
            return;
        }

        $file = $request->file($fieldName);
        $path = $file->store('request-attachments/' . $ticket->id, 'public');

        Attachment::create([
            'request_id' => $ticket->id,
            'uploaded_by' => $uploader?->id ?? auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'attachment_type' => $attachmentType,
            'uploaded_at' => now(),
        ]);
    }

    private function findOrCreatePublicReporter(array $validated): User
    {
        $email = Str::lower($validated['reporter_email']);
        $name = $validated['reporter_name'] ?: Str::before($email, '@');
        $phone = data_get($validated, 'payload.reporter_phone') ?: '0';

        $user = User::firstOrNew(['email' => $email]);

        if (!$user->exists) {
            $user->name = $name;
            $user->password = Hash::make(Str::random(32));
            $this->setUserColumnIfExists($user, 'username', Str::before($email, '@'));
            $this->setUserColumnIfExists($user, 'phone', $phone);
            $this->setUserColumnIfExists($user, 'role', 'pelapor');
            $this->setUserColumnIfExists($user, 'user_type', 'public');
            $this->setUserColumnIfExists($user, 'kartu_uang_1', '-');
        } else {
            $user->name = $user->name ?: $name;
            if (Schema::hasColumn('users', 'phone')) {
                $user->phone = $user->phone ?: $phone;
            }
        }

        $user->save();

        return $user;
    }

    private function setUserColumnIfExists(User $user, string $column, mixed $value): void
    {
        if (Schema::hasColumn('users', $column)) {
            $user->{$column} = $value;
        }
    }

    public function storePublicGaRequestFinding(Request $request)
    {
        $request->merge([
            'request_type' => 'ga_request_finding',
            'ticket_category_id' => $this->getBumTicketCategoryId(),
            'category_id' => $this->getGaRequestFindingCategoryId(),
            'priority_id' => $request->input('priority_id') ?: $this->getDefaultPriorityId(),
            'impact_id' => $request->input('impact_id') ?: $this->getDefaultImpactId(),
            'urgency_id' => $request->input('urgency_id') ?: $this->getDefaultUrgencyId(),
        ]);

        $validated = $request->validate([
            'reporter_name' => 'required|string|max:150',
            'reporter_email' => 'required|email|max:255',
            'request_type' => 'required|in:ga_request_finding',
            'category_id' => 'required|exists:problem_categories,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'impact_id' => 'nullable|exists:impacts,id',
            'urgency_id' => 'nullable|exists:urgencies,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'payload' => 'nullable|array',
            'payload.report_type' => 'required|in:Permintaan,Temuan',
            'payload.location' => 'required|string|max:150',
            'payload.detail_location' => 'nullable|string|max:150',
            'payload.description' => 'required|string',
            'payload.expected_action' => 'nullable|string|max:255',
            'payload.reporter_phone' => 'nullable|string|max:30',
            'evidence_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $requester = $this->findOrCreatePublicReporter($validated);
        $payload = $this->buildPayload('ga_request_finding', array_merge($validated['payload'] ?? [], [
            'reporter_name' => $requester->name,
            'reporter_email' => $requester->email,
            'submitted_from' => 'public',
        ]));

        $departments = DepartmentCategory::with('department')
            ->where('category_id', $validated['category_id'])
            ->get()
            ->pluck('department')
            ->filter();

        $assignedDepartment = $departments->first()
            ?: $this->findDepartmentByKeywords(['umum', 'general affairs', 'ga', 'procurement']);

        $ticket = Ticket::create([
            'ticket_no' => $this->generateTicketNo($validated['category_id']),
            'requester_id' => $requester->id,
            'department_id' => $assignedDepartment->id ?? null,
            'assigned_department_id' => $assignedDepartment->id ?? null,
            'category_id' => $validated['category_id'],
            'title' => $this->resolveTitle('ga_request_finding', $validated, $payload),
            'description' => $this->resolveDescription('ga_request_finding', $validated, $payload),
            'priority_id' => $validated['priority_id'] ?? $this->getDefaultPriorityId(),
            'impact_id' => $validated['impact_id'] ?? $this->getDefaultImpactId(),
            'urgency_id' => $validated['urgency_id'] ?? $this->getDefaultUrgencyId(),
            'ticket_category_id' => $validated['ticket_category_id'] ?? $this->getBumTicketCategoryId(),
            'payload' => $payload,
            'status_id' => Status::where('name', 'Open')->value('id') ?? 1,
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'status_id' => $ticket->status_id,
            'action' => 'Ticket dibuat (GA Permintaan & Temuan - Public)',
        ]);

        $this->storeRequestAttachment($request, $ticket, 'evidence_file', 'ga_report_evidence', $requester);

        try {
            Mail::to($ticket->requester->email)
                ->send(new TicketCreated($ticket, 'requester'));
            Log::info("Email ticket public terkirim ke pengaju: {$ticket->requester->email}");
        } catch (\Exception $e) {
            Log::error("Gagal kirim email ticket public ke pengaju: {$ticket->requester->email}. Error: ".$e->getMessage());
        }

        foreach ($departments as $dep) {
            if ($dep && $dep->email) {
                try {
                    Mail::to($dep->email)
                        ->send(new TicketCreated($ticket, 'department'));
                    Log::info("Email ticket public terkirim ke departemen: {$dep->email}");
                } catch (\Exception $e) {
                    Log::error("Gagal kirim email ticket public ke departemen: {$dep->email}. Error: ".$e->getMessage());
                }
            }
        }

        return redirect()->route('public.ticket.ga-permintaan-temuan.create')
            ->with('success', 'Laporan berhasil dikirim. Nomor ticket Anda: ' . $ticket->ticket_no);
    }

    private function createPublicTicketFromPayload(array $validated, array $payload, User $requester, string $requestType): Ticket
    {
        $departments = DepartmentCategory::with('department')
            ->where('category_id', $validated['category_id'])
            ->get()
            ->pluck('department')
            ->filter();

        $assignedDepartment = $departments->first()
            ?: $this->findDepartmentByKeywords(['umum', 'general affairs', 'ga', 'procurement']);

        $ticket = Ticket::create([
            'ticket_no' => $this->generateTicketNo($validated['category_id']),
            'requester_id' => $requester->id,
            'department_id' => $assignedDepartment->id ?? null,
            'assigned_department_id' => $assignedDepartment->id ?? null,
            'category_id' => $validated['category_id'],
            'title' => $this->resolveTitle($requestType, $validated, $payload),
            'description' => $this->resolveDescription($requestType, $validated, $payload),
            'priority_id' => $validated['priority_id'] ?? $this->getDefaultPriorityId(),
            'impact_id' => $validated['impact_id'] ?? $this->getDefaultImpactId(),
            'urgency_id' => $validated['urgency_id'] ?? $this->getDefaultUrgencyId(),
            'ticket_category_id' => $validated['ticket_category_id'] ?? $this->getBumTicketCategoryId(),
            'payload' => $payload,
            'status_id' => Status::where('name', 'Open')->value('id') ?? 1,
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'status_id' => $ticket->status_id,
            'action' => 'Ticket dibuat (' . $this->getRequestTypeLabel($requestType) . ' - Public)',
        ]);

        return $ticket;
    }

    private function sendPublicTicketEmails(Ticket $ticket, int $categoryId): void
    {
        try {
            Mail::to($ticket->requester->email)
                ->send(new TicketCreated($ticket, 'requester'));
            Log::info("Email ticket public terkirim ke pengaju: {$ticket->requester->email}");
        } catch (\Exception $e) {
            Log::error("Gagal kirim email ticket public ke pengaju: {$ticket->requester->email}. Error: ".$e->getMessage());
        }

        $departments = DepartmentCategory::with('department')
            ->where('category_id', $categoryId)
            ->get()
            ->pluck('department')
            ->filter();

        foreach ($departments as $dep) {
            if ($dep && $dep->email) {
                try {
                    Mail::to($dep->email)
                        ->send(new TicketCreated($ticket, 'department'));
                    Log::info("Email ticket public terkirim ke departemen: {$dep->email}");
                } catch (\Exception $e) {
                    Log::error("Gagal kirim email ticket public ke departemen: {$dep->email}. Error: ".$e->getMessage());
                }
            }
        }
    }

    public function storePublicConsumption(Request $request)
    {
        $request->merge([
            'request_type' => 'consumption',
            'ticket_category_id' => $this->getBumTicketCategoryId(),
            'category_id' => $this->getConsumptionCategoryId(),
            'priority_id' => $request->input('priority_id') ?: $this->getDefaultPriorityId(),
            'impact_id' => $request->input('impact_id') ?: $this->getDefaultImpactId(),
            'urgency_id' => $request->input('urgency_id') ?: $this->getDefaultUrgencyId(),
        ]);

        $validated = $request->validate([
            'reporter_name' => 'required|string|max:150',
            'reporter_email' => 'required|email|max:255',
            'request_type' => 'required|in:consumption',
            'category_id' => 'required|exists:problem_categories,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'impact_id' => 'nullable|exists:impacts,id',
            'urgency_id' => 'nullable|exists:urgencies,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'payload' => 'nullable|array',
            'payload.activity_name' => 'required|string|max:150',
            'payload.event_type' => 'required|string|max:100',
            'payload.event_date' => 'required|date',
            'payload.start_time' => 'required|date_format:H:i',
            'payload.end_time' => 'required|date_format:H:i',
            'payload.location' => 'required|string|max:150',
            'payload.participant_count' => 'required|integer|min:1',
            'payload.consumption_type' => 'required|string|max:100',
            'payload.request_reason' => 'required|string',
            'payload.organizer_unit' => 'nullable|string|max:150',
            'payload.pic_contact' => 'nullable|string|max:150',
            'payload.consumption_notes' => 'nullable|string',
            'attendance_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
            'documentation_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,zip,rar|max:5120',
            'activity_report_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'training_material_file' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,xls,xlsx|max:5120',
        ]);

        $requester = $this->findOrCreatePublicReporter($validated);
        $payload = $this->buildPayload('consumption', array_merge($validated['payload'] ?? [], [
            'reporter_name' => $requester->name,
            'reporter_email' => $requester->email,
            'submitted_from' => 'public',
        ]));
        $payload['workflow_status'] = 'WAITING_BUM_REVIEW';

        $ticket = $this->createPublicTicketFromPayload($validated, $payload, $requester, 'consumption');

        $this->storeRequestAttachment($request, $ticket, 'attendance_file', 'attendance', $requester);
        $this->storeRequestAttachment($request, $ticket, 'documentation_file', 'documentation', $requester);
        $this->storeRequestAttachment($request, $ticket, 'activity_report_file', 'activity_report', $requester);
        $this->storeRequestAttachment($request, $ticket, 'training_material_file', 'training_material', $requester);
        $this->sendPublicTicketEmails($ticket, $validated['category_id']);

        return redirect()->route('public.ticket.konsumsi.create')
            ->with('success', 'Permintaan konsumsi berhasil dikirim. Nomor ticket Anda: ' . $ticket->ticket_no);
    }

    public function storePublicAtkRtk(Request $request)
    {
        $request->merge([
            'request_type' => 'atk_rtk',
            'ticket_category_id' => $this->getBumTicketCategoryId(),
            'category_id' => $this->getAtkRtkCategoryId(),
            'priority_id' => $request->input('priority_id') ?: $this->getDefaultPriorityId(),
            'impact_id' => $request->input('impact_id') ?: $this->getDefaultImpactId(),
            'urgency_id' => $request->input('urgency_id') ?: $this->getDefaultUrgencyId(),
        ]);

        $validated = $request->validate([
            'reporter_name' => 'required|string|max:150',
            'reporter_email' => 'required|email|max:255',
            'request_type' => 'required|in:atk_rtk',
            'category_id' => 'required|exists:problem_categories,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'impact_id' => 'nullable|exists:impacts,id',
            'urgency_id' => 'nullable|exists:urgencies,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'payload' => 'nullable|array',
            'payload.request_subject' => 'required|string|max:150',
            'payload.item_type' => 'required|string|max:100',
            'payload.items' => 'required|array|min:1',
            'payload.items.*.item_id' => 'required|exists:consumable_items,id',
            'payload.items.*.quantity' => 'required|integer|min:1',
            'payload.needed_date' => 'required|date',
            'payload.delivery_location' => 'required|string|max:150',
            'payload.recipient_pic' => 'nullable|string|max:150',
            'payload.reporter_phone' => 'nullable|string|max:30',
        ]);

        $requester = $this->findOrCreatePublicReporter($validated);
        $payload = $this->buildPayload('atk_rtk', $this->enrichAtkRtkPayload(array_merge($validated['payload'] ?? [], [
            'reporter_name' => $requester->name,
            'reporter_email' => $requester->email,
            'submitted_from' => 'public',
        ])));
        $payload['workflow_status'] = 'WAITING_BUM_REVIEW';

        $ticket = $this->createPublicTicketFromPayload($validated, $payload, $requester, 'atk_rtk');
        $this->sendPublicTicketEmails($ticket, $validated['category_id']);

        return redirect()->route('public.ticket.atk-rtk.create')
            ->with('success', 'Permintaan ATK/RTK berhasil dikirim. Nomor ticket Anda: ' . $ticket->ticket_no);
    }

    /**
     * Store a newly created ticket
     */
    public function store(Request $request)
    {
        $requestType = $request->input('request_type', 'general');

        $rules = [
            'request_type' => 'required|in:general,consumption,atk_rtk,ga_request_finding',
            'category_id' => 'required|exists:problem_categories,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'impact_id' => 'nullable|exists:impacts,id',
            'urgency_id' => 'nullable|exists:urgencies,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'payload' => 'nullable|array',
        ];

        if ($requestType === 'consumption') {
            $rules = array_merge($rules, [
                'payload.activity_name' => 'required|string|max:150',
                'payload.event_type' => 'required|string|max:100',
                'payload.event_date' => 'required|date',
                'payload.start_time' => 'required|date_format:H:i',
                'payload.end_time' => 'required|date_format:H:i',
                'payload.location' => 'required|string|max:150',
                'payload.participant_count' => 'required|integer|min:1',
                'payload.consumption_type' => 'required|string|max:100',
                'payload.request_reason' => 'required|string',
                'payload.supervisor_id' => 'required|exists:users,id',
                'attendance_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
                'documentation_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,zip,rar|max:5120',
                'activity_report_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
                'training_material_file' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,xls,xlsx|max:5120',
            ]);
        } elseif ($requestType === 'atk_rtk') {
            $rules = array_merge($rules, [
                'payload.request_subject' => 'required|string|max:150',
                'payload.item_type' => 'required|string|max:100',
                'payload.items' => 'required|array|min:1',
                'payload.items.*.item_id' => 'required|exists:consumable_items,id',
                'payload.items.*.quantity' => 'required|integer|min:1',
                'payload.supervisor_id' => 'nullable|exists:users,id',
                'payload.needed_date' => 'required|date',
                'payload.delivery_location' => 'required|string|max:150',
            ]);
        } elseif ($requestType === 'ga_request_finding') {
            $rules = array_merge($rules, [
                'payload.report_type' => 'required|in:Permintaan,Temuan',
                'payload.location' => 'required|string|max:150',
                'payload.detail_location' => 'nullable|string|max:150',
                'payload.description' => 'required|string',
                'payload.expected_action' => 'nullable|string|max:255',
                'payload.reporter_phone' => 'nullable|string|max:30',
                'evidence_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);
        } else {
            $rules = array_merge($rules, [
                'title' => 'required|string|max:150',
                'description' => 'nullable|string',
            ]);
        }

        $validated = $request->validate($rules);

        if ($requestType === 'atk_rtk') {
            $validated['payload'] = $this->enrichAtkRtkPayload($validated['payload'] ?? []);
            $estimatedAmount = (float) data_get($validated, 'payload.total_estimated_amount', 0);
            if ($estimatedAmount <= 0) {
                $estimatedPayload = $this->buildPayload('atk_rtk', $validated['payload']);
                $estimatedAmount = (float) data_get($estimatedPayload, 'total_estimated_amount', 0);
            }
            $threshold = config('bum.atk_rtk_manager_approval_threshold', 100000);
            if ($estimatedAmount >= $threshold && empty(data_get($validated, 'payload.supervisor_id'))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payload.supervisor_id' => 'Atasan wajib dipilih jika estimasi permintaan mencapai threshold approval.',
                ]);
            }
        }

        $departments = DepartmentCategory::with('department')
            ->where('category_id', $validated['category_id'])
            ->get()
            ->pluck('department')
            ->filter();

        $assignedDepartment = $departments->first();

        if (!$assignedDepartment && in_array($requestType, ['consumption', 'atk_rtk', 'ga_request_finding'], true)) {
            $assignedDepartment = $this->findDepartmentByKeywords(['umum', 'general affairs', 'ga', 'procurement']);
        }

        if ($requestType === 'consumption') {
            $validated['category_id'] = $this->getConsumptionCategoryId();
            $validated['ticket_category_id'] = $this->getBumTicketCategoryId();
        } elseif ($requestType === 'atk_rtk') {
            $validated['category_id'] = $this->getAtkRtkCategoryId();
            $validated['ticket_category_id'] = $this->getBumTicketCategoryId();
        } elseif ($requestType === 'ga_request_finding') {
            $validated['category_id'] = $this->getGaRequestFindingCategoryId();
            $validated['ticket_category_id'] = $this->getBumTicketCategoryId();
        }

        $payload = $this->buildPayload($requestType, $validated['payload'] ?? []);

        if (in_array($requestType, ['consumption', 'atk_rtk'], true) && !empty($payload['supervisor_id'])) {
            $payload['supervisor_name'] = User::whereKey($payload['supervisor_id'])->value('name');
        }

        $ticketNo = $this->generateTicketNo($validated['category_id']);

        $ticket = Ticket::create([
            'ticket_no' => $ticketNo,
            'requester_id' => auth()->id(),
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

        // Create history
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => auth()->id(),
            'status_id' => $ticket->status_id,
            'action'    => 'Ticket dibuat (' . $this->getRequestTypeLabel($requestType) . ')',
        ]);

        if (data_get($payload, 'workflow_status') === 'WAITING_MANAGER_APPROVAL') {
            $this->createManagerApproval($ticket);
        }

        if ($requestType === 'consumption') {
            $this->storeRequestAttachment($request, $ticket, 'attendance_file', 'attendance');
            $this->storeRequestAttachment($request, $ticket, 'documentation_file', 'documentation');
            $this->storeRequestAttachment($request, $ticket, 'activity_report_file', 'activity_report');
            $this->storeRequestAttachment($request, $ticket, 'training_material_file', 'training_material');
        } elseif ($requestType === 'ga_request_finding') {
            $this->storeRequestAttachment($request, $ticket, 'evidence_file', 'ga_report_evidence');
        }

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

        if ($requestType === 'consumption') {
            return redirect()->route('ticket.konsumsi.create')
                ->with('success', 'Permintaan konsumsi berhasil dibuat dan flow pengajuannya sudah tercatat.');
        }

        if ($requestType === 'atk_rtk') {
            return redirect()->route('ticket.atk-rtk.create')
                ->with('success', 'Permintaan ATK/RTK berhasil dibuat.');
        }

        if ($requestType === 'ga_request_finding') {
            return redirect()->route('ticket.ga-permintaan-temuan.create')
                ->with('success', 'Laporan GA Permintaan & Temuan berhasil dibuat.');
        }

        return redirect()->route('ticket.general')->with('success', 'Ticket berhasil dibuat dan email dikirim.');
    }

    // schema
    public function getSchema($categoryId)
    {
        $schemaModel = TicketFormSchema::where('ticket_category_id', $categoryId)->first();

        // Return array kosong jika tidak ada schema khusus
        return response()->json($schemaModel ? $schemaModel->schema : []);
    }


    /**
     * Show single ticket
     */
    public function show(Ticket $ticket)
    {
        $ticket->load(['requester', 'priority', 'status', 'department', 'assignedUser', 'assignedDepartment', 'histories.user', 'comments.user', 'attachments']);
        $users = User::all();
        $departments = Department::all();
        $statuses = Status::all();
        $consumableItems = ConsumableItem::where('is_active', true)->orderBy('name')->get();

        return view('ticket.show', compact('ticket', 'users', 'departments', 'statuses', 'consumableItems'));
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

        $previousStatus = $ticket->status?->name;

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

        $this->mobileNotifications()->notifyTicketStatusChanged($ticket->fresh(['requester', 'status']), auth()->user(), $previousStatus);

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

        if ($assignedUser = $ticket->assignedUser()->first()) {
            $this->mobileNotifications()->notifyTicketAssigned($ticket->fresh(['status']), $assignedUser);
        }

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

            $ticket->histories()->create([
                'user_id' => auth()->id(),
                'status_id' => $ticket->status_id,
                'action' => 'Komentar ditambahkan',
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

        $approval = Approval::where('request_id', $ticket->id)->findOrFail($request->approval_id);

        if ((int) $ticket->requester_id === (int) auth()->id() && (auth()->user()->role ?? null) !== 'admin') {
            return redirect()->back()->with('error', 'User tidak boleh approve request miliknya sendiri.');
        }

        $approval->update([
            'status' => $request->status,
            'notes' => $request->note,
            'decided_at' => now(),
        ]);

        $payload = $ticket->payload ?? [];
        $previousStatus = data_get($payload, 'workflow_status') ?: ($ticket->status->name ?? null);
        $requestType = data_get($payload, 'request_type');
        if ($requestType === 'atk_rtk') {
            $payload['workflow_status'] = $request->status === 'approved' ? 'APPROVED_BY_MANAGER' : 'REJECTED_BY_MANAGER';
            if ($request->status === 'approved') {
                $payload['workflow_status'] = 'WAITING_BUM_REVIEW';
            }
        } elseif ($requestType === 'consumption') {
            $payload['workflow_status'] = $request->status === 'approved' ? 'WAITING_BUM_VERIFICATION' : 'REJECTED_BY_MANAGER';
        }
        $ticket->update(['payload' => $payload]);

        $ticket->histories()->create([
            'user_id' => Auth::id(),
            'action' => ucfirst($request->status) . " approval at level " . $approval->level,
        ]);

        $this->mobileNotifications()->notifyTicketStatusChanged($ticket->fresh(['requester', 'status']), auth()->user(), $previousStatus);

        return redirect()->back()->with('success', 'Persetujuan berhasil dicatat.');
    }

    public function bumReviewAtkRtk(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'workflow_status' => 'required|in:STOCK_CHECKED,WAITING_PROCUREMENT,READY_TO_HANDOVER,CANCELLED',
            'approved_qty' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $payload = $ticket->payload ?? [];
        $previousStatus = data_get($payload, 'workflow_status') ?: ($ticket->status->name ?? null);
        $payload['workflow_status'] = $data['workflow_status'];
        $payload['approved_qty'] = $data['approved_qty'] ?? data_get($payload, 'quantity');
        $payload['bum_review_notes'] = $data['notes'] ?? null;
        $payload['bum_reviewed_at'] = now()->toDateTimeString();

        $ticket->update(['payload' => $payload]);
        $ticket->histories()->create([
            'user_id' => auth()->id(),
            'action' => 'BUM review ATK/RTK: ' . $data['workflow_status'],
        ]);

        $this->mobileNotifications()->notifyTicketStatusChanged($ticket->fresh(['requester', 'status']), auth()->user(), $previousStatus);

        return back()->with('success', 'Review BUM berhasil dicatat.');
    }

    public function replenishAtkRtk(Request $request, Ticket $ticket, ConsumableStockService $stockService)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:consumable_items,id',
            'large_qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $payload = $ticket->payload ?? [];
        $previousStatus = data_get($payload, 'workflow_status') ?: ($ticket->status->name ?? null);
        $payloadItems = collect(data_get($payload, 'items', []));
        if ($payloadItems->isNotEmpty() && !$payloadItems->contains(fn ($row) => (int) data_get($row, 'item_id') === (int) $data['item_id'])) {
            return back()->with('error', 'Barang tidak ada di daftar permintaan ticket ini.');
        }

        try {
            DB::transaction(function () use ($data, $ticket, $stockService, &$payload) {
                $item = ConsumableItem::lockForUpdate()->findOrFail($data['item_id']);
                $largeQty = (int) $data['large_qty'];
                $smallQty = $largeQty * max(1, (int) $item->conversion_qty);

                $stockService->transferBigToSmall(
                    $item,
                    $largeQty,
                    'atk_rtk_replenishment',
                    $ticket->id,
                    $data['notes'] ?: 'Transfer Gudang Besar ke Gudang Kecil untuk ' . $ticket->ticket_no,
                    auth()->id()
                );

                $payload['replenishments'][] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'large_qty' => $largeQty,
                    'large_uom' => $item->large_uom,
                    'small_qty' => $smallQty,
                    'small_uom' => $item->small_uom,
                    'notes' => $data['notes'] ?? null,
                    'transferred_at' => now()->toDateTimeString(),
                    'transferred_by' => auth()->id(),
                ];
                $payload['workflow_status'] = 'STOCK_CHECKED';
                $ticket->update(['payload' => $payload]);
                $ticket->histories()->create([
                    'user_id' => auth()->id(),
                    'action' => "Transfer {$largeQty} {$item->large_uom} ke Gudang Kecil ({$smallQty} {$item->small_uom}).",
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->mobileNotifications()->notifyTicketStatusChanged($ticket->fresh(['requester', 'status']), auth()->user(), $previousStatus);

        return back()->with('success', 'Barang berhasil diambil dari Gudang Besar dan masuk ke Gudang Kecil.');
    }

    public function handoverAtkRtk(Request $request, Ticket $ticket, ConsumableStockService $stockService)
    {
        $data = $request->validate([
            'item_id' => 'nullable|exists:consumable_items,id',
            'fulfilled_qty' => 'nullable|integer|min:1',
            'received_by' => 'required|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $payload = $ticket->payload ?? [];
        $previousStatus = data_get($payload, 'workflow_status') ?: ($ticket->status->name ?? null);
        $payloadItems = collect(data_get($payload, 'items', []));

        if ($payloadItems->isEmpty()) {
            $approvedQty = (int) (data_get($payload, 'approved_qty') ?: data_get($payload, 'quantity', 0));
            if ((int) $data['fulfilled_qty'] > $approvedQty) {
                return back()->with('error', 'Qty fulfilled tidak boleh melebihi qty approved.');
            }
        }

        try {
            DB::transaction(function () use ($data, $ticket, $stockService, &$payload, $payloadItems) {
                if ($payloadItems->isNotEmpty()) {
                    foreach ($payloadItems as $row) {
                        $item = ConsumableItem::lockForUpdate()->findOrFail((int) data_get($row, 'item_id'));
                        $qty = (int) data_get($row, 'quantity', 0);
                        $stockService->decreaseSmall($item, $qty, 'atk_rtk_request', $ticket->id, 'Handover ' . $ticket->ticket_no, auth()->id());
                    }

                    $payload['fulfilled_qty'] = (int) data_get($payload, 'total_quantity', $payloadItems->sum(fn ($row) => (int) data_get($row, 'quantity', 0)));
                    $payload['fulfilled_items'] = $payloadItems->map(fn ($row) => [
                        'item_id' => data_get($row, 'item_id'),
                        'item_name' => data_get($row, 'item_name'),
                        'quantity' => (int) data_get($row, 'quantity', 0),
                        'small_uom' => data_get($row, 'small_uom'),
                    ])->values()->all();
                } else {
                    $item = ConsumableItem::lockForUpdate()->findOrFail($data['item_id']);
                    $stockService->decreaseSmall($item, (int) $data['fulfilled_qty'], 'atk_rtk_request', $ticket->id, 'Handover ' . $ticket->ticket_no, auth()->id());
                    $payload['item_id'] = $item->id;
                    $payload['item_name'] = $item->name;
                    $payload['fulfilled_qty'] = $data['fulfilled_qty'];
                }

                $payload['received_by'] = $data['received_by'];
                $payload['handover_notes'] = $data['notes'] ?? null;
                $payload['handover_date'] = now()->toDateString();
                $payload['workflow_status'] = 'HANDED_OVER';
                $ticket->update(['payload' => $payload]);
                $ticket->histories()->create([
                    'user_id' => auth()->id(),
                    'action' => 'Barang ATK/RTK diserahkan dan stok berkurang.',
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->mobileNotifications()->notifyTicketStatusChanged($ticket->fresh(['requester', 'status']), auth()->user(), $previousStatus);

        return back()->with('success', 'Handover berhasil dan stock card tercatat.');
    }

    public function updateConsumptionFlow(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'workflow_status' => 'required|in:APPROVED_BY_BUM,ORDERED_TO_VENDOR,RECEIVED,WAITING_ACCOUNTABILITY,REPORTED,CLOSED,CANCELLED',
            'vendor_name' => 'nullable|string|max:150',
            'order_date' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $previousStatus = data_get($ticket->payload, 'workflow_status') ?: ($ticket->status->name ?? null);
        $payload = array_merge($ticket->payload ?? [], array_filter($data, fn ($value) => $value !== null));
        $payload['bum_updated_at'] = now()->toDateTimeString();
        $ticket->update(['payload' => $payload]);
        $ticket->histories()->create([
            'user_id' => auth()->id(),
            'action' => 'Update konsumsi rapat: ' . $data['workflow_status'],
        ]);

        $this->mobileNotifications()->notifyTicketStatusChanged($ticket->fresh(['requester', 'status']), auth()->user(), $previousStatus);

        return back()->with('success', 'Status konsumsi rapat berhasil diperbarui.');
    }

    public function uploadConsumptionEvidence(Request $request, Ticket $ticket)
    {
        $request->validate([
            'attendance_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
            'documentation_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,zip,rar|max:5120',
            'activity_report_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'training_material_file' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,xls,xlsx|max:5120',
        ]);

        if (!$request->hasFile('attendance_file') && !$request->hasFile('documentation_file') && !$request->hasFile('activity_report_file') && !$request->hasFile('training_material_file')) {
            return back()->with('error', 'Minimal satu evidence pertanggungjawaban wajib diupload.');
        }

        $this->storeRequestAttachment($request, $ticket, 'attendance_file', 'attendance');
        $this->storeRequestAttachment($request, $ticket, 'documentation_file', 'documentation');
        $this->storeRequestAttachment($request, $ticket, 'activity_report_file', 'activity_report');
        $this->storeRequestAttachment($request, $ticket, 'training_material_file', 'training_material');

        $payload = $ticket->payload ?? [];
        $previousStatus = data_get($payload, 'workflow_status') ?: ($ticket->status->name ?? null);
        $payload['workflow_status'] = 'ACCOUNTABILITY_SUBMITTED';
        $payload['accountability_submitted_at'] = now()->toDateTimeString();
        $ticket->update(['payload' => $payload]);
        $ticket->histories()->create([
            'user_id' => auth()->id(),
            'action' => 'Pertanggungjawaban konsumsi rapat diupload.',
        ]);

        $this->mobileNotifications()->notifyTicketStatusChanged($ticket->fresh(['requester', 'status']), auth()->user(), $previousStatus);

        return back()->with('success', 'Evidence pertanggungjawaban berhasil diupload.');
    }

    private function createManagerApproval(Ticket $ticket): void
    {
        $supervisorId = data_get($ticket->payload, 'supervisor_id');
        $approver = $supervisorId
            ? User::where('id', '!=', $ticket->requester_id)->find($supervisorId)
            : null;

        if (!$approver) {
            $approver = User::where('id', '!=', $ticket->requester_id)
                ->whereIn('role', ['approver', 'manager', 'admin'])
                ->first();
        }

        if (!$approver) {
            return;
        }

        $approval = Approval::firstOrCreate([
            'request_id' => $ticket->id,
            'level' => 1,
        ], [
            'approver_id' => $approver->id,
            'status' => 'Pending',
        ]);

        if ($approval->wasRecentlyCreated) {
            $this->mobileNotifications()->notifyApprovalRequested($ticket, $approval);
        }
    }

    private function mobileNotifications(): LrtjSpaceMobileNotificationService
    {
        return app(LrtjSpaceMobileNotificationService::class);
    }

}
