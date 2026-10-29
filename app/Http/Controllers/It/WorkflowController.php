<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\It\FormEngine;
use App\Services\It\TicketWorkflow;
use App\Support\ItAccess;
use App\Support\ServiceDomains;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkflowController extends Controller
{
    private function json(Request $r): bool
    {
        return $r->expectsJson() || $r->is('api/*');
    }

    public function index(Request $r, TicketWorkflow $workflow)
    {
        $q = Ticket::query();
        ItAccess::constrainIt($q);
        ItAccess::constrainReadable($q, $r->user());
        $r->validate(['status' => 'nullable|in:'.implode(',', array_unique(array_merge(['submitted'], TicketWorkflow::STATES))), 'priority' => 'nullable|in:P1,P2,P3,P4', 'scope' => 'nullable|in:mine,assigned,queue', 'q' => 'nullable|string|max:150']);
        if ($r->filled('status')) {
            $q->whereHas('itDetail', fn ($d) => $d->where('status', $r->status));
        }
        if ($r->filled('priority')) {
            $q->whereHas('itDetail', fn ($d) => $d->where('priority', $r->priority));
        }
        if ($r->scope === 'mine') {
            $q->where('requester_id', $r->user()->id);
        }
        if ($r->scope === 'assigned') {
            $q->where('assigned_user_id', $r->user()->id);
        }
        if ($r->scope === 'queue') {
            $q->whereNull('assigned_user_id')->whereHas('itDetail', fn ($d) => $d->whereIn('status', ['triage', 'queued', 'approval_exception']));
        }
        if ($r->filled('q')) {
            $q->where(fn ($s) => $s->where('title', 'like', '%'.$r->q.'%')->orWhere('ticket_no', 'like', '%'.$r->q.'%'));
        }
        $tickets = $q->with(['itDetail', 'assignedUser', 'requester'])->latest()->paginate(15)->withQueryString();
        if ($this->json($r)) {
            return response()->json(['data' => collect($tickets->items())->map(fn ($t) => $workflow->view($r->user(), $t)), 'current_page' => $tickets->currentPage(), 'last_page' => $tickets->lastPage()]);
        }

        return view('it.index', ['tickets' => $tickets, 'enabled' => $workflow->enabled($r->user())]);
    }

    public function requestEntry(Request $r, TicketWorkflow $workflow)
    {
        if (! ServiceDomains::itActive()) {
            return view('it.coming-soon');
        }
        if ($workflow->enabled($r->user())) {
            return redirect()->route('it.create');
        }

        return view('it.request-unavailable');
    }

    public function create(Request $r, TicketWorkflow $workflow)
    {
        abort_unless($workflow->enabled($r->user()), 423, 'Pengajuan menunggu SLA dan pilot.');
        $cfg = $workflow->active()->configuration;
        // Never return the administrative bundle or technician-only fields to requesters.
        $catalog = array_values(array_filter($cfg['catalog'], fn ($c) => $c['is_active'] && app(\App\Services\It\PilotGate::class)->allowed($r->user(), $c['key'])));

        return $this->json($r) ? response()->json(['catalog' => $catalog]) : view('it.create', compact('catalog'));
    }

    public function form(Request $r, TicketWorkflow $workflow, FormEngine $engine)
    {
        abort_unless($workflow->enabled($r->user()), 423, 'Pengajuan menunggu SLA dan pilot.');
        $r->validate(['catalog_key' => 'required|string|max:50', 'answers' => 'sometimes|array|max:60']);
        abort_unless(app(\App\Services\It\PilotGate::class)->allowed($r->user(), $r->catalog_key), 404);
        $v = $workflow->active();
        $cfg = $v->configuration;
        $c = collect($cfg['catalog'])->firstWhere('key', $r->catalog_key);
        abort_unless($c && $c['is_active'], 404);
        $result = $engine->inspect($cfg, $c['form_key'], 'requester', $r->input('answers', []));

        return response()->json($result + ['configuration_version_id' => $v->id, 'dependencies' => $workflow->dependencies($cfg, $c['form_key'])]);
    }

    public function reviseForm(Request $r, Ticket $ticket, TicketWorkflow $workflow)
    {
        abort_unless($ticket->requester_id === $r->user()->id && ItAccess::isIt($ticket) && ItAccess::canRead($r->user(), $ticket), 403);
        $d = $ticket->itDetail;
        abort_unless($d && $d->catalog_key && $d->status === 'needs_revision', 409);
        $cfg = $d->configurationVersion->configuration;
        if ($r->isMethod('post')) {
            $r->validate(['answers' => 'sometimes|array|max:60']);

            return response()->json(app(FormEngine::class)->inspect($cfg, $d->form_key, 'requester', $r->input('answers', [])) + ['dependencies' => $workflow->dependencies($cfg, $d->form_key)]);
        }
        $catalog = [collect($cfg['catalog'])->firstWhere('key', $d->catalog_key)];
        $answers = $workflow->view($r->user(), $ticket)['answers'];

        return view('it.create', compact('catalog', 'ticket', 'answers'));
    }

    public function store(Request $r, TicketWorkflow $workflow)
    {
        if (! $r->has('answers')) {
            $r->merge(['answers' => []]);
        }
        $t = $workflow->submit($r->user(), $r->all(), $r->file('uploads', []));

        return $this->json($r) ? response()->json($workflow->view($r->user(), $t), 201) : redirect()->route('it.show', $t)->with('success', 'Tiket IT tercatat.');
    }

    public function show(Request $r, Ticket $ticket, TicketWorkflow $workflow)
    {
        $detail = $workflow->view($r->user(), $ticket);
        if ($this->json($r)) {
            return response()->json($detail);
        }
        $teams = $detail['staff'] ? DB::table('support_teams')->where('is_active', true)->orderBy('name')->get() : collect();
        $members = $detail['staff'] ? DB::table('support_team_members')->join('users', 'users.id', '=', 'support_team_members.user_id')->get(['users.id', 'users.name', 'support_team_id']) : collect();

        $ticket->loadMissing(['requester', 'itDetail.configurationVersion']);
        $visibleUserIds = collect($detail['comments'] ?? [])->pluck('author_id')
            ->merge(collect($detail['events'] ?? [])->pluck('actor_id'))
            ->merge(collect($detail['tasks'] ?? [])->pluck('assigned_to'))
            ->filter()->unique();
        $visibleNames = User::whereIn('id', $visibleUserIds)->pluck('name', 'id');
        $teamNames = $teams->pluck('name', 'id');
        $fieldLabels = [];
        $detailLabels = [];
        if (! $detail['read_only']) {
            $config = $ticket->itDetail->configurationVersion->configuration;
            $form = collect($config['forms'])->firstWhere('key', $detail['form_key']);
            $fieldLabels = collect($form['fields'] ?? [])->pluck('label', 'key')->all();
            $catalog = collect($config['catalog'])->firstWhere('key', $ticket->itDetail->catalog_key);
            $detailLabels = [
                'catalog' => $catalog['name'] ?? null,
                'category' => collect($config['categories'])->firstWhere('key', $ticket->itDetail->category_key)['name'] ?? null,
                'service' => collect($config['services'])->firstWhere('key', $ticket->itDetail->service_key)['name'] ?? null,
            ];
        }

        return view('it.show', compact('ticket', 'detail', 'teams', 'members', 'teamNames', 'visibleNames', 'fieldLabels', 'detailLabels'));
    }

    public function action(Request $r, Ticket $ticket, string $action, TicketWorkflow $workflow)
    {
        if ($action === 'revise' && ! $r->has('answers')) {
            $r->merge(['answers' => []]);
        }
        $t = $workflow->action($r->user(), $ticket, $action, $r->all(), $r->file('uploads', []));

        return $this->json($r) ? response()->json($workflow->view($r->user(), $t)) : redirect()->route('it.show', $t)->with('success', 'Perubahan tiket tersimpan.');
    }

    public function attachment(Request $r, Ticket $ticket, int $attachment, TicketWorkflow $workflow)
    {
        $file = $workflow->attachment($r->user(), $ticket, $attachment);

        return Storage::disk('it_private')->download($file->path, $file->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function approvals(Request $r)
    {
        $approvals = DB::table('it_ticket_approvals')->join('requests', 'requests.id', '=', 'it_ticket_approvals.request_id')
            ->where('approver_id', $r->user()->id)->orderByDesc('it_ticket_approvals.id')
            ->select(['it_ticket_approvals.id', 'requests.ticket_no', 'requests.title', 'it_ticket_approvals.status', 'it_ticket_approvals.cycle'])->paginate(20);

        return $this->json($r) ? response()->json($approvals) : view('it.approvals', compact('approvals'));
    }

    public function approval(Request $r, int $approval, TicketWorkflow $workflow)
    {
        $detail = $workflow->approvalView($r->user(), $approval);

        return $this->json($r) ? response()->json($detail) : view('it.approval', compact('detail'));
    }

    public function decide(Request $r, int $approval, TicketWorkflow $workflow)
    {
        $workflow->decide($r->user(), $approval, $r->all());

        return $this->json($r) ? response()->json($workflow->approvalView($r->user(), $approval)) : redirect()->route('it.approvals')->with('success', 'Keputusan approval tersimpan.');
    }

    public function notifications(Request $r, TicketWorkflow $workflow)
    {
        $rows = DB::table('it_ticket_notifications')->join('it_ticket_events', 'it_ticket_events.id', '=', 'it_ticket_notifications.event_id')
            ->where('it_ticket_notifications.user_id', $r->user()->id)->orderByDesc('it_ticket_notifications.id')
            ->limit(250)->get(['it_ticket_notifications.id', 'it_ticket_notifications.kind', 'it_ticket_notifications.read_at', 'it_ticket_events.request_id', 'it_ticket_events.action', 'it_ticket_events.created_at']);
        $data = [];
        foreach ($rows as $row) {
            $t = Ticket::find($row->request_id);
            if (! $t) {
                continue;
            }
            $read = ItAccess::canRead($r->user(), $t);
            $staff = $read && $t->itDetail && $workflow->staff($r->user(), $t, $t->itDetail);
            $a = DB::table('it_ticket_approvals')->where('request_id', $t->id)->where('approver_id', $r->user()->id)->where('status', 'pending')->first();
            if ((! $read && ! $a) || ($row->kind === 'internal' && ! $staff)) {
                continue;
            }
            $data[] = ['id' => $row->id, 'ticket_no' => $t->ticket_no, 'message' => 'Ada pembaruan tiket IT.', 'read_at' => $row->read_at,
                'url' => $read ? route('it.show', $t) : route('it.approval', $a->id), 'created_at' => $row->created_at];
            if (count($data) >= 50) {
                break;
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('it_operation_notifications')) {
            $extra = DB::table('it_operation_notifications')->where('user_id', $r->user()->id)->orderByDesc('id')->limit(100)->get();
            foreach ($extra as $item) {
                $t = Ticket::find($item->request_id);
                if (! $t || ! $t->itDetail || ! $workflow->staff($r->user(), $t, $t->itDetail)) {
                    continue;
                }
                $data[] = ['id' => $item->id, 'ticket_no' => $t->ticket_no, 'message' => $item->message, 'read_at' => $item->read_at, 'url' => route('it.show', $t), 'created_at' => $item->created_at,
                    'read_url' => route('it.operations.notifications.read', $item->id), 'kind' => 'operational'];
            }
            usort($data, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
            $data = array_slice($data, 0, 50);
        }

        return $this->json($r) ? response()->json(['data' => $data]) : view('it.notifications', compact('data'));
    }

    public function readNotification(Request $r, int $notification)
    {
        abort_unless(DB::table('it_ticket_notifications')->where('id', $notification)->where('user_id', $r->user()->id)->exists(), 404);
        DB::table('it_ticket_notifications')->where('id', $notification)->where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->json($r) ? response()->json(['read' => true]) : back();
    }
}
