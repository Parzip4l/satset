<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditItFoundation extends Command
{
    protected $signature = 'satset:it-audit {--json : Output JSON tanpa isi tiket atau identitas pemohon}';

    protected $description = 'Audit read-only schema dan kelompok tiket untuk migrasi layanan IT';

    public function handle(): int
    {
        $report = ['connection_driver' => DB::getDriverName(), 'tables' => [], 'groups' => [], 'review_count' => 0, 'reviewed_count' => 0, 'legacy_exception_count' => 0];
        foreach (['requests', 'problem_categories', 'category_department', 'sla', 'routing_rules', 'escalation_rules', 'assignments',
            'approvals', 'approval_audits', 'ticket_form_schemas', 'service_domains', 'support_teams', 'support_team_members',
            'it_user_capabilities', 'it_ticket_details', 'service_domain_reviews'] as $table) {
            $exists = Schema::hasTable($table);
            $report['tables'][$table] = ['exists' => $exists, 'columns' => $exists ? Schema::getColumnListing($table) : [],
                'rows' => $exists ? DB::table($table)->count() : 0];
        }
        if (Schema::hasTable('requests') && Schema::hasColumn('requests', 'payload')) {
            $fields = array_values(array_intersect(['id', 'payload', 'ticket_category_id', 'category_id', 'status_id', 'assigned_department_id', 'service_domain_id'], Schema::getColumnListing('requests')));
            DB::table('requests')->select($fields)->orderBy('id')->chunkById(500, function ($rows) use (&$report) {
                $itIds = Schema::hasTable('it_ticket_details')
                    ? DB::table('it_ticket_details')->whereIn('request_id', $rows->pluck('id'))->pluck('request_id')->all() : [];
                $domains = Schema::hasTable('service_domains') ? DB::table('service_domains')->pluck('code', 'id') : collect();
                $reviews = Schema::hasTable('service_domain_reviews') ? DB::table('service_domain_reviews')->whereIn('request_id', $rows->pluck('id'))->get()->keyBy('request_id') : collect();
                foreach ($rows as $row) {
                    $payload = is_string($row->payload) ? json_decode($row->payload, true) : $row->payload;
                    $rawType = data_get($payload, 'request_type');
                    // Unknown values are bucketed: never echo arbitrary user-entered content.
                    $type = in_array($rawType, ['atk_rtk', 'consumption', 'ga_request_finding', 'general', 'it'], true) ? $rawType : 'unknown';
                    $decision = in_array($type, ['atk_rtk', 'consumption', 'ga_request_finding'], true) ? $type : 'manual_review';
                    $hasDetail = in_array($row->id, $itIds);
                    if ($hasDetail || (isset($row->service_domain_id) && $domains->get($row->service_domain_id) !== $type)) {
                        $decision = 'manual_review';
                    }
                    if ($review = $reviews->get($row->id)) {
                        $decision = $review->decision === 'retained_legacy' ? 'reviewed_retained_legacy' : 'reviewed_'.($review->mapped_service ?? 'unknown');
                        $report['reviewed_count']++;
                        if ($review->decision === 'retained_legacy') {
                            $report['legacy_exception_count']++;
                        }
                    }
                    $key = implode('|', [$type, $row->ticket_category_id ?? '-', $row->category_id ?? '-', $row->status_id ?? '-', $row->assigned_department_id ?? '-', $row->service_domain_id ?? '-', (int) $hasDetail, $decision]);
                    if (! isset($report['groups'][$key])) {
                        $report['groups'][$key] = ['request_type' => $type, 'ticket_category_id' => $row->ticket_category_id ?? null,
                            'category_id' => $row->category_id ?? null, 'status_id' => $row->status_id ?? null,
                            'assigned_department_id' => $row->assigned_department_id ?? null, 'service_domain_id' => $row->service_domain_id ?? null,
                            'has_it_detail' => $hasDetail, 'mapping' => $decision, 'count' => 0];
                    }
                    $report['groups'][$key]['count']++;
                    if ($decision === 'manual_review') {
                        $report['review_count']++;
                    }
                }
            });
        }
        $report['groups'] = array_values($report['groups']);
        $report['notes'] = ['Read-only; general/unknown tidak otomatis dianggap IT.', 'Verifikasi backup/restore dan keputusan bisnis tetap diperlukan sebelum cutover.'];
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
