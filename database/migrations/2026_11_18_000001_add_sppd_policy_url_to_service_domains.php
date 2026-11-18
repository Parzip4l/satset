<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_domains', fn (Blueprint $table) => $table->text('sppd_policy_url')->nullable());
    }

    public function down(): void
    {
        Schema::table('service_domains', fn (Blueprint $table) => $table->dropColumn('sppd_policy_url'));
    }
};
