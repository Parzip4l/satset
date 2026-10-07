<?php

namespace Tests\Feature;

use App\Services\TicketDataBackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketDataBackupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['attachments', 'comments', 'requests'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function test_it_backs_up_clears_and_restores_ticket_data_and_files(): void
    {
        Storage::fake('public');
        DB::table('requests')->insert([
            'id' => 17,
            'ticket_no' => 'TCK-0017',
            'title' => 'Lampu peron mati',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('comments')->insert([
            'id' => 9,
            'request_id' => 17,
            'message' => 'Segera ditindaklanjuti',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attachments')->insert([
            'id' => 4,
            'request_id' => 17,
            'file_name' => 'bukti.txt',
            'file_path' => 'request-attachments/17/bukti.txt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put('request-attachments/17/bukti.txt', 'isi bukti');

        $archive = tempnam(sys_get_temp_dir(), 'ticket-backup-test-');
        $service = app(TicketDataBackupService::class);

        try {
            $manifest = $service->createArchive($archive);
            $this->assertSame(1, $manifest['ticket_count']);

            $this->assertSame(1, $service->clearAll());
            $this->assertDatabaseCount('requests', 0);
            $this->assertDatabaseCount('attachments', 0);
            Storage::disk('public')->assertMissing('request-attachments/17/bukti.txt');

            $this->assertSame(1, $service->restoreArchive($archive));
            $this->assertDatabaseHas('requests', ['id' => 17, 'ticket_no' => 'TCK-0017']);
            $this->assertDatabaseHas('comments', ['id' => 9, 'request_id' => 17]);
            $this->assertDatabaseHas('attachments', ['id' => 4, 'request_id' => 17]);
            Storage::disk('public')->assertExists('request-attachments/17/bukti.txt');
            $this->assertSame('isi bukti', Storage::disk('public')->get('request-attachments/17/bukti.txt'));
        } finally {
            @unlink($archive);
        }
    }
}
