<?php

// database/migrations/2025_08_28_000001_create_ticket_masters.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // PRIORITIES
        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // High, Medium, Low, Critical
            $table->string('code', 20)->unique(); // HIGH, MED, LOW, CRIT
            $table->timestamps();
        });

        // STATUSES
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Open, In Progress, Resolved, Closed, Cancelled
            $table->string('code', 20)->unique(); // OPEN, INPROG, RESOLVED, CLOSED, CANCEL
            $table->timestamps();
        });

        // IMPACTS
        Schema::create('impacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Low, Medium, High
            $table->string('code', 20)->unique(); // LOW, MED, HIGH
            $table->timestamps();
        });

        // URGENCIES
        Schema::create('urgencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Low, Medium, High
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        // UPDATE REQUESTS TABLE
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['priority', 'impact', 'urgency', 'status']);

            $table->foreignId('priority_id')->nullable()->constrained('priorities');
            $table->foreignId('impact_id')->nullable()->constrained('impacts');
            $table->foreignId('urgency_id')->nullable()->constrained('urgencies');
            $table->foreignId('status_id')->constrained('statuses');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['priority_id']);
            $table->dropForeign(['impact_id']);
            $table->dropForeign(['urgency_id']);
            $table->dropForeign(['status_id']);

            $table->dropColumn(['priority_id', 'impact_id', 'urgency_id', 'status_id']);

            // restore old string columns
            $table->string('priority', 20)->nullable();
            $table->string('impact', 20)->nullable();
            $table->string('urgency', 20)->nullable();
            $table->string('status', 20)->default('Open');
        });

        Schema::dropIfExists('priorities');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('impacts');
        Schema::dropIfExists('urgencies');
    }
};

