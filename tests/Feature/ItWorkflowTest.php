<?php

namespace Tests\Feature;

use App\Models\Master\Ticket;
use App\Services\It\ConfigurationStore;
use App\Services\LrtjSpaceApprovalResolverService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItWorkflowTest extends TestCase
{
    use \Tests\Support\BuildsItWorkflow;

    public function test_submission_gate_and_legacy_ticket_writes_remain_closed(): void
    {
        config(['satset.it.requests_enabled' => false]);
        $this->actingAs($this->requester)->postJson(route('it.store'), $this->input())->assertStatus(423);
        config(['satset.it.requests_enabled' => true]);
        $t = $this->ticket();
        $t->itDetail->forceFill(['catalog_key' => null])->save();
        $this->actingAs($this->technician)->postJson(route('it.action', [$t, 'claim']), ['lock_version' => 1])->assertStatus(409);
    }

    public function test_incident_runs_from_submit_through_tasks_pending_resolution_confirmation_and_reopen(): void
    {
        $t = $this->ticket();
        $this->assertSame('queued', $t->itDetail->status);
        $this->assertSame('P2', $t->itDetail->priority);
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $t = $this->act($t, 'task', ['title' => 'Periksa akses poin', 'assigned_to' => $this->technician->id, 'required' => true]);
        $this->actingAs($this->technician)->postJson(route('it.action', [$t, 'resolve']), ['lock_version' => $t->itDetail->lock_version, 'reason' => 'Sudah dipulihkan'])->assertStatus(409);
        $t = $this->act($t, 'complete_task', ['task_id' => DB::table('it_ticket_tasks')->value('id')]);
        $t = $this->act($t, 'pending', ['next_update_at' => now()->addHour()->toDateTimeString()]);
        $this->assertNotNull($t->itDetail->pending_reason);
        $t = $this->act($t, 'resume');
        $t = $this->act($t, 'resolve');
        $t = $this->act($t, 'confirm', [], $this->requester);
        $this->assertSame('closed', $t->itDetail->status);
        $this->assertNotNull($t->closed_at);
        $t = $this->act($t, 'reopen', [], $this->requester);
        $this->assertSame('queued', $t->itDetail->status);
        $this->assertNull($t->closed_at);
        $this->assertSame($t->itDetail->lock_version, DB::table('it_ticket_events')->where('request_id', $t->id)->count());
    }

    public function test_configuration_capabilities_never_give_ticket_access_or_claim_rights(): void
    {
        $t = $this->ticket();
        $this->actingAs($this->manager)->getJson(route('it.show', $t))->assertForbidden();
        $this->postJson(route('it.action', [$t, 'claim']), ['lock_version' => 1])->assertForbidden();
    }

    public function test_only_one_claim_succeeds_with_same_version_and_assignment_is_not_overwritten(): void
    {
        $t = $this->ticket();
        $this->actingAs($this->technician)->postJson(route('it.action', [$t, 'claim']), ['lock_version' => 1])->assertOk();
        $this->actingAs($this->other)->postJson(route('it.action', [$t, 'claim']), ['lock_version' => 1])->assertStatus(409);
        $this->postJson(route('it.action', [$t, 'claim']), ['lock_version' => 2])->assertStatus(409);
        $this->assertSame($this->technician->id, $t->fresh()->assigned_user_id);
    }

    public function test_submission_token_retries_are_idempotent_and_different_content_conflicts(): void
    {
        $data = $this->input();
        $t = $this->workflow->submit($this->requester, $data);
        $again = $this->workflow->submit($this->requester, $data);
        $this->assertSame($t->id, $again->id);
        $this->assertSame(1, Ticket::count());
        $this->assertSame(1, DB::table('it_ticket_events')->count());
        $this->actingAs($this->requester)->postJson(route('it.store'), array_replace($data, ['title' => 'Isi berubah']))->assertStatus(409);
        $this->actingAs($this->other)->postJson(route('it.store'), $data)->assertStatus(409);
    }

    public function test_hidden_fields_are_rejected_and_uploads_are_private_and_authorized(): void
    {
        $data = $this->input();
        $data['answers']['internal_note'] = 'Terlarang';
        $this->actingAs($this->requester)->postJson(route('it.store'), $data)->assertUnprocessable();
        $t = $this->workflow->submit($this->requester, $this->input(), ['evidence' => [UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')]]);
        $a = DB::table('it_ticket_attachments')->first();
        Storage::disk('it_private')->assertExists($a->path);
        $view = $this->workflow->view($this->requester, $t);
        $this->assertStringNotContainsString($a->path, json_encode($view));
        $this->actingAs($this->requester)->get(route('it.attachment', [$t, $a->id]))->assertOk();
        $this->actingAs($this->manager)->get(route('it.attachment', [$t, $a->id]))->assertForbidden();
    }

    public function test_internal_comment_and_attachment_are_hidden_from_requester_and_approver(): void
    {
        $t = $this->ticket();
        $t = $this->act($t, 'comment', ['is_internal' => true, 'reason' => 'Rahasia internal teknisi']);
        $this->assertCount(0, $this->workflow->view($this->requester, $t)['comments']);
        $this->assertCount(1, $this->workflow->view($this->technician, $t)['comments']);
        $this->actingAs($this->requester)->postJson(route('it.action', [$t, 'comment']), ['lock_version' => $t->itDetail->lock_version, 'reason' => 'Mencoba internal', 'is_internal' => true])->assertForbidden();
        $t = $this->workflow->action($this->technician, $t, 'attachment', ['lock_version' => $t->itDetail->lock_version, 'is_internal' => true], ['attachments' => [UploadedFile::fake()->create('internal.pdf', 10, 'application/pdf')]]);
        $id = DB::table('it_ticket_attachments')->value('id');
        $this->actingAs($this->requester)->get(route('it.attachment', [$t, $id]))->assertNotFound();
        $this->assertCount(0, $this->workflow->view($this->requester, $t)['attachments']);
        $this->assertFalse(DB::table('it_ticket_notifications')->where('kind', 'internal')->where('user_id', $this->requester->id)->exists());
    }

    public function test_approval_is_required_before_work_and_decision_cannot_be_processed_twice(): void
    {
        $this->approvalRule();
        $t = $this->ticket('service_request');
        $this->assertSame('awaiting_approval', $t->itDetail->status);
        $id = DB::table('it_ticket_approvals')->value('id');
        $this->actingAs($this->technician)->postJson(route('it.action', [$t, 'claim']), ['lock_version' => 1])->assertStatus(409);
        $this->actingAs($this->approver)->getJson(route('it.show', $t))->assertForbidden();
        $this->getJson(route('it.approval', $id))->assertOk()->assertJsonMissingPath('comments')->assertJsonMissingPath('events');
        $body = ['lock_version' => 1, 'decision' => 'approved', 'reason' => 'Kebutuhan disetujui'];
        $this->postJson(route('it.decide', $id), $body)->assertOk();
        $this->postJson(route('it.decide', $id), $body)->assertStatus(409);
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $t = $this->act($t, 'resolve');
        $t = $this->act($t, 'confirm', [], $this->requester);
        $this->assertSame('closed', $t->itDetail->status);
    }

    public function test_revision_creates_fresh_approval_without_changing_pinned_configuration(): void
    {
        $this->approvalRule();
        $t = $this->ticket('service_request');
        $id = DB::table('it_ticket_approvals')->value('id');
        $version = $t->itDetail->configuration_version_id;
        $this->workflow->decide($this->approver, $id, ['lock_version' => 1, 'decision' => 'revision', 'reason' => 'Tambahkan kebutuhan bisnis']);
        $data = $this->input('service_request');
        $t = $this->act($t, 'revise', ['title' => 'Kebutuhan diperbarui', 'description' => 'Rincian telah diperbarui', 'answers' => $data['answers']], $this->requester);
        $this->assertSame(2, $t->itDetail->cycle);
        $this->assertSame('awaiting_approval', $t->itDetail->status);
        $this->assertSame($version, $t->itDetail->configuration_version_id);
        $this->assertSame(2, DB::table('it_ticket_approvals')->count());
        $this->actingAs($this->approver)->postJson(route('it.decide', $id), ['lock_version' => $t->itDetail->lock_version, 'decision' => 'approved', 'reason' => 'Tidak boleh ulang'])->assertStatus(409);
    }

    public function test_self_approval_and_failed_portal_resolver_enter_exception_without_auto_approval(): void
    {
        $this->approvalRule(['approver_id' => $this->requester->id]);
        $t = $this->ticket('service_request');
        $this->assertSame('approval_exception', $t->itDetail->status);
        $this->assertNull(DB::table('it_ticket_approvals')->value('approver_id'));
        $this->approvalRule(['resolver' => 'portal', 'approver_id' => null]);
        $this->mock(LrtjSpaceApprovalResolverService::class)->shouldReceive('resolveFirstApprover')->once()->andThrow(new \RuntimeException('Portal offline'));
        $t = $this->ticket('service_request');
        $this->assertSame('approval_exception', $t->itDetail->status);
        $this->mock(LrtjSpaceApprovalResolverService::class)->shouldReceive('resolveFirstApprover')->once()->andReturn($this->approver);
        $t = $this->act($t, 'retry_approval');
        $this->assertSame('awaiting_approval', $t->itDetail->status);
        $this->assertSame(2, $t->itDetail->cycle);
    }

    public function test_routing_is_ordered_fallback_is_safe_and_priority_uses_pinned_matrix(): void
    {
        $newTeam = DB::table('support_teams')->insertGetId(['code' => 'app', 'name' => 'Aplikasi', 'is_active' => true]);
        DB::table('support_team_members')->insert(['support_team_id' => $newTeam, 'user_id' => $this->other->id, 'role' => 'technician']);
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Uji urutan routing');
        $cfg = $v->configuration;
        $cfg['profiles']['routing'] = [['key' => 'first', 'record_type' => 'incident', 'team_id' => $newTeam], ['key' => 'second', 'record_type' => 'incident', 'team_id' => $this->team]];
        $cfg['profiles']['priority_matrix']['medium.high'] = 'P1';
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Simpan routing baru');
        $store->activate($v->id, 1, $v->lock_version, $this->manager, 'Publikasi routing baru');
        $t = $this->ticket();
        $this->assertSame($newTeam, $t->itDetail->support_team_id);
        $this->assertSame('P1', $t->itDetail->priority);
        DB::table('support_teams')->where('id', $newTeam)->update(['is_active' => false]);
        $t = $this->ticket();
        $this->assertSame($this->team, $t->itDetail->support_team_id);
        DB::table('support_teams')->where('id', $this->team)->update(['is_active' => false]);
        $t = $this->ticket();
        $this->assertSame('triage', $t->itDetail->status);
        $this->assertNull($t->itDetail->support_team_id);
    }

    public function test_transfer_clears_old_assignment_and_old_team_access(): void
    {
        $newTeam = DB::table('support_teams')->insertGetId(['code' => 'infra', 'name' => 'Infrastruktur', 'is_active' => true]);
        $newUser = $this->user('Infra');
        DB::table('support_team_members')->insert(['support_team_id' => $newTeam, 'user_id' => $newUser->id, 'role' => 'technician']);
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'transfer', ['team_id' => $newTeam]);
        $this->assertNull($t->assigned_user_id);
        $this->actingAs($this->technician)->getJson(route('it.show', $t))->assertForbidden();
        $this->actingAs($newUser)->getJson(route('it.show', $t))->assertOk();
    }

    public function test_restriction_requires_explicit_capability_and_cancel_blocks_further_handling(): void
    {
        $t = $this->ticket();
        $this->actingAs($this->technician)->postJson(route('it.action', [$t, 'restrict']), ['lock_version' => 1, 'reason' => 'Data sangat sensitif', 'is_restricted' => true])->assertForbidden();
        $this->grant($this->technician, 'view_restricted_it');
        $t = $this->act($t, 'restrict', ['is_restricted' => true]);
        $this->actingAs($this->other)->getJson(route('it.show', $t))->assertForbidden();
        $this->actingAs($this->requester)->getJson(route('it.show', $t))->assertOk();
        $t = $this->act($t, 'cancel', [], $this->requester);
        $this->assertSame('cancelled', $t->itDetail->status);
        $this->actingAs($this->technician)->postJson(route('it.action', [$t, 'claim']), ['lock_version' => $t->itDetail->lock_version])->assertStatus(409);
    }

    public function test_reopen_window_and_technician_cannot_confirm_on_behalf_of_requester(): void
    {
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'start');
        $t = $this->act($t, 'resolve');
        $this->actingAs($this->technician)->postJson(route('it.action', [$t, 'confirm']), ['lock_version' => $t->itDetail->lock_version])->assertForbidden();
        $t = $this->act($t, 'confirm', [], $this->requester);
        $this->travel(8)->days();
        $this->actingAs($this->requester)->postJson(route('it.action', [$t, 'reopen']), ['lock_version' => $t->itDetail->lock_version, 'reason' => 'Gangguan berulang'])->assertStatus(409);
    }

    public function test_runtime_views_render_and_team_edit_is_audited_and_optimistic(): void
    {
        $t = $this->ticket();
        $this->actingAs($this->requester)->get(route('it.show', $t))->assertOk()->assertSee('Tindak lanjut');
        $this->actingAs($this->technician)->get(route('it.show', $t))->assertOk()->assertSee('Riwayat penanganan');
        $this->get(route('it.index'))->assertOk();
        $this->get(route('it.notifications'))->assertOk();
        $this->actingAs($this->manager)->get(route('it.configuration.teams'))->assertOk();
        $fingerprint = ConfigurationStore::checksum(['name' => 'Service Desk', 'is_active' => true, 'members' => [['user_id' => $this->technician->id, 'role' => 'coordinator'], ['user_id' => $this->other->id, 'role' => 'technician']]]);
        $body = ['name' => 'Service Desk baru', 'is_active' => true, 'members' => [['user_id' => $this->technician->id, 'role' => 'coordinator']], 'reason' => 'Perubahan anggota tim', 'fingerprint' => $fingerprint];
        $this->putJson(route('it.configuration.teams.update', $this->team), $body)->assertOk();
        $this->putJson(route('it.configuration.teams.update', $this->team), $body)->assertStatus(409);
        $this->assertSame(1, DB::table('it_team_events')->count());
    }

    public function test_four_catalog_scenarios_reach_closed_with_incident_or_service_request_controls(): void
    {
        $this->approvalRule();
        foreach (['network_issue' => 'Gangguan Wi-Fi', 'application_issue' => 'Error aplikasi', 'access_request' => 'Pembuatan akun', 'software_request' => 'Instalasi software'] as $catalog => $title) {
            $type = in_array($catalog, ['network_issue', 'application_issue']) ? 'incident' : 'service_request';
            $t = $this->ticket($type, ['catalog_key' => $catalog, 'title' => $title]);
            if ($type === 'service_request') {
                $a = DB::table('it_ticket_approvals')->where('request_id', $t->id)->first();
                $this->workflow->decide($this->approver, $a->id, ['lock_version' => 1, 'decision' => 'approved', 'reason' => 'Kebutuhan disetujui atasan']);
            }
            $t = $this->act($t, 'claim');
            $t = $this->act($t, 'start');
            $t = $this->act($t, 'resolve');
            $t = $this->act($t, 'confirm', [], $this->requester);
            $this->assertSame('closed', $t->itDetail->status, $catalog);
            $this->assertSame($this->version->id, $t->itDetail->configuration_version_id);
        }
    }

    public function test_transferred_required_task_can_be_reassigned_and_completed_by_new_team(): void
    {
        $team = DB::table('support_teams')->insertGetId(['code' => 'new_team', 'name' => 'Tim baru', 'is_active' => true]);
        $newUser = $this->user('Koordinator baru');
        DB::table('support_team_members')->insert(['support_team_id' => $team, 'user_id' => $newUser->id, 'role' => 'coordinator']);
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        $t = $this->act($t, 'task', ['title' => 'Tugas lintas tim', 'assigned_to' => $this->technician->id, 'required' => true]);
        $task = DB::table('it_ticket_tasks')->value('id');
        $t = $this->act($t, 'transfer', ['team_id' => $team]);
        $t = $this->act($t, 'reassign_task', ['task_id' => $task, 'assigned_to' => $newUser->id], $newUser);
        $t = $this->act($t, 'complete_task', ['task_id' => $task], $newUser);
        $this->assertSame('done', DB::table('it_ticket_tasks')->where('id', $task)->value('status'));
    }

    public function test_workflow_publication_rejects_incomplete_team_and_profile_may_not_open_submission(): void
    {
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Workflow belum lengkap');
        $cfg = $v->configuration;
        $cfg['services'][0]['fallback_team_id'] = null;
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Simpan profil belum lengkap');
        $this->actingAs($this->manager)->postJson(route('it.configuration.publish', $v), ['set_revision' => 1, 'lock_version' => $v->lock_version, 'reason' => 'Harus ditolak publikasi'])->assertUnprocessable();
        $cfg['profiles']['submission_enabled'] = true;
        $this->putJson(route('it.configuration.update', $v), ['lock_version' => $v->lock_version, 'configuration' => $cfg, 'reason' => 'Mencoba membuka pengajuan'])->assertUnprocessable();
    }

    public function test_mobile_bearer_routes_share_workflow_permissions_and_validation(): void
    {
        (require database_path('migrations/2026_08_11_000001_create_mobile_satset_tokens_table.php'))->up();
        $token = 'phase3-mobile-test-token';
        \App\Models\MobileSatsetToken::create(['user_id' => $this->requester->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);
        $this->getJson('/api/mobile/v1/satset/it/catalog')->assertUnauthorized();
        $response = $this->withToken($token)->postJson('/api/mobile/v1/satset/it/tickets', $this->input())->assertCreated();
        $id = $response->json('id');
        $this->withToken($token)->getJson('/api/mobile/v1/satset/it/tickets/'.$id)->assertOk()->assertJsonPath('read_only', false);
        $this->withToken($token)->postJson('/api/mobile/v1/satset/it/tickets/'.$id.'/actions/claim', ['lock_version' => 1, 'role' => 'technician'])->assertForbidden();
        $bad = $this->input();
        $bad['answers']['internal_note'] = 'Forged internal';
        $this->withToken($token)->postJson('/api/mobile/v1/satset/it/tickets', $bad)->assertUnprocessable();
    }

    public function test_stale_form_version_is_rejected_and_published_ticket_snapshot_survives_new_version(): void
    {
        $t = $this->ticket();
        $original = $this->version->id;
        $this->approvalRule();
        $data = $this->input();
        $data['configuration_version_id'] = $original;
        $this->actingAs($this->requester)->postJson(route('it.store'), $data)->assertStatus(409);
        $this->assertSame($original, $t->fresh()->itDetail->configuration_version_id);
        $t = $this->act($t, 'triage', ['impact' => 'low', 'urgency' => 'low', 'team_id' => $this->team]);
        $this->assertSame('P4', $t->itDetail->priority);
    }

    public function test_removed_member_cannot_read_previous_assignment_or_notifications(): void
    {
        $t = $this->ticket();
        $t = $this->act($t, 'claim');
        DB::table('support_team_members')->where('support_team_id', $this->team)->where('user_id', $this->technician->id)->delete();
        $this->actingAs($this->technician)->getJson(route('it.show', $t))->assertForbidden();
        $this->getJson(route('it.notifications'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_revision_retains_required_files_and_latest_replacement_does_not_accumulate_older_files(): void
    {
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Tambahkan bukti wajib');
        $cfg = $v->configuration;
        $cfg['forms'][1]['fields'][] = ['key' => 'evidence', 'label' => 'Bukti wajib', 'type' => 'file', 'required' => true, 'visible_roles' => ['requester', 'technician', 'approver'], 'max_files' => 1, 'allowed_mimes' => ['pdf']];
        $cfg['profiles']['approval'] = [['key' => 'review', 'record_type' => 'service_request', 'resolver' => 'user', 'approver_id' => $this->approver->id]];
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Simpan bukti wajib');
        $this->version = $store->activate($v->id, 1, $v->lock_version, $this->manager, 'Publikasi bukti wajib');
        $data = $this->input('service_request');
        $t = $this->workflow->submit($this->requester, $data, ['evidence' => [UploadedFile::fake()->createWithContent('awal.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n")]]);
        for ($cycle = 1; $cycle <= 3; $cycle++) {
            $a = DB::table('it_ticket_approvals')->where('request_id', $t->id)->where('cycle', $cycle)->first();
            $this->workflow->decide($this->approver, $a->id, ['lock_version' => $t->fresh()->itDetail->lock_version, 'decision' => 'revision', 'reason' => 'Perbarui rincian permintaan']);
            $this->actingAs($this->requester)->get(route('it.revise.form', $t))->assertOk();
            $files = $cycle === 2 ? ['evidence' => [UploadedFile::fake()->createWithContent('baru.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n")]] : [];
            $t = $this->workflow->action($this->requester, $t, 'revise', ['lock_version' => $t->fresh()->itDetail->lock_version, 'reason' => 'Rincian telah diperbaiki', 'title' => $t->title, 'description' => $t->description, 'answers' => $data['answers']], $files);
            $this->assertSame('awaiting_approval', $t->itDetail->status);
        }
        $this->assertSame('baru.pdf', $t->itDetail->form_answers['evidence'][0]['name']);
        $this->assertSame(2, DB::table('it_ticket_attachments')->where('request_id', $t->id)->count());
    }

    public function test_request_schema_projects_role_and_dependencies_without_admin_bundle(): void
    {
        $this->actingAs($this->requester)->get(route('it.create'))->assertOk();
        $this->postJson(route('it.form'), ['catalog_key' => 'network_issue', 'role' => 'technician'])->assertOk()->assertJsonPath('role', 'requester')->assertJsonPath('dependencies.0', 'connection')->assertJsonMissingPath('profiles');
        $this->actingAs($this->manager)->postJson(route('it.configuration.teams.store'), ['code' => 'new_support', 'name' => 'Tim khusus baru', 'reason' => 'Penambahan dukungan khusus'])->assertCreated();
        $this->assertFalse((bool) DB::table('support_teams')->where('code', 'new_support')->value('is_active'));
        $this->assertSame(1, DB::table('it_team_events')->count());
    }

    public function test_catalog_restriction_applies_at_submission_before_any_staff_can_read(): void
    {
        $store = app(ConfigurationStore::class);
        $v = $store->draft($this->manager, 'Atur katalog rahasia');
        $cfg = $v->configuration;
        foreach ($cfg['catalog'] as &$c) {
            if ($c['key'] === 'network_issue') {
                $c['is_restricted'] = true;
            }
        }unset($c);
        $v = $store->save($v->id, $cfg, 0, $this->manager, 'Simpan akses katalog');
        $store->activate($v->id, 1, $v->lock_version, $this->manager, 'Publikasikan akses katalog');
        $t = $this->ticket('incident', ['catalog_key' => 'network_issue']);
        $this->assertTrue($t->itDetail->is_restricted);
        $this->actingAs($this->technician)->getJson(route('it.show', $t))->assertForbidden();
        $this->grant($this->technician, 'view_restricted_it');
        $this->getJson(route('it.show', $t))->assertOk();
        $this->actingAs($this->requester)->getJson(route('it.show', $t))->assertOk();
    }

    public function test_it_list_priority_filter_respects_ticket_visibility(): void
    {
        $visible = $this->ticket();
        $otherRequester = $this->user('Pemohon lain');
        $data = $this->input('incident', ['title' => 'Tiket privat orang lain', 'impact' => 'low', 'urgency' => 'low']);
        $hidden = $this->workflow->submit($otherRequester, $data);
        $this->actingAs($this->requester)->get(route('it.index', ['priority' => 'P2']))->assertOk()->assertSee($visible->ticket_no)->assertSee($this->requester->name)->assertDontSee($hidden->ticket_no);
        $this->get(route('it.index', ['priority' => 'P4']))->assertOk()->assertDontSee($hidden->ticket_no)->assertDontSee($visible->ticket_no);
        $this->getJson(route('it.index', ['priority' => 'bad']))->assertUnprocessable();
    }

    public function test_detail_uses_readable_form_labels_and_keeps_internal_discussion_private(): void
    {
        $ticket = $this->ticket();
        $ticket = $this->workflow->action($this->technician, $ticket, 'comment', ['lock_version' => $ticket->itDetail->lock_version, 'is_internal' => true, 'reason' => 'Catatan internal khusus teknisi']);
        $ticket = $this->workflow->action($this->technician, $ticket, 'comment', ['lock_version' => $ticket->itDetail->lock_version, 'is_internal' => false, 'reason' => 'Pembaruan publik untuk pemohon']);
        $this->actingAs($this->requester)->get(route('it.show', $ticket))->assertOk()
            ->assertSee('Deskripsi')->assertSee('Detail Request')->assertSee('Nama jaringan Wi-Fi')
            ->assertSee('Pembaruan publik untuk pemohon')->assertDontSee('Catatan internal khusus teknisi')
            ->assertDontSee('Riwayat penanganan')->assertSee($this->technician->name);
        $this->actingAs($this->technician)->get(route('it.show', $ticket))->assertOk()
            ->assertSee('Catatan internal khusus teknisi')->assertSee('Riwayat penanganan')->assertSee('Tindak lanjut');
    }
}
