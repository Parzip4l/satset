<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ReviewLegacyServiceDomains extends Command
{
    protected $signature = 'satset:review-legacy-domains {--reviewer= : Akun penanggung jawab migrasi} {--apply : Default hanya dry-run}';

    protected $description = 'Review legacy berbasis marker dan ancestry kategori eksplisit; kasus ambigu dipertahankan dalam register pengecualian';

    public function handle(): int
    {
        if (! Schema::hasTable('service_domain_reviews')) {
            $this->error('Migration register review belum dijalankan.');

            return self::FAILURE;
        }
        $reviewer = filter_var($this->option('reviewer'), FILTER_VALIDATE_INT);
        if ($this->option('apply') && (! $reviewer || ! DB::table('users')->where('id', $reviewer)->exists())) {
            $this->error('Gunakan --reviewer dengan akun existing yang ditunjuk.');

            return self::FAILURE;
        }
        $categories = DB::table('problem_categories')->get()->keyBy('id');
        $domains = DB::table('service_domains')->pluck('id', 'code');
        $types = DB::table('ticket_categories')->pluck('code', 'id');
        $statuses = DB::table('statuses')->pluck('name', 'id');
        $counts = [];
        DB::transaction(function () use ($reviewer, $categories, $domains, $types, $statuses, &$counts) {
            $tickets = DB::table('requests')->whereNotExists(fn ($review) => $review->selectRaw('1')->from('service_domain_reviews')->whereColumn('service_domain_reviews.request_id', 'requests.id'))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($tickets as $ticket) {
                $payload = json_decode($ticket->payload ?? '{}', true) ?: [];
                $marker = data_get($payload, 'request_type');
                $service = in_array($marker, ['atk_rtk', 'consumption', 'ga_request_finding'], true) ? $marker : null;
                $path = [];
                $visited = [];
                $id = $ticket->category_id;
                while ($id && isset($categories[$id])) {
                    if (isset($visited[$id])) {
                        throw new RuntimeException('Category cycle; review aborted.');
                    }
                    $visited[$id] = true;
                    $category = $categories[$id];
                    $path[] = ['id' => $category->id, 'code' => $category->code];
                    $id = $category->parent_id;
                }
                $root = $path ? end($path) : null;
                if (! $service && in_array($marker, [null, '', 'general'], true) && ($root['code'] ?? null) === 'IT') {
                    $service = 'it';
                }
                if ($ticket->service_domain_id && (! $service || (int) $ticket->service_domain_id !== (int) ($domains[$service] ?? 0))) {
                    throw new RuntimeException('Existing domain conflicts with classification for ticket '.$ticket->id);
                }
                $decision = $service ? 'mapped' : 'retained_legacy';
                $reason = in_array($marker, ['atk_rtk', 'consumption', 'ga_request_finding'], true)
                    ? 'Explicit request_type takes precedence over historical category.'
                    : ($service === 'it' ? 'Explicit IT root ancestry; original payload and ticket type retained.' : 'No unambiguous service marker; retained in legacy exception register.');
                $counts[$service ?? 'retained_legacy'] = ($counts[$service ?? 'retained_legacy'] ?? 0) + 1;
                if (! $this->option('apply')) {
                    continue;
                }
                if ($service) {
                    if (! isset($domains[$service])) {
                        throw new RuntimeException('Missing service domain.');
                    }
                    DB::table('requests')->where('id', $ticket->id)->update(['service_domain_id' => $domains[$service]]);
                }
                $recordType = match ($types[$ticket->ticket_category_id] ?? null) {
                    'INC' => 'incident', 'SR' => 'service_request', default => null
                };
                if ($service === 'it' && $recordType) {
                    $status = match (strtolower(trim($statuses[$ticket->status_id] ?? ''))) {
                        'open' => 'submitted', 'in progress' => 'in_progress', 'resolved' => 'resolved', 'closed' => 'closed', 'cancelled' => 'cancelled', default => 'legacy_unknown',
                    };
                    DB::table('it_ticket_details')->insertOrIgnore(['request_id' => $ticket->id, 'record_type' => $recordType, 'status' => $status, 'is_restricted' => false, 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('service_domain_reviews')->insert([
                    'request_id' => $ticket->id, 'decision' => $decision, 'mapped_service' => $service, 'reason' => $reason,
                    'evidence' => json_encode(['request_type' => is_string($marker) ? $marker : null, 'category_path' => $path, 'ticket_type_code' => $types[$ticket->ticket_category_id] ?? null, 'record_type' => $recordType], JSON_THROW_ON_ERROR),
                    'reviewed_by' => $reviewer, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $this->line(json_encode(['mode' => $this->option('apply') ? 'applied' : 'dry-run', 'counts' => $counts], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
