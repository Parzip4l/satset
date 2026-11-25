<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_assets', function (Blueprint $table) {
            $table->json('specifications')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('it_assets', function (Blueprint $table) {
            $table->dropColumn('specifications');
        });
    }
};
