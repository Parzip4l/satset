<?php 

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use App\Models\Master\Ticket as RequestModel; // gunakan alias karena Request sudah ada
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $selectedYear = $request->tahun ?? Carbon::now()->year;

        // Base query: Admin = semua, PIC = miliknya
        $requestsQuery = RequestModel::query();
        if(strtolower($user->role) !== 'admin') {
            $requestsQuery->where('requester_id', $user->id);
        }

        // Total requests
        $totalRequests = (clone $requestsQuery)->count();

        // Status distribution
        $statusCounts = (clone $requestsQuery)
            ->join('statuses', 'requests.status_id', '=', 'statuses.id')
            ->select('statuses.name', DB::raw('COUNT(requests.id) as total'))
            ->groupBy('statuses.name')
            ->pluck('total', 'statuses.name')
            ->toArray();

        $statusLabels = array_keys($statusCounts);
        $statusSeries = array_values($statusCounts);

        $openRequests = $statusCounts['Open'] ?? 0;
        $inProgressRequests = $statusCounts['In Progress'] ?? 0;
        $closedRequests = $statusCounts['Closed'] ?? 0;

        // Recent requests
        $recentRequests = (clone $requestsQuery)
            ->with(['requester','department','status'])
            ->orderByDesc('requests.created_at') // <-- prefix tabel agar tidak ambiguous
            ->take(5)
            ->get();

        // Statistik bulanan
        $monthlyLabels = [];
        $monthlyData = [];
        for($i=1; $i<=12; $i++){
            $monthlyLabels[] = Carbon::create()->month($i)->format('M');
            $monthlyData[] = (clone $requestsQuery)
                                ->whereMonth('requests.created_at',$i)
                                ->whereYear('requests.created_at',$selectedYear)
                                ->count();
        }

        // Kategori distribution
        $kategoriData = (clone $requestsQuery)
            ->join('problem_categories','requests.category_id','=','problem_categories.id')
            ->select('problem_categories.name', DB::raw('COUNT(requests.id) as total'))
            ->groupBy('problem_categories.name')
            ->pluck('total','problem_categories.name')
            ->toArray();

        // Tahun tersedia
        $availableYears = RequestModel::selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        return view('dashboard.index', compact(
            'totalRequests','openRequests','inProgressRequests','closedRequests',
            'statusLabels','statusSeries',
            'recentRequests','monthlyLabels','monthlyData','kategoriData',
            'availableYears','selectedYear'
        ));
    }
}
