<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // ============================
        // MASTER DATA
        // ============================

        Schema::create('problem_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('problem_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ============================
        // REQUEST TICKETING
        // ============================
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 30)->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('department_id')->constrained('departments')->nullable();
            $table->foreignId('category_id')->constrained('problem_categories');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('priority', 20)->nullable(); // Low, Medium, High, Critical
            $table->string('impact', 20)->nullable();
            $table->string('urgency', 20)->nullable();
            $table->string('status', 20)->default('Open'); // Open/In Progress/Pending/Resolved/Closed
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        // ============================
        // ROUTING & SLA
        // ============================
        Schema::create('sla', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('response_time_minutes');
            $table->integer('resolve_time_minutes');
            $table->boolean('business_hours')->default(true);
            $table->timestamps();
        });

        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('problem_categories');
            $table->foreignId('assignee_department_id')->constrained('departments');
            $table->string('default_priority', 20)->nullable();
            $table->foreignId('sla_id')->nullable()->constrained('sla');
            $table->timestamps();
        });

        // ============================
        // ASSIGNMENT & APPROVAL
        // ============================
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users');
            $table->foreignId('assignee_department_id')->nullable()->constrained('departments');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users');
            $table->integer('level');
            $table->string('status', 20)->default('Pending'); // Pending/Approved/Rejected
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ============================
        // LOGGING & HISTORY
        // ============================
        Schema::create('status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->timestamp('changed_at')->useCurrent();
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('mime_type', 50)->nullable();
            $table->integer('size')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_id')->constrained('sla');
            $table->integer('threshold_minutes');
            $table->foreignId('escalate_to_user_id')->nullable()->constrained('users');
            $table->foreignId('escalate_to_department_id')->nullable()->constrained('departments');
            $table->string('notify_roles', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('escalation_rules');
        Schema::dropIfExists('watchers');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('status_logs');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('sla');
        Schema::dropIfExists('requests');
        Schema::dropIfExists('problem_categories');
    }
};
