<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Bum\BumAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BumAnalyticsApiController extends Controller
{
    public function __construct(private BumAnalyticsService $analytics)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analytics->summary($this->analytics->filters($request->all()))]);
    }

    public function usageTrend(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analytics->usageTrend($this->analytics->filters($request->all()))]);
    }

    public function usageForecast(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analytics->usageForecast($this->analytics->filters($request->all()))]);
    }

    public function stockForecast(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analytics->stockForecast($this->analytics->filters($request->all()))]);
    }

    public function requestTrend(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analytics->requestTrend($this->analytics->filters($request->all()))]);
    }

    public function meetingConsumptionTrend(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analytics->meetingConsumptionTrend($this->analytics->filters($request->all()))]);
    }

    public function receivingTrend(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analytics->receivingTrend($this->analytics->filters($request->all()))]);
    }

    public function procurementRecommendation(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->analytics->procurementRecommendation($this->analytics->filters($request->all()))]);
    }
}
