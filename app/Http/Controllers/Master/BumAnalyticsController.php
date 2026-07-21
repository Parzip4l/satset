<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\ConsumableItem;
use App\Models\Master\Department;
use App\Services\Bum\BumAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BumAnalyticsController extends Controller
{
    public function __construct(private BumAnalyticsService $analytics)
    {
    }

    public function index()
    {
        $items = ConsumableItem::where('is_active', true)->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('bum.analytics', compact('items', 'departments'));
    }

    public function summary(Request $request): JsonResponse
    {
        $filters = $this->analytics->filters($request->all());

        return response()->json(['success' => true, 'data' => $this->analytics->summary($filters)]);
    }

    public function usageTrend(Request $request): JsonResponse
    {
        $filters = $this->analytics->filters($request->all());

        return response()->json(['success' => true, 'data' => $this->analytics->usageTrend($filters)]);
    }

    public function usageForecast(Request $request): JsonResponse
    {
        $filters = $this->analytics->filters($request->all());

        return response()->json(['success' => true, 'data' => $this->analytics->usageForecast($filters)]);
    }

    public function stockForecast(Request $request): JsonResponse
    {
        $filters = $this->analytics->filters($request->all());

        return response()->json(['success' => true, 'data' => $this->analytics->stockForecast($filters)]);
    }

    public function requestTrend(Request $request): JsonResponse
    {
        $filters = $this->analytics->filters($request->all());

        return response()->json(['success' => true, 'data' => $this->analytics->requestTrend($filters)]);
    }

    public function meetingConsumptionTrend(Request $request): JsonResponse
    {
        $filters = $this->analytics->filters($request->all());

        return response()->json(['success' => true, 'data' => $this->analytics->meetingConsumptionTrend($filters)]);
    }

    public function receivingTrend(Request $request): JsonResponse
    {
        $filters = $this->analytics->filters($request->all());

        return response()->json(['success' => true, 'data' => $this->analytics->receivingTrend($filters)]);
    }

    public function procurementRecommendation(Request $request): JsonResponse
    {
        $filters = $this->analytics->filters($request->all());

        return response()->json(['success' => true, 'data' => $this->analytics->procurementRecommendation($filters)]);
    }
}
