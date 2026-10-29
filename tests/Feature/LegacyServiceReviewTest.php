<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LegacyServiceReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        DB::table('users')->insert(['id' => 1]);
        Schema::create('problem_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('code');
        });
        DB::table('problem_categories')->insert([
            ['id' => 1, 'parent_id' => null, 'code' => 'IT'], ['id' => 2, 'parent_id' => 1, 'code' => 'HW'],
            ['id' => 3, 'parent_id' => null, 'code' => 'GA'],
        ]);
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code');
        });
        DB::table('ticket_categories')->insert([['id' => 1, 'code' => 'INC'], ['id' => 2, 'code' => 'SR']]);
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        DB::table('statuses')->insert(['id' => 1, 'name' => 'In Progress']);
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('ticket_category_id')->nullable();
            $table->foreignId('status_id')->default(1);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_28_000001_create_it_service_foundation.php'))->up();
        (require database_path('migrations/2026_10_28_000002_create_service_domain_reviews.php'))->up();
    }

    private function ticket(int $category, ?int $type = null, array $payload = []): int
    {
        return DB::table('requests')->insertGetId(['category_id' => $category, 'ticket_category_id' => $type,
            'payload' => json_encode($payload), 'created_at' => '2025-01-01 10:00:00', 'updated_at' => '2025-01-02 10:00:00']);
    }

    public function test_review_uses_explicit_markers_then_it_ancestry_and_preserves_ambiguous_legacy(): void
    {
        $incident = $this->ticket(2, 1);
        $untyped = $this->ticket(1);
        $ga = $this->ticket(3);
        $atk = $this->ticket(1, 2, ['request_type' => 'atk_rtk']);
        $original = DB::table('requests')->select('id', 'category_id', 'ticket_category_id', 'payload', 'created_at', 'updated_at')->get()->toJson();
        $this->artisan('satset:review-legacy-domains', ['--reviewer' => 1])->assertSuccessful();
        $this->assertDatabaseCount('service_domain_reviews', 0);
        $this->artisan('satset:review-legacy-domains', ['--reviewer' => 1, '--apply' => true])->assertSuccessful();
        $itId = DB::table('service_domains')->where('code', 'it')->value('id');
        $this->assertDatabaseHas('requests', ['id' => $incident, 'service_domain_id' => $itId]);
        $this->assertDatabaseHas('requests', ['id' => $untyped, 'service_domain_id' => $itId]);
        $this->assertDatabaseHas('requests', ['id' => $ga, 'service_domain_id' => null]);
        $this->assertDatabaseHas('it_ticket_details', ['request_id' => $incident, 'record_type' => 'incident', 'status' => 'in_progress']);
        $this->assertDatabaseMissing('it_ticket_details', ['request_id' => $untyped]);
        $this->assertDatabaseHas('service_domain_reviews', ['request_id' => $ga, 'decision' => 'retained_legacy']);
        $this->assertDatabaseHas('service_domain_reviews', ['request_id' => $atk, 'mapped_service' => 'atk_rtk']);
        $this->assertSame($original, DB::table('requests')->select('id', 'category_id', 'ticket_category_id', 'payload', 'created_at', 'updated_at')->get()->toJson());
        $this->artisan('satset:review-legacy-domains', ['--reviewer' => 1, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('service_domain_reviews', 4);
        $this->assertDatabaseCount('it_ticket_details', 1);
    }

    public function test_apply_requires_an_existing_reviewer(): void
    {
        $this->ticket(1);
        $this->artisan('satset:review-legacy-domains', ['--apply' => true])->assertFailed();
        $this->artisan('satset:review-legacy-domains', ['--reviewer' => 99, '--apply' => true])->assertFailed();
        $this->assertDatabaseCount('service_domain_reviews', 0);
    }

    public function test_cycle_aborts_the_entire_review_transaction(): void
    {
        $this->ticket(1, 1);
        $this->ticket(2, 1);
        DB::table('problem_categories')->where('id', 2)->update(['parent_id' => 2]);
        try {
            Artisan::call('satset:review-legacy-domains', ['--reviewer' => 1, '--apply' => true]);
            $this->fail('Cycle should reject the review.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('cycle', $error->getMessage());
        }
        $this->assertDatabaseCount('service_domain_reviews', 0);
        $this->assertSame(0, DB::table('requests')->whereNotNull('service_domain_id')->count());
    }

    public function test_conflicting_domain_is_not_silently_overwritten(): void
    {
        $id = $this->ticket(1, 1);
        DB::table('requests')->where('id', $id)->update(['service_domain_id' => DB::table('service_domains')->where('code', 'consumption')->value('id')]);
        $this->expectException(RuntimeException::class);
        Artisan::call('satset:review-legacy-domains', ['--reviewer' => 1, '--apply' => true]);
    }
}
