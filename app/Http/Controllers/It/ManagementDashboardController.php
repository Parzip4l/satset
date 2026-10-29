<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Services\It\ManagementDashboard;
use App\Support\ItAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class ManagementDashboardController extends Controller
{
    public function index(Request $request, ManagementDashboard $dashboard)
    {
        abort_unless(ItAccess::operational($request->user()) || ItAccess::can($request->user(), 'view_it_queue') || ItAccess::can($request->user(), 'manage_it_configuration'), 403);
        abort_unless(Schema::hasTable('it_sla_clocks'), 503, 'Data SLA belum tersedia.');
        $today = CarbonImmutable::now('Asia/Jakarta');
        $filters = $request->validate([
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'record_type' => 'nullable|in:incident,service_request', 'priority' => 'nullable|in:P1,P2,P3,P4',
            'support_team_id' => 'nullable|integer|min:1',
            'assigned_user_id' => 'nullable|integer|min:1', 'service_key' => 'nullable|string|max:50', 'category_key' => 'nullable|string|max:50',
            'status' => 'nullable|in:submitted,'.implode(',', \App\Services\It\TicketWorkflow::STATES),
            'detail' => 'nullable|in:incoming,resolved,backlog,near,breached',
            'export' => 'nullable|in:csv',
        ]);
        $filters['from'] = $filters['from'] ?? $today->startOfMonth()->toDateString();
        $filters['to'] = $filters['to'] ?? $today->toDateString();
        $start = CarbonImmutable::parse($filters['from'], 'Asia/Jakarta');
        $end = CarbonImmutable::parse($filters['to'], 'Asia/Jakarta');
        abort_if($start->gt($end) || $start->diffInDays($end) > 365, 422, 'Periode tidak valid atau lebih dari 366 hari.');
        if (! empty($filters['detail']) && empty($filters['export'])) {
            $metric = $filters['detail'];
            $tickets = $dashboard->detailScope($request->user(), $filters, $metric)->with(['itDetail.supportTeam', 'assignedUser'])->orderByDesc('requests.created_at')->paginate(20)->withQueryString();
            return view('it.management-detail', compact('filters', 'metric', 'tickets'));
        }
        if (($filters['export'] ?? null) === 'csv') {
            $fromUtc = $start->startOfDay()->setTimezone(config('app.timezone', 'Asia/Jakarta'));
            $toUtc = $end->addDay()->startOfDay()->setTimezone(config('app.timezone', 'Asia/Jakarta'));
            $tickets = $dashboard->scope($request->user(), $filters)
                ->where('requests.created_at', '>=', $fromUtc)->where('requests.created_at', '<', $toUtc)
                ->with(['itDetail.supportTeam', 'assignedUser'])->orderBy('requests.id')->limit(5001)->get();
            $truncated = $tickets->count() > 5000;
            Log::info('IT dashboard CSV exported', ['user_id' => $request->user()->id, 'filters' => $filters, 'rows' => min(5000, $tickets->count()), 'truncated' => $truncated]);

            return response()->streamDownload(function () use ($tickets, $filters, $truncated) {
                $out = fopen('php://output', 'w');
                $safe = fn ($value) => preg_match('/^[\s\x00-\x1f]*[=+@\-]/u', (string) $value) ? "'".(string) $value : (string) $value;
                fputcsv($out, ['Laporan tiket masuk IT', 'Tanggal Jakarta', $filters['from'], $filters['to']]);
                fputcsv($out, ['Lingkup', 'Sesuai akses pengguna', 'Dibuat', CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d H:i:s').' WIB']);
                fputcsv($out, ['Filter', 'Jenis: '.($filters['record_type'] ?? 'semua'), 'Prioritas: '.($filters['priority'] ?? 'semua'), 'Tim ID: '.($filters['support_team_id'] ?? 'semua'), 'Layanan: '.($filters['service_key'] ?? 'semua'), 'Kategori: '.($filters['category_key'] ?? 'semua'), 'PIC ID: '.($filters['assigned_user_id'] ?? 'semua'), 'Status: '.($filters['status'] ?? 'semua')]);
                fputcsv($out, ['Catatan', $truncated ? 'Dibatasi 5000 baris. Persempit filter untuk mendapatkan seluruh data.' : 'Semua tiket masuk yang sesuai filter.']);
                fputcsv($out, ['Nomor tiket', 'Judul', 'Jenis', 'Prioritas saat ini', 'Status saat ini', 'Tim saat ini', 'PIC saat ini', 'Tanggal masuk WIB']);
                foreach ($tickets->take(5000) as $ticket) {
                    fputcsv($out, array_map($safe, [$ticket->ticket_no, $ticket->title, $ticket->itDetail->record_type, $ticket->itDetail->priority, $ticket->itDetail->status, $ticket->itDetail->supportTeam?->name, $ticket->assignedUser?->name, $ticket->created_at->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s')]));
                }
                fclose($out);
            }, 'laporan-tiket-it.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }
        $data = $dashboard->build($request->user(), $filters);
        return view('it.management-dashboard', $data);
    }
}
