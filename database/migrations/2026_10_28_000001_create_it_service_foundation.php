<?php

use App\Support\ServiceDomains;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_domains', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 60);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        foreach (array_keys(ServiceDomains::DEFINITIONS) as $order => $code) {
            $definition = ServiceDomains::DEFINITIONS[$code];
            DB::table('service_domains')->insert([
                'code' => $code, 'name' => $definition['name'], 'description' => $definition['description'],
                'icon' => $definition['icon'], 'sort_order' => $order, 'is_active' => $code !== 'it',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('service_domain_id')->nullable()->index()->constrained('service_domains')->restrictOnDelete();
        });
        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('support_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('technician');
            $table->timestamps();
            $table->unique(['support_team_id', 'user_id']);
        });
        Schema::create('it_user_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 80);
            $table->timestamps();
            $table->unique(['user_id', 'capability']);
        });
        Schema::create('it_ticket_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('requests')->cascadeOnDelete();
            $table->enum('record_type', ['incident', 'service_request']);
            $table->foreignId('support_team_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('submitted');
            $table->boolean('is_restricted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_ticket_details');
        Schema::dropIfExists('it_user_capabilities');
        Schema::dropIfExists('support_team_members');
        Schema::dropIfExists('support_teams');
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['service_domain_id']);
            $table->dropIndex(['service_domain_id']);
            $table->dropColumn('service_domain_id');
        });
        Schema::dropIfExists('service_domains');
    }
};
