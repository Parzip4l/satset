<?php

namespace App\Exports;

use App\Models\Po\PurchaseOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseOrdersExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return PurchaseOrder::with(['distributor','items.warehouseItem'])
            ->get()
            ->map(function($order) {
                // Gabungkan item jadi string: "Nama Item (Qty), ..."
                $itemsString = $order->items->map(function($item) {
                    $name = ($item->warehouseItem->name ?? '-') . ' ' . ($item->warehouseItem->type ?? '-');
                    $qty = $item->quantity ?? '-';
                    return "{$name} ({$qty})";
                })->implode(', ');

                return [
                    'PO Number' => $order->po_number,
                    'Vendor'    => $order->distributor->name ?? '-',
                    'Total'     => $order->total,
                    'Status'    => $order->status,
                    'PO Date'   => $order->po_date,
                    'Due Date'  => $order->due_date,
                    'Items'     => $itemsString,
                ];
            });
    }


    public function headings(): array
    {
        return ['PO Number', 'Vendor', 'Total', 'Status', 'PO Date', 'Due Date', 'Items'];
    }
}
