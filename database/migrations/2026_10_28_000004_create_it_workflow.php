<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_ticket_details', function (Blueprint $t) {
            $t->string('catalog_key', 50)->nullable();
            $t->string('service_key', 50)->nullable();
            $t->string('category_key', 50)->nullable();
            $t->string('impact', 20)->nullable();
            $t->string('urgency', 20)->nullable();
            $t->string('priority', 10)->nullable();
            $t->unsignedInteger('lock_version')->default(0);
            $t->unsignedInteger('cycle')->default(1);
            $t->string('submission_fingerprint', 64)->nullable();
            $t->text('pending_reason')->nullable();
            $t->text('resolution')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamp('next_update_at')->nullable();
            $t->index(['support_team_id', 'status']);
        });
        Schema::create('it_team_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('support_team_id')->constrained('support_teams')->restrictOnDelete();
            $t->text('reason');
            $t->json('before_state');
            $t->json('after_state');
            $t->timestamp('created_at');
        });
        Schema::create('it_ticket_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->string('action', 40);
            $t->string('from_status', 40)->nullable();
            $t->string('to_status', 40);
            $t->text('reason')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at');
            $t->unique(['request_id', 'sequence']);
        });
        Schema::create('it_ticket_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->boolean('is_internal')->default(false);
            $t->text('body');
            $t->timestamp('created_at');
        });
        Schema::create('it_ticket_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('field_key', 50)->nullable();
            $t->unsignedInteger('form_cycle')->nullable();
            $t->string('original_name');
            $t->string('path')->unique();
            $t->string('mime', 150);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->boolean('is_internal')->default(false);
            $t->timestamp('created_at');
        });
        Schema::create('it_ticket_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('title', 200);
            $t->boolean('required')->default(true);
            $t->string('status', 20)->default('open');
            $t->text('result')->nullable();
            $t->timestamps();
        });
        Schema::create('it_ticket_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $t->unsignedInteger('cycle');
            $t->string('rule_key', 50);
            $t->foreignId('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('status', 30)->default('pending');
            $t->text('reason')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->unique(['request_id', 'cycle']);
            $t->index(['approver_id', 'status']);
        });
        Schema::create('it_ticket_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('it_ticket_events')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('kind', 30);
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['it_team_events', 'it_ticket_notifications', 'it_ticket_approvals', 'it_ticket_tasks', 'it_ticket_attachments', 'it_ticket_comments', 'it_ticket_events'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('it_ticket_details', function (Blueprint $t) {
            $t->dropIndex(['support_team_id', 'status']);
            $t->dropColumn(['catalog_key', 'service_key', 'category_key', 'impact', 'urgency', 'priority', 'lock_version', 'cycle', 'submission_fingerprint', 'pending_reason', 'resolution', 'resolved_at', 'closed_at', 'next_update_at']);
        });
    }
};
