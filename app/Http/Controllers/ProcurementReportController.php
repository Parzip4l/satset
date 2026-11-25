<?php

namespace App\Http\Controllers;

use App\Services\ProcurementReportService;
use App\Support\ProcurementAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class ProcurementReportController extends Controller
{
    private function filters(Request $request): array
    {
        abort_unless(ProcurementAccess::allowed($request->user()), 403);
        $filters = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'department' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(array_keys(ProcurementReportService::STATUSES))],
            'ranking' => ['nullable', Rule::in(array_keys(ProcurementReportService::RANKINGS))],
            'page' => 'nullable|integer|min:1',
        ]);
        $filters['from'] = $filters['from'] ?? now()->startOfYear()->toDateString();
        $filters['to'] = $filters['to'] ?? now()->toDateString();
        $filters['ranking'] = $filters['ranking'] ?? 'budget';
        validator($filters, ['to' => 'after_or_equal:from'])->validate();

        return $filters;
    }

    public function index(Request $request, ProcurementReportService $reports)
    {
        $filters = $this->filters($request);
        $report = $reports->build($filters);
        $details = new LengthAwarePaginator($report['rows']->forPage($request->integer('page', 1), 20)->values(), $report['rows']->count(), 20, $request->integer('page', 1), [
            'path' => route('procurement.reports'), 'query' => $request->query(),
        ]);

        return view('procurement.reports', compact('filters', 'report', 'details'));
    }

    public function export(Request $request, ProcurementReportService $reports)
    {
        $filters = $this->filters($request);
        $report = $reports->build($filters);
        $kind = $request->validate(['kind' => ['nullable', Rule::in(['departments', 'requests'])]])['kind'] ?? 'departments';

        return response()->streamDownload(function () use ($filters, $report, $kind) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            $write = function (array $row) use ($stream) {
                // Treat user-entered text as text when opened in a spreadsheet.
                fputcsv($stream, array_map(fn ($cell) => is_string($cell) && preg_match('/^[\s]*[=+@-]/u', $cell) ? "'".$cell : $cell, $row), ';', '"', '');
            };
            $money = fn ($cents) => number_format($cents / 100, 2, '.', '');
            $write(['Laporan Pengadaan Barang', 'Tanggal pengajuan '.$filters['from'].' s.d. '.$filters['to']]);
            $write(['Dibuat pada', $report['generated_at']->format('Y-m-d H:i:s'), 'Progres dan dana kumulatif hingga laporan dibuat']);
            $write(['Departemen', $filters['department'] ?? 'Semua', 'Status', $filters['status'] ?? 'Semua']);
            if ($kind === 'departments') {
                $write(['Departemen', 'Jumlah pengajuan', 'Anggaran diajukan (Rp)', 'Anggaran disetujui (Rp)', 'Dana diberikan (Rp)', 'Estimasi kebutuhan terbuka (Rp)', 'Qty stok diserahkan', 'Selesai', 'Aktif', 'Ditolak']);
                foreach ($report['departmentRows'] as $row) {
                    $write([$row['label'], $row['count'], $money($row['budget']), $money($row['approved_budget']), $money($row['funds']), $money($row['outstanding_budget']), $row['stock_quantity'], $row['completed'], $row['active'], $row['rejected']]);
                }
            } else {
                $write(['Nomor pengajuan', 'Tanggal pengajuan', 'Pemohon', 'Departemen', 'Kebutuhan', 'Status', 'Anggaran diajukan (Rp)', 'Dana diberikan (Rp)', 'Estimasi kebutuhan terbuka (Rp)', 'Qty stok diserahkan', 'Qty dicakup dana', 'Sisa qty kebutuhan']);
                foreach ($report['rows'] as $row) {
                    $write([$row['ticket_no'], $row['submitted_at']->format('Y-m-d'), $row['requester_name'], $row['department'], $row['title'], $row['status_label'], $money($row['budget']), $money($row['funds']), $money($row['outstanding_budget']), $row['stock_quantity'], $row['funded_quantity'], $row['active'] ? $row['remaining_quantity'] : 0]);
                }
            }
            fclose($stream);
        }, 'laporan-pengadaan-'.$kind.'-'.$filters['from'].'-'.$filters['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
