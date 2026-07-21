<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            if (!Schema::hasColumn('divisions', 'signal_id')) {
                $table->string('signal_id', 64)->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('divisions', 'code')) {
                $table->string('code', 30)->nullable()->unique()->after('signal_id');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'signal_id')) {
                $table->string('signal_id', 64)->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'signal_id')) {
                $table->dropUnique(['signal_id']);
                $table->dropColumn('signal_id');
            }
        });

        Schema::table('divisions', function (Blueprint $table) {
            if (Schema::hasColumn('divisions', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }

            if (Schema::hasColumn('divisions', 'signal_id')) {
                $table->dropUnique(['signal_id']);
                $table->dropColumn('signal_id');
            }
        });
    }
};
