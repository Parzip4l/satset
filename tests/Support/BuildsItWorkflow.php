<?php

namespace Tests\Support;

use App\Models\It\ConfigurationVersion;
use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\It\ConfigurationStore;
use App\Services\It\OperationalProfile;
use App\Services\It\TicketWorkflow;
use Database\Seeders\ItConfigurationSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait BuildsItWorkflow
{
    protected User $requester;

    protected User $technician;

    protected User $other;

    protected User $manager;

    protected User $approver;

    protected int $team;

    protected ConfigurationVersion $version;

    protected TicketWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->string('role')->nullable();
            $t->timestamps();
        });
        Schema::create('problem_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name');
        });
        Schema::create('statuses', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name');
        });
        Schema::create('requests', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_no')->unique();
            $t->uuid('submission_token')->nullable()->unique();
            $t->foreignId('requester_id');
            $t->foreignId('category_id');
            $t->foreignId('status_id');
            $t->foreignId('assigned_user_id')->nullable();
            $t->string('title');
            $t->text('description');
            $t->json('payload')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id');
            $t->foreignId('approver_id');
        });
        Schema::create('menus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable();
            $t->boolean('is_active');
            $t->integer('order');
        });
        foreach (['000001_create_it_service_foundation', '000003_create_it_configuration_versions', '000004_create_it_workflow'] as $file) {
            (require database_path('migrations/2026_10_28_'.$file.'.php'))->up();
        }
        DB::table('service_domains')->where('code', 'it')->update(['is_active' => true]);
        DB::table('problem_categories')->insert(['code' => 'IT', 'name' => 'IT']);
        DB::table('statuses')->insert([['code' => 'open', 'name' => 'Open'], ['code' => 'progress', 'name' => 'In Progress'], ['code' => 'resolved', 'name' => 'Resolved'], ['code' => 'closed', 'name' => 'Closed']]);
        $this->requester = $this->user('Pemohon');
        $this->technician = $this->user('Teknisi');
        $this->other = $this->user('Teknisi kedua');
        $this->manager = $this->user('Konfigurator');
        $this->approver = $this->user('Approver');
        $this->grant($this->manager, 'manage_it_configuration');
        $this->grant($this->manager, 'publish_it_configuration');
        $this->team = DB::table('support_teams')->insertGetId(['code' => 'desk', 'name' => 'Service Desk', 'is_active' => true]);
        foreach ([$this->technician, $this->other] as $u) {
            DB::table('support_team_members')->insert(['support_team_id' => $this->team, 'user_id' => $u->id, 'role' => $u->id === $this->technician->id ? 'coordinator' : 'technician']);
        }
        $this->seed(ItConfigurationSeeder::class);
        $v = ConfigurationVersion::firstOrFail();
        $cfg = $v->configuration;
        $cfg['schema_version'] = 2;
        $cfg['profiles'] = OperationalProfile::defaults();
        foreach ($cfg['services'] as &$s) {
            $s['owner_id'] = $this->manager->id;
            $s['fallback_team_id'] = $this->team;
        }unset($s);
        $store = app(ConfigurationStore::class);
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Siapkan workflow pengujian');
        $this->version = $store->activate($v->id, 0, $v->lock_version, $this->manager, 'Publikasi untuk pengujian');
        config(['satset.it.requests_enabled' => true, 'satset.it.pilot.required' => false]);
        Storage::fake('it_private');
        $this->workflow = app(TicketWorkflow::class);
    }

    protected function user(string $name): User
    {
        return User::create(['name' => $name, 'email' => Str::random(10).'@example.test', 'password' => 'password', 'role' => 'admin']);
    }

    protected function grant(User $u, string $cap): void
    {
        DB::table('it_user_capabilities')->insert(['user_id' => $u->id, 'capability' => $cap]);
    }

    protected function input(string $type = 'incident', array $overrides = []): array
    {
        $catalog = collect($this->version->configuration['catalog'])->firstWhere('record_type', $type);
        $answers = ['subject' => 'Gangguan Wi-Fi', 'details' => 'Tidak dapat mengakses jaringan', 'connection' => 'wifi', 'ssid' => 'LRTJ'];
        if ($type === 'service_request') {
            $answers = ['subject' => 'Pembuatan akun', 'details' => 'Akun untuk pegawai', 'business_reason' => 'Kebutuhan pekerjaan'];
        }

        return array_replace(['submission_token' => (string) Str::uuid(), 'catalog_key' => $catalog['key'], 'title' => $answers['subject'], 'description' => $answers['details'], 'impact' => 'medium', 'urgency' => 'high', 'answers' => $answers], $overrides);
    }

    protected function ticket(string $type = 'incident', array $overrides = []): Ticket
    {
        return $this->workflow->submit($this->requester, $this->input($type, $overrides));
    }

    protected function act(Ticket $t, string $action, array $data = [], ?User $actor = null): Ticket
    {
        return $this->workflow->action($actor ?? $this->technician, $t, $action, ['lock_version' => $t->fresh()->itDetail->lock_version, 'reason' => 'Keterangan untuk pengujian'] + $data);
    }

    protected function approvalRule(array $rule = []): void
    {
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Tambahkan aturan approval');
        $cfg = $v->configuration;
        $cfg['profiles']['approval'] = [array_replace(['key' => 'manager', 'record_type' => 'service_request', 'resolver' => 'user', 'approver_id' => $this->approver->id], $rule)];
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Atur approval bersyarat');
        $rev = DB::table('it_configuration_sets')->value('revision');
        $this->version = $store->activate($v->id, $rev, $v->lock_version, $this->manager, 'Publikasikan approval');
    }
}
