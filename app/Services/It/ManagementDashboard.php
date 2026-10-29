<?php

namespace App\Services\It;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Support\ItAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ManagementDashboard
{
    private const TERMINAL = ['resolved', 'closed', 'cancelled', 'rejected'];

    public function scope(User $user, array $filters): Builder
    {
        $query = Ticket::query();
        ItAccess::constrainIt($query);
        ItAccess::constrainReadable($query, $user);
        $query->whereHas('itDetail', function ($detail) use ($filters) {
            $detail->whereNotNull('catalog_key');
            foreach (['record_type', 'priority', 'support_team_id', 'service_key', 'category_key', 'status'] as $key) {
                if (! empty($filters[$key])) {
                    $detail->where($key, $filters[$key]);
                }
            }
        });
        if (! empty($filters['assigned_user_id'])) {
            $query->where('requests.assigned_user_id', $filters['assigned_user_id']);
        }

        return $query;
    }

    public function detailScope(User $user, array $filters, string $metric): Builder
    {
        $query = $this->scope($user, $filters);
        $zone = 'Asia/Jakarta';
        $start = CarbonImmutable::parse($filters['from'], $zone)->startOfDay()->setTimezone(config('app.timezone', $zone));
        $end = CarbonImmutable::parse($filters['to'], $zone)->addDay()->startOfDay()->setTimezone(config('app.timezone', $zone));
        $now = CarbonImmutable::now('UTC');
        if ($metric === 'incoming') {
            $query->where('requests.created_at', '>=', $start)->where('requests.created_at', '<', $end);
        } elseif ($metric === 'resolved') {
            $query->whereIn('requests.id', DB::table('it_ticket_events')->where('action', 'resolve')->where('created_at', '>=', $start)->where('created_at', '<', $end)->select('request_id'));
        } else {
            $query->whereHas('itDetail', fn ($d) => $d->whereNotIn('status', self::TERMINAL));
            if (in_array($metric, ['near', 'breached'], true)) {
                $clocks = DB::table('it_sla_clocks as c')->join('it_ticket_details as d', 'd.request_id', '=', 'c.request_id')->whereColumn('c.cycle', 'd.sla_cycle')->whereIn('c.state', ['running', 'paused']);
                if ($metric === 'near') {
                    $clocks->whereNull('c.breached_at')->where('c.state', 'running')->where('c.due_at', '>=', $now)->where('c.due_at', '<=', $now->addMinutes(30));
                } else {
                    $clocks->where(fn ($q) => $q->whereNotNull('c.breached_at')->orWhere(fn ($q) => $q->where('c.state', 'running')->whereNotNull('c.due_at')->where('c.due_at', '<', $now)));
                }
                $query->whereIn('requests.id', $clocks->select('c.request_id'));
            }
        }
        return $query;
    }

    public function build(User $user, array $filters): array
    {
        $zone = 'Asia/Jakarta';
        $start = CarbonImmutable::parse($filters['from'], $zone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($filters['to'], $zone)->addDay()->startOfDay()->utc();
        // Eloquent ticket/event timestamps use the application timezone; SLA engine clocks use UTC.
        $appZone = config('app.timezone', 'Asia/Jakarta');
        $ticketStart = $start->setTimezone($appZone);
        $ticketEnd = $end->setTimezone($appZone);
        $now = CarbonImmutable::now('UTC');
        $ids = $this->scope($user, $filters)->select('requests.id');
        $details = fn () => DB::table('it_ticket_details as d')->join('requests as r', 'r.id', '=', 'd.request_id')->whereIn('r.id', clone $ids);
        $incoming = fn () => $details()->where('r.created_at', '>=', $ticketStart)->where('r.created_at', '<', $ticketEnd);
        $backlog = fn () => $details()->whereNotIn('d.status', self::TERMINAL);
        $resolved = fn () => DB::table('it_ticket_events as e')->whereIn('e.request_id', clone $ids)->where('e.action', 'resolve')->where('e.created_at', '>=', $ticketStart)->where('e.created_at', '<', $ticketEnd);
        $active = DB::table('it_sla_clocks as c')->join('it_ticket_details as d', 'd.request_id', '=', 'c.request_id')->whereIn('c.request_id', clone $ids)->whereColumn('c.cycle', 'd.sla_cycle')->whereNotIn('d.status', self::TERMINAL)->whereIn('c.state', ['running', 'paused']);
        $breached = (clone $active)->where(fn ($q) => $q->whereNotNull('c.breached_at')->orWhere(fn ($q) => $q->where('c.state', 'running')->whereNotNull('c.due_at')->where('c.due_at', '<', $now)))->distinct()->count('c.request_id');
        $near = (clone $active)->whereNull('c.breached_at')->where('c.state', 'running')->where('c.due_at', '>=', $now)->where('c.due_at', '<=', $now->addMinutes(30))->distinct()->count('c.request_id');
        $clocks = DB::table('it_sla_clocks as c')->whereIn('c.request_id', clone $ids)->where('c.state', 'completed')->where('c.completed_at', '>=', $start)->where('c.completed_at', '<', $end)
            ->selectRaw('c.kind, COUNT(*) as total, SUM(CASE WHEN c.breached_at IS NULL AND c.consumed_seconds + c.carried_seconds <= c.target_seconds THEN 1 ELSE 0 END) as met')->groupBy('c.kind')->get()->keyBy('kind');
        $sla = [];
        foreach (['response', 'restore', 'fulfil'] as $kind) {
            $total = (int) ($clocks[$kind]->total ?? 0);
            $met = (int) ($clocks[$kind]->met ?? 0);
            $sla[$kind] = ['total' => $total, 'met' => $met, 'percent' => $total ? round(100 * $met / $total, 1) : null];
        }
        $types = $incoming()->selectRaw('d.record_type, COUNT(*) as total')->groupBy('d.record_type')->pluck('total', 'record_type');
        $priorities = $incoming()->selectRaw('d.priority, COUNT(*) as total')->groupBy('d.priority')->pluck('total', 'priority');
        $categoryGroups = $incoming()->selectRaw('d.configuration_version_id, d.category_key, COUNT(*) as total')->groupBy('d.configuration_version_id', 'd.category_key')->get();
        $versions = DB::table('it_configuration_versions')->whereIn('id', $categoryGroups->pluck('configuration_version_id')->filter()->unique())->pluck('configuration', 'id');
        $categories = [];
        foreach ($categoryGroups as $group) {
            $configuration = json_decode($versions[$group->configuration_version_id] ?? '[]', true) ?: [];
            $category = collect($configuration['categories'] ?? [])->firstWhere('key', $group->category_key);
            $label = $category['name'] ?? $group->category_key ?? 'Belum dikategorikan';
            $categories[$label] = ($categories[$label] ?? 0) + (int) $group->total;
        }
        arsort($categories);
        $categories = array_slice($categories, 0, 6, true);
        $workload = $backlog()->leftJoin('support_teams as team', 'team.id', '=', 'd.support_team_id')->selectRaw('team.name, COUNT(*) as total')->groupBy('team.name')->orderByDesc('total')->limit(6)->get();
        $ageNow = $now->setTimezone($appZone);
        $age = $backlog()->selectRaw('SUM(CASE WHEN r.created_at >= ? THEN 1 ELSE 0 END) as day_1, SUM(CASE WHEN r.created_at < ? AND r.created_at >= ? THEN 1 ELSE 0 END) as day_3, SUM(CASE WHEN r.created_at < ? AND r.created_at >= ? THEN 1 ELSE 0 END) as day_7, SUM(CASE WHEN r.created_at < ? THEN 1 ELSE 0 END) as older', [$ageNow->subDay(), $ageNow->subDay(), $ageNow->subDays(3), $ageNow->subDays(3), $ageNow->subDays(7), $ageNow->subDays(7)])->first();
        $trend = [];
        for ($day = CarbonImmutable::parse($filters['from'], $zone); $day->lte(CarbonImmutable::parse($filters['to'], $zone)); $day = $day->addDay()) {
            $trend[$day->toDateString()] = ['date' => $day->toDateString(), 'incoming' => 0, 'resolved' => 0];
        }
        // Group by timestamp in SQL to avoid loading ticket bodies. Use the first resolution per ticket in the period.
        foreach ($incoming()->selectRaw('r.created_at as at, COUNT(*) as total')->groupBy('r.created_at')->get() as $row) {
            $key = CarbonImmutable::parse($row->at, $appZone)->setTimezone($zone)->toDateString();
            if (isset($trend[$key])) $trend[$key]['incoming'] += (int) $row->total;
        }
        foreach ($resolved()->selectRaw('e.request_id, MIN(e.created_at) as at')->groupBy('e.request_id')->get() as $row) {
            $key = CarbonImmutable::parse($row->at, $appZone)->setTimezone($zone)->toDateString();
            if (isset($trend[$key])) $trend[$key]['resolved']++;
        }
        $recent = $this->scope($user, $filters)->where('requests.created_at', '>=', $ticketStart)->where('requests.created_at', '<', $ticketEnd)->with(['itDetail', 'assignedUser'])->orderByDesc('requests.created_at')->limit(8)->get();
        $teamFilters = $filters;
        unset($teamFilters['support_team_id']);
        $teamIds = $this->scope($user, $teamFilters)->select('requests.id');
        $teams = DB::table('support_teams as t')->whereIn('t.id', DB::table('it_ticket_details')->whereIn('request_id', $teamIds)->whereNotNull('support_team_id')->select('support_team_id'))->orderBy('t.name')->get(['t.id', 't.name']);
        // Choices come only from readable tickets and do not reveal otherwise inaccessible people.
        $allIds = $this->scope($user, [])->select('requests.id');
        $people = DB::table('users')->whereIn('id', DB::table('requests')->whereIn('id', clone $allIds)->whereNotNull('assigned_user_id')->select('assigned_user_id'))->orderBy('name')->get(['id', 'name']);
        $dimensionRows = DB::table('it_ticket_details')->whereIn('request_id', clone $allIds)->select('configuration_version_id', 'service_key', 'category_key')->distinct()->get();
        $dimensionVersions = DB::table('it_configuration_versions')->whereIn('id', $dimensionRows->pluck('configuration_version_id')->filter()->unique())->pluck('configuration', 'id');
        $services = []; $categoryChoices = [];
        foreach ($dimensionRows as $row) {
            $config = json_decode($dimensionVersions[$row->configuration_version_id] ?? '[]', true) ?: [];
            foreach ([['services', 'service_key'], ['categories', 'category_key']] as [$section, $key]) {
                if (! $row->$key) continue;
                $item = collect($config[$section] ?? [])->firstWhere('key', $row->$key);
                if ($section === 'services') $services[$row->$key] = $item['name'] ?? $row->$key;
                else $categoryChoices[$row->$key] = $item['name'] ?? $row->$key;
            }
        }
        asort($services); asort($categoryChoices);
        $days = CarbonImmutable::parse($filters['from'], $zone)->diffInDays(CarbonImmutable::parse($filters['to'], $zone)) + 1;
        $previousStart = $ticketStart->subDays((int) $days);
        $previousIncoming = $details()->where('r.created_at', '>=', $previousStart)->where('r.created_at', '<', $ticketStart)->count();
        $previousResolved = DB::table('it_ticket_events as e')->whereIn('e.request_id', clone $ids)->where('e.action', 'resolve')->where('e.created_at', '>=', $previousStart)->where('e.created_at', '<', $ticketStart)->distinct()->count('e.request_id');

        return ['filters' => $filters, 'updated_at' => $now->setTimezone($zone)->format('d M Y, H:i'), 'incoming' => $incoming()->count(), 'resolved' => $resolved()->distinct()->count('e.request_id'), 'backlog' => $backlog()->count(), 'near' => $near, 'breached' => $breached, 'sla' => $sla, 'types' => $types, 'priorities' => $priorities, 'categories' => $categories, 'workload' => $workload, 'age' => $age, 'trend' => array_values($trend), 'recent' => $recent, 'teams' => $teams, 'people' => $people, 'services' => $services, 'categoryChoices' => $categoryChoices, 'previous' => ['from' => $previousStart->setTimezone($zone)->toDateString(), 'to' => $ticketStart->setTimezone($zone)->subDay()->toDateString(), 'incoming' => $previousIncoming, 'resolved' => $previousResolved]];
    }
}
