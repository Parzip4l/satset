<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Services\It\PracticeService;
use App\Support\ItAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PracticeController extends Controller
{
    private function check(Request $request): void
    {
        abort_unless(Schema::hasTable('it_practice_records'), 503, 'Fondasi Problem dan Change belum diterapkan.');
        abort_unless(ItAccess::operational($request->user()), 403);
    }

    private function json(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    public function index(Request $request, PracticeService $service)
    {
        $this->check($request);
        $request->validate(['type' => 'nullable|in:problem,change,knowledge,asset']);
        $user = $request->user();
        $teams = \App\Support\AccountAccess::isSuperadmin($user)
            ? DB::table('support_teams')->where('is_active', true)->pluck('id')
            : DB::table('support_team_members')->join('support_teams', 'support_teams.id', '=', 'support_team_members.support_team_id')
                ->where('user_id', $user->id)->where('is_active', true)->whereIn('role', ['technician', 'coordinator'])->pluck('support_teams.id');
        $query = DB::table('it_practice_records')->whereIn('support_team_id', $teams);
        if (! ItAccess::can($user, 'view_restricted_it')) {
            $query->where('is_restricted', false);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $records = $query->orderByDesc('id')->paginate(20)->withQueryString();
        if ($this->json($request)) {
            return response()->json($records);
        }
        $teams = DB::table('support_teams')->whereIn('id', $teams)->orderBy('name')->get();
        $members = DB::table('support_team_members')->join('users', 'users.id', '=', 'support_team_members.user_id')->whereIn('support_team_id', $teams->pluck('id'))->get(['users.id', 'users.name', 'support_team_id']);

        return view('it.practices.index', compact('records', 'teams', 'members'));
    }

    public function store(Request $request, PracticeService $service)
    {
        $this->check($request);
        $record = $service->create($request->user(), $request->all());

        return $this->json($request) ? response()->json($service->view($request->user(), $record->id), 201) : redirect()->route('it.practices.show', $record->id)->with('success', 'Catatan dibuat.');
    }

    public function show(Request $request, int $record, PracticeService $service)
    {
        $this->check($request);
        $detail = $service->view($request->user(), $record);
        if ($this->json($request)) {
            return response()->json($detail);
        }

        return view('it.practices.show', compact('detail'));
    }

    public function action(Request $request, int $record, string $action, PracticeService $service)
    {
        $this->check($request);
        $result = $service->action($request->user(), $record, $action, $request->all());

        return $this->json($request) ? response()->json($service->view($request->user(), $result->id)) : redirect()->route('it.practices.show', $record)->with('success', 'Perubahan dicatat.');
    }
}
