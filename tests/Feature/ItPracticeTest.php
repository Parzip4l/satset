<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItPracticeTest extends TestCase
{
    use \Tests\Support\BuildsItWorkflow { setUp as workflowSetUp; }

    protected function setUp(): void
    {
        $this->workflowSetUp();
        (require database_path('migrations/2026_10_28_000006_create_it_practice_foundation.php'))->up();
        $this->grant($this->technician, 'manage_it_practices');
        $this->grant($this->other, 'manage_it_practices');
        $this->grant($this->other, 'authorize_it_change');
    }

    private function record(string $type = 'problem'): array
    {
        return $this->actingAs($this->technician)->postJson(route('it.practices.store'), ['type' => $type, 'title' => 'Masalah jaringan', 'description' => 'Pengujian praktik IT', 'support_team_id' => $this->team, 'owner_id' => $this->technician->id, 'is_restricted' => false, 'reason' => 'Catatan pengujian'])->assertCreated()->json('record');
    }

    private function action(array $r, string $action, array $values = [], ?\App\Models\User $actor = null): array
    {
        return $this->actingAs($actor ?? $this->technician)->postJson(route('it.practices.action', [$r['id'], $action]), ['lock_version' => $r['lock_version'], 'reason' => 'Pembaruan yang diuji'] + $values)->assertOk()->json('record');
    }

    public function test_problem_investigation_does_not_block_incident_restoration(): void
    {
        $ticket = $this->ticket();
        $problem = $this->record();
        $problem = $this->action($problem, 'link_ticket', ['request_id' => $ticket->id]);
        $ticket = $this->act($ticket, 'claim');
        $ticket = $this->act($ticket, 'start');
        $ticket = $this->act($ticket, 'resolve');
        $ticket = $this->act($ticket, 'confirm', [], $this->requester);
        $this->assertSame('closed', $ticket->itDetail->status);
        $this->assertSame('identified', $problem['status']);
        $problem = $this->action($problem, 'transition', ['status' => 'assessment']);
        $problem = $this->action($problem, 'transition', ['status' => 'investigation']);
        $this->actingAs($this->technician)->postJson(route('it.practices.action', [$problem['id'], 'transition']), ['lock_version' => $problem['lock_version'], 'reason' => 'Known error tanpa workaround', 'status' => 'known_error'])->assertStatus(422);
        $problem = $this->action($problem, 'update', ['workaround' => 'Mulai ulang akses poin', 'root_cause' => 'Konfigurasi gagal', 'prevention' => 'Perbarui konfigurasi']);
        $problem = $this->action($problem, 'transition', ['status' => 'known_error']);
        $this->assertSame('known_error', $problem['status']);
    }

    public function test_change_requires_separate_authority_result_and_review(): void
    {
        $p = $this->record();
        $c = $this->record('change');
        $c = $this->action($c, 'link_problem', ['problem_id' => $p['id']]);
        $c = $this->action($c, 'update', ['change_type' => 'normal', 'risk' => 'medium', 'implementation_plan' => 'Perbarui konfigurasi', 'test_plan' => 'Verifikasi koneksi', 'rollback_plan' => 'Kembalikan konfigurasi', 'authority_id' => $this->other->id, 'scheduled_start' => '2030-01-01 08:00', 'scheduled_end' => '2030-01-01 09:00']);
        $c = $this->action($c, 'transition', ['status' => 'assessment']);
        $this->actingAs($this->technician)->postJson(route('it.practices.action', [$c['id'], 'transition']), ['lock_version' => $c['lock_version'], 'reason' => 'Tidak boleh otorisasi sendiri', 'status' => 'authorized'])->assertForbidden();
        $c = $this->action($c, 'transition', ['status' => 'authorized'], $this->other);
        $this->actingAs($this->technician)->postJson(route('it.practices.action', [$c['id'], 'update']), ['lock_version' => $c['lock_version'], 'reason' => 'Ubah setelah otorisasi'])->assertStatus(409);
        $c = $this->action($c, 'transition', ['status' => 'scheduled']);
        $c = $this->action($c, 'transition', ['status' => 'implementing']);
        $c = $this->action($c, 'transition', ['status' => 'review', 'result' => 'rolled_back', 'test_result' => 'Verifikasi gagal', 'actual_result' => 'Konfigurasi dikembalikan']);
        $c = $this->action($c, 'transition', ['status' => 'closed', 'review' => 'Review lengkap atas rollback']);
        $this->assertSame('closed', $c['status']);
        $this->assertSame('rolled_back', json_decode($c['data'], true)['result']);
        $links = $this->actingAs($this->technician)->getJson(route('it.practices.show', $p['id']))->assertOk()->json('links');
        $this->assertCount(1, $links);
    }

    public function test_configuration_admin_and_outsiders_do_not_gain_operational_access(): void
    {
        $record = $this->record();
        $this->actingAs($this->manager)->getJson(route('it.practices.show', $record['id']))->assertForbidden();
        $this->actingAs($this->requester)->getJson(route('it.practices.show', $record['id']))->assertForbidden();
        $this->actingAs($this->technician)->get(route('it.practices.index'))->assertOk();
        $this->get(route('it.practices.show', $record['id']))->assertOk();
    }

    public function test_restricted_incident_cannot_link_to_unrestricted_problem(): void
    {
        $t = $this->ticket();
        $this->grant($this->technician, 'view_restricted_it');
        $t = $this->act($t, 'restrict', ['is_restricted' => true]);
        $p = $this->record();
        $this->actingAs($this->technician)->postJson(route('it.practices.action', [$p['id'], 'link_ticket']), ['lock_version' => 1, 'reason' => 'Jaga kerahasiaan incident', 'request_id' => $t->id])->assertStatus(422);
    }

    public function test_concurrent_edit_stale_version_is_rejected(): void
    {
        $p = $this->record();
        $this->action($p, 'transition', ['status' => 'assessment']);
        $this->actingAs($this->other)->postJson(route('it.practices.action', [$p['id'], 'transition']), ['lock_version' => 1, 'reason' => 'Versi sudah usang', 'status' => 'assessment'])->assertStatus(409);
        $this->assertSame(2, DB::table('it_practice_events')->where('record_id', $p['id'])->count());
    }

    public function test_incident_problem_change_review_and_published_knowledge_chain_is_traceable(): void
    {
        $this->grant($this->other, 'publish_it_knowledge');
        $ticket = $this->ticket();
        $problem = $this->record();
        $problem = $this->action($problem, 'link_ticket', ['request_id' => $ticket->id]);
        $change = $this->record('change');
        $change = $this->action($change, 'link_problem', ['problem_id' => $problem['id']]);
        $change = $this->action($change, 'update', ['change_type' => 'emergency', 'risk' => 'high', 'implementation_plan' => 'Pulihkan koneksi jaringan', 'test_plan' => 'Uji akses jaringan', 'rollback_plan' => 'Kembalikan perubahan', 'authority_id' => $this->other->id, 'scheduled_start' => '2030-01-01 08:00', 'scheduled_end' => '2030-01-01 09:00']);
        $change = $this->action($change, 'transition', ['status' => 'assessment']);
        $change = $this->action($change, 'transition', ['status' => 'authorized'], $this->other);
        foreach (['scheduled', 'implementing'] as $status) {
            $change = $this->action($change, 'transition', ['status' => $status]);
        }
        $change = $this->action($change, 'transition', ['status' => 'review', 'result' => 'success', 'test_result' => 'Koneksi berhasil diuji', 'actual_result' => 'Layanan jaringan pulih']);
        $change = $this->action($change, 'transition', ['status' => 'closed', 'review' => 'Review perbaikan selesai']);
        $article = $this->record('knowledge');
        $article = $this->action($article, 'update', ['article' => 'Panduan pemulihan koneksi jaringan yang sudah divalidasi.', 'review_date' => '2030-01-01']);
        $article = $this->action($article, 'link_change', ['change_id' => $change['id']]);
        $article = $this->action($article, 'transition', ['status' => 'review']);
        $this->actingAs($this->technician)->postJson(route('it.practices.action', [$article['id'], 'transition']), ['lock_version' => $article['lock_version'], 'status' => 'published', 'reason' => 'Tidak boleh publikasi sendiri'])->assertForbidden();
        $article = $this->action($article, 'transition', ['status' => 'published'], $this->other);
        $this->assertSame('published', $article['status']);
        $this->assertCount(1, $this->actingAs($this->technician)->getJson(route('it.practices.show', $article['id']))->json('links'));
        $this->assertSame('queued', $ticket->fresh()->itDetail->status);
    }

    public function test_asset_register_links_to_problem_and_requires_active_service_and_unique_tag(): void
    {
        $asset = $this->record('asset');
        $this->actingAs($this->technician)->postJson(route('it.practices.action', [$asset['id'], 'update']), ['lock_version' => 1, 'reason' => 'Simpan data aset', 'asset_tag' => 'LRTJ-001', 'ci_type' => 'network', 'service_key' => 'unknown'])->assertStatus(422);
        $asset = $this->action($asset, 'update', ['asset_tag' => 'LRTJ-001', 'ci_type' => 'network', 'service_key' => 'connectivity', 'location' => 'Kantor']);
        $asset = $this->action($asset, 'transition', ['status' => 'active']);
        $this->actingAs($this->technician)->get(route('it.practices.show', $asset['id']))->assertOk();
        $problem = $this->record();
        $problem = $this->action($problem, 'link_asset', ['asset_id' => $asset['id']]);
        $this->assertCount(1, $this->actingAs($this->technician)->getJson(route('it.practices.show', $problem['id']))->assertOk()->json('links'));
        $duplicate = $this->record('asset');
        $this->postJson(route('it.practices.action', [$duplicate['id'], 'update']), ['lock_version' => 1, 'reason' => 'Kode tidak boleh duplikat', 'asset_tag' => 'LRTJ-001', 'ci_type' => 'device', 'service_key' => 'workstation'])->assertStatus(409);
    }
}
