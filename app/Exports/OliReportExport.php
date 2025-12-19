<?php

namespace App\Exports;

use App\Models\Oli;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class OliReportExport implements FromCollection, WithHeadings, WithCalculatedFormulas
{
    protected $dataOli;

    public function __construct($dataOli)
    {
        $this->dataOli = $dataOli;
    }

    public function collection()
    {
        // Menambahkan total ke akhir data
        $total = $this->dataOli->sum('total'); // Menghitung total dari kolom 'total'
        
        // Menambahkan baris dengan total ke dalam koleksi
        $this->dataOli->push([
            'id' => '',
            'tanggal' => '',
            'pengirim' => '',
            'jenis_oli' => 'TOTAL', // Label untuk total
            'jumlah' => '',
            'harga' => '',
            'total' => $total, // Menampilkan total di kolom terakhir
        ]);
        
        return $this->dataOli;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal',
            'Pengirim',
            'Jenis Oli',
            'Jumlah',
            'Harga',
            'Total',
        ];
    }
}
