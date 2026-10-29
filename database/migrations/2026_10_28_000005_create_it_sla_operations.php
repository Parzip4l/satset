<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_ticket_details', function (Blueprint $t) {
            $t->unsignedInteger('sla_cycle')->default(1);
            $t->string('pending_code', 30)->nullable();
            $t->boolean('is_major')->default(false);
            $t->foreignId('major_commander_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('major_interval_minutes')->nullable();
            $t->timestamp('major_next_update_at')->nullable();
            $t->text('major_summary')->nullable();
            $t->text('major_review')->nullable();
        });
        Schema::create('it_sla_clocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->unsignedInteger('cycle');
            $t->string('kind', 20);
            $t->foreignId('previous_clock_id')->nullable()->constrained('it_sla_clocks')->restrictOnDelete();
            $t->json('snapshot');
            $t->string('snapshot_checksum', 64);
            $t->string('priority', 10);
            $t->unsignedBigInteger('target_seconds');
            $t->unsignedBigInteger('carried_seconds')->default(0);
            $t->unsignedBigInteger('consumed_seconds')->default(0);
            $t->string('state', 20);
            $t->string('pause_reason', 30)->nullable();
            $t->timestamp('started_at');
            $t->timestamp('accounted_at');
            $t->timestamp('due_at')->nullable()->index();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('breached_at')->nullable();
            $t->timestamps();
            $t->unique(['request_id', 'cycle', 'kind']);
        });
        Schema::create('it_sla_spans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clock_id')->constrained('it_sla_clocks')->restrictOnDelete();
            $t->timestamp('from_at');
            $t->timestamp('to_at');
            $t->unsignedBigInteger('consumed_seconds');
        });
        Schema::create('it_sla_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->foreignId('clock_id')->nullable()->constrained('it_sla_clocks')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('event_key', 160)->unique();
            $t->string('action', 40);
            $t->json('metadata');
            $t->timestamp('created_at');
        });
        Schema::create('it_ticket_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_request_id')->constrained('requests')->restrictOnDelete();
            $t->foreignId('child_request_id')->constrained('requests')->restrictOnDelete();
            $t->string('type', 30);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->timestamp('created_at');
            $t->unique(['parent_request_id', 'child_request_id', 'type'], 'it_links_unique');
            $t->unique(['child_request_id', 'type'], 'it_child_major_unique');
        });
        Schema::create('it_delivery_outbox', function (Blueprint $t) {
            $t->id();
            $t->string('delivery_key', 190)->unique();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('sla_event_id')->constrained('it_sla_events')->restrictOnDelete();
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index(['status', 'dispatched_at']);
        });
        Schema::create('it_operation_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->unique()->constrained('it_delivery_outbox')->restrictOnDelete();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('message');
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('it_worker_heartbeats', function (Blueprint $t) {
            $t->string('component', 40)->primary();
            $t->string('status', 20);
            $t->timestamp('last_seen_at');
            $t->json('metadata');
        });
        Schema::create('it_queue_jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('it_queue_failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
            $t->timestamp('resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['it_queue_failed_jobs', 'it_queue_jobs', 'it_worker_heartbeats', 'it_operation_notifications', 'it_delivery_outbox', 'it_ticket_links', 'it_sla_events', 'it_sla_spans', 'it_sla_clocks'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('it_ticket_details', function (Blueprint $t) {
            $t->dropForeign(['major_commander_id']);
            $t->dropColumn(['sla_cycle', 'pending_code', 'is_major', 'major_commander_id', 'major_interval_minutes', 'major_next_update_at', 'major_summary', 'major_review']);
        });
    }
};
