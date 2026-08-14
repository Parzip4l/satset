<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->string('last_action_source', 40)->nullable()->after('notes');
            $table->string('portal_reference_id', 120)->nullable()->after('last_action_source');
        });

        Schema::create('approval_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 40);
            $table->string('status', 20);
            $table->text('comment')->nullable();
            $table->string('satset_reference_id', 120);
            $table->string('external_reference_id', 120)->nullable();
            $table->string('approver_email')->nullable();
            $table->string('approver_name')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_audits');

        Schema::table('approvals', function (Blueprint $table) {
            $table->dropColumn(['last_action_source', 'portal_reference_id']);
        });
    }
};
