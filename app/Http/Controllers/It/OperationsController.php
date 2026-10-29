<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Master\Ticket;
use App\Services\It\OperationHealth;
use App\Services\It\TicketWorkflow;
use App\Support\ItAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $member = DB::table('support_team_members')->join('support_teams', 'support_teams.id', '=', 'support_team_members.support_team_id')->where('user_id', $u->id)->where('is_active', true)->whereIn('role', ['technician', 'coordinator'])->exists();
        abort_unless($member || ItAccess::can($u, 'manage_it_operations'), 403);
        abort_unless(Schema::hasTable('it_sla_clocks'), 503, 'Migration operasi belum tersedia.');
        $r->validate(['filter' => 'nullable|in:all,near_due,breached,backlog,major', 'minutes' => 'sometimes|integer|between:5,1440']);
        $now = CarbonImmutable::now('UTC');
        $cutoff = $now->addMinutes((int) $r->input('minutes', 30));
        $q = Ticket::query();
        ItAccess::constrainIt($q);
        ItAccess::constrainReadable($q, $u);
        $q->whereHas('itDetail', fn ($d) => $d->whereNotNull('catalog_key'));
        // Dashboard aggregates always use the same ticket visibility scope.
        $ids = (clone $q)->pluck('requests.id');
        $open = DB::table('it_ticket_details')->whereIn('request_id', $ids)->whereNotIn('status', ['resolved', 'closed', 'cancelled', 'rejected']);
        $workload = (clone $open)->join('support_teams', 'support_teams.id', '=', 'it_ticket_details.support_team_id')->join('requests', 'requests.id', '=', 'it_ticket_details.request_id')->leftJoin('users', 'users.id', '=', 'requests.assigned_user_id')
            ->selectRaw('support_teams.name as team, users.name as assignee, it_ticket_details.priority, count(*) as total')->groupBy('support_teams.name', 'users.name', 'it_ticket_details.priority')->get();
        $clockQuery = DB::table('it_sla_clocks')->join('it_ticket_details', 'it_ticket_details.request_id', '=', 'it_sla_clocks.request_id')->whereIn('it_sla_clocks.request_id', $ids)->whereColumn('it_sla_clocks.cycle', 'it_ticket_details.sla_cycle')->whereIn('it_sla_clocks.state', ['running', 'paused']);
        $nearIds = (clone $clockQuery)->where('state', 'running')->whereBetween('due_at', [$now->toDateTimeString(), $cutoff->toDateTimeString()])->pluck('it_sla_clocks.request_id')->unique();
        $breachIds = (clone $clockQuery)->where(fn ($c) => $c->whereNotNull('breached_at')->orWhere('due_at', '<', $now->toDateTimeString()))->pluck('it_sla_clocks.request_id')->unique();
        $backlogIds = (clone $open)->where('it_ticket_details.created_at', '<', now()->subDays(7))->pluck('request_id');
        $majorIds = DB::table('it_ticket_details')->whereIn('request_id', $ids)->where('is_major', true)->pluck('request_id');
        $counts = ['open' => (clone $open)->count(), 'near_due' => $nearIds->count(), 'breached' => $breachIds->count(), 'backlog' => $backlogIds->count(), 'major' => $majorIds->count()];
        $filter = $r->input('filter', 'all');
        $filtered = match ($filter) {
            'near_due' => $nearIds,'breached' => $breachIds,'backlog' => $backlogIds,'major' => $majorIds,default => null
        };
        if ($filtered !== null) {
            $q->whereIn('id', $filtered);
        } else {
            $q->whereHas('itDetail', fn ($d) => $d->whereNotIn('status', ['resolved', 'closed', 'cancelled', 'rejected']));
        }
        $tickets = $q->with(['itDetail', 'assignedUser'])->latest()->paginate(20)->withQueryString();
        if ($r->expectsJson() || $r->is('api/*')) {
            return response()->json(['counts' => $counts, 'workload' => $workload, 'data' => collect($tickets->items())->map(fn ($t) => ['id' => $t->id, 'ticket_no' => $t->ticket_no, 'title' => $t->title, 'status' => $t->itDetail->status, 'priority' => $t->itDetail->priority, 'major' => (bool) $t->itDetail->is_major]), 'current_page' => $tickets->currentPage(), 'last_page' => $tickets->lastPage()]);
        }

        return view('it.operations', compact('counts', 'workload', 'tickets'));
    }

    public function health(Request $r, OperationHealth $health)
    {
        abort_unless(ItAccess::can($r->user(), 'manage_it_configuration') || ItAccess::can($r->user(), 'manage_it_operations'), 403);
        $report = $health->report();

        return ($r->expectsJson() || $r->is('api/*')) ? response()->json($report) : view('it.health', compact('report'));
    }

    public function read(Request $r, int $notification, TicketWorkflow $workflow)
    {
        $n = DB::table('it_operation_notifications')->where('id', $notification)->where('user_id', $r->user()->id)->first();
        abort_unless($n, 404);
        $ticket = Ticket::findOrFail($n->request_id);
        abort_unless($workflow->staff($r->user(), $ticket, $ticket->itDetail), 403);
        DB::table('it_operation_notifications')->where('id',$notification)->whereNull('read_at')->update(['read_at' => CarbonImmutable::now('UTC')]);

        return $r->expectsJson() ? response()->json(['read' => true]) : back();
    }
}
