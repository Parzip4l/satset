<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // contoh: Incident, Service Request, Change Request
            $table->string('code')->nullable(); // optional: INC, SR, CR
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('ticket_category_id')
                ->nullable()
                ->constrained('ticket_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_category_id');
        });
        Schema::dropIfExists('ticket_categories');
    }
};
