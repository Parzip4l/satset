<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Support\ItAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function index(Request $r)
    {
        abort_unless(ItAccess::can($r->user(), 'manage_it_configuration'), 403);
        $teams = DB::table('support_teams')->orderBy('name')->get();
        $members = DB::table('support_team_members')->get();
        $users = DB::table('users')->orderBy('name')->get(['id', 'name']);

        return view('it.configuration.teams', compact('teams', 'members', 'users'));
    }

    public function store(Request $r)
    {
        abort_unless(ItAccess::can($r->user(), 'manage_it_configuration'), 403);
        $data = $r->validate(['code' => 'required|string|regex:/^[a-z][a-z0-9_-]{0,49}$/|unique:support_teams,code', 'name' => 'required|string|max:100', 'reason' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($r, $data) {
            $id = DB::table('support_teams')->insertGetId(['code' => $data['code'], 'name' => $data['name'], 'is_active' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('it_team_events')->insert(['actor_id' => $r->user()->id, 'support_team_id' => $id, 'reason' => $data['reason'], 'before_state' => '{}',
                'after_state' => json_encode(['name' => $data['name'], 'code' => $data['code'], 'is_active' => false, 'members' => []]), 'created_at' => now()]);
        });

        return $r->expectsJson() ? response()->json(['created' => true], 201) : back()->with('success', 'Tim baru dibuat dalam status draft.');
    }

    public function update(Request $r, int $team)
    {
        abort_unless(ItAccess::can($r->user(), 'manage_it_configuration'), 403);
        if ($r->has('roles')) {
            $roles = $r->validate(['roles' => 'array|max:500', 'roles.*' => 'nullable|in:technician,coordinator'])['roles'];
            $r->merge(['members' => collect($roles)->filter()->map(fn ($role, $id) => ['user_id' => $id, 'role' => $role])->values()->all()]);
        }
        $data = $r->validate(['name' => 'required|string|max:100', 'is_active' => 'required|boolean', 'members' => 'sometimes|array|max:100',
            'members.*' => 'array:user_id,role', 'members.*.user_id' => 'required|integer|distinct|exists:users,id', 'members.*.role' => 'required|in:technician,coordinator',
            'reason' => 'required|string|min:5|max:1000', 'fingerprint' => 'required|string|size:64']);
        DB::transaction(function () use ($r, $team, $data) {
            $before = DB::table('support_teams')->where('id', $team)->lockForUpdate()->first();
            abort_unless($before, 404);
            $old = DB::table('support_team_members')->where('support_team_id', $team)->orderBy('user_id')->get(['user_id', 'role'])->map(fn ($m) => (array) $m)->all();
            $snapshot = ['name' => $before->name, 'is_active' => (bool) $before->is_active, 'members' => $old];
            abort_unless(hash_equals(\App\Services\It\ConfigurationStore::checksum($snapshot), $data['fingerprint']), 409, 'Tim berubah. Muat ulang.');
            $members = $data['members'] ?? [];
            abort_if($data['is_active'] && ! count($members), 422, 'Tim aktif membutuhkan anggota.');
            DB::table('support_teams')->where('id', $team)->update(['name' => $data['name'], 'is_active' => $data['is_active'], 'updated_at' => now()]);
            DB::table('support_team_members')->where('support_team_id', $team)->delete();
            foreach ($members as $m) {
                DB::table('support_team_members')->insert($m + ['support_team_id' => $team, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('it_team_events')->insert(['actor_id' => $r->user()->id, 'support_team_id' => $team, 'reason' => $data['reason'], 'before_state' => json_encode($snapshot), 'after_state' => json_encode(['name' => $data['name'], 'is_active' => (bool) $data['is_active'], 'members' => $members]), 'created_at' => now()]);
        });

        return $r->expectsJson() ? response()->json(['updated' => true]) : back()->with('success', 'Tim IT diperbarui dan dicatat dalam audit.');
    }
}
