<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Master\TicketFormSchema;
use App\Models\Master\TicketCategory;
use App\Models\Master\ProblemCategory;

class TicketFormSchemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $incidentCategory = TicketCategory::firstOrCreate(
            ['code' => 'INC'],
            ['name' => 'Incident']
        );

        $serviceRequestCategory = TicketCategory::firstOrCreate(
            ['code' => 'SR'],
            ['name' => 'Service Request']
        );

        $bumCategory = TicketCategory::firstOrCreate(
            ['code' => 'BUM'],
            [
                'name' => 'Layanan BUM',
                'description' => 'Layanan Bagian Umum untuk ATK/RTK, konsumsi rapat, dan QR Permintaan & Temuan.',
            ]
        );

        ProblemCategory::firstOrCreate(
            ['code' => 'GAQR'],
            [
                'name' => 'GA Permintaan dan Temuan',
                'description' => 'Kategori laporan QR Code Bagian Umum untuk dukungan permintaan dan temuan.',
            ]
        );

        TicketFormSchema::updateOrCreate([
            'ticket_category_id' => $incidentCategory->id,
        ], [
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

        TicketFormSchema::updateOrCreate([
            'ticket_category_id' => $serviceRequestCategory->id,
        ], [
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

        TicketFormSchema::updateOrCreate([
            'ticket_category_id' => $bumCategory->id,
        ], [
            'schema' => [
                [
                    'name' => 'report_type',
                    'label' => 'Jenis Laporan',
                    'type' => 'select',
                    'options' => ['Permintaan', 'Temuan'],
                    'required' => true,
                ],
                [
                    'name' => 'location',
                    'label' => 'Lokasi',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'name' => 'detail_location',
                    'label' => 'Detail Lokasi',
                    'type' => 'text',
                    'required' => false,
                ],
                [
                    'name' => 'description',
                    'label' => 'Uraian Permintaan / Temuan',
                    'type' => 'textarea',
                    'required' => true,
                ],
                [
                    'name' => 'expected_action',
                    'label' => 'Ekspektasi Tindak Lanjut',
                    'type' => 'text',
                    'required' => false,
                ],
                [
                    'name' => 'reporter_phone',
                    'label' => 'Kontak Pelapor',
                    'type' => 'text',
                    'required' => false,
                ],
            ],
        ]);
    }
}
