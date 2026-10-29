<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_configuration_sets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
        Schema::create('it_configuration_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_set_id')->constrained('it_configuration_sets')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('state', 20)->default('draft');
            $table->unsignedInteger('lock_version')->default(0);
            $table->json('configuration');
            $table->string('checksum', 64);
            $table->foreignId('source_version_id')->nullable()->constrained('it_configuration_versions')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['configuration_set_id', 'number'], 'it_configuration_version_number_unique');
        });
        Schema::table('it_configuration_sets', fn (Blueprint $table) => $table->foreign('active_version_id')->references('id')->on('it_configuration_versions')->restrictOnDelete());
        Schema::create('it_configuration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_set_id')->constrained('it_configuration_sets')->restrictOnDelete();
            $table->foreignId('version_id')->constrained('it_configuration_versions')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->text('reason');
            $table->string('before_checksum', 64)->nullable();
            $table->string('after_checksum', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });
        Schema::table('it_ticket_details', function (Blueprint $table) {
            $table->foreignId('configuration_version_id')->nullable()->constrained('it_configuration_versions')->restrictOnDelete();
            $table->string('form_key', 50)->nullable();
            $table->json('form_answers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('it_ticket_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('configuration_version_id');
            $table->dropColumn(['form_key', 'form_answers']);
        });
        Schema::dropIfExists('it_configuration_events');
        Schema::table('it_configuration_sets', fn (Blueprint $table) => $table->dropForeign(['active_version_id']));
        Schema::dropIfExists('it_configuration_versions');
        Schema::dropIfExists('it_configuration_sets');
    }
};
