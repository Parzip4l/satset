<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Master\TicketFormSchema;

class TicketFormSchemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TicketFormSchema::create([
            'ticket_category_id' => 1,
            'schema' => [
                [
                    'name' => 'date',
                    'label' => 'Tanggal Meeting',
                    'type' => 'date',
                    'required' => true,
                ],
                [
                    'name' => 'capacity',
                    'label' => 'Jumlah Peserta',
                    'type' => 'number',
                    'required' => true,
                ],
            ],
        ]);

        TicketFormSchema::create([
            'ticket_category_id' => 2, // Generate QR
            'schema' => [
                [
                    'name' => 'qr_type',
                    'label' => 'Jenis QR',
                    'type' => 'select',
                    'options' => ['Visitor', 'Vendor'],
                    'required' => true,
                ],
                [
                    'name' => 'expired_at',
                    'label' => 'Berlaku Sampai',
                    'type' => 'date',
                    'required' => true,
                ],
            ],
        ]);
    }
}
