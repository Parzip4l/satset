<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_practice_records', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30);
            $t->string('number', 40)->unique();
            $t->string('title', 200);
            $t->text('description');
            $t->foreignId('support_team_id')->constrained('support_teams')->restrictOnDelete();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->boolean('is_restricted')->default(false);
            $t->string('status', 30);
            $t->unsignedInteger('lock_version')->default(1);
            $t->json('data');
            $t->timestamps();
            $t->index(['type', 'support_team_id', 'status']);
        });
        Schema::create('it_practice_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('it_practice_records')->restrictOnDelete();
            $t->foreignId('related_record_id')->nullable()->constrained('it_practice_records')->restrictOnDelete();
            $t->foreignId('request_id')->nullable()->constrained('requests')->restrictOnDelete();
            $t->string('type', 30);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->timestamp('created_at');
            $t->unique(['record_id', 'related_record_id', 'type']);
            $t->unique(['record_id', 'request_id', 'type']);
        });
        Schema::create('it_practice_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('it_practice_records')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->string('action', 40);
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->text('reason');
            $t->json('metadata');
            $t->timestamp('created_at');
            $t->unique(['record_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_practice_events');
        Schema::dropIfExists('it_practice_links');
        Schema::dropIfExists('it_practice_records');
    }
};
