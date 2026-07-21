<?php

namespace App\Services\Bum;

use Carbon\Carbon;

class ForecastHelper
{
    public function calculateAverageUsage(array $data, int $periodDays): float
    {
        if ($periodDays <= 0) {
            return 0;
        }

        return round(array_sum($data) / $periodDays, 2);
    }

    public function calculateMovingAverage(array $data, int $windowSize): array
    {
        $windowSize = max(1, $windowSize);
        $series = [];

        foreach (array_values($data) as $index => $value) {
            $window = array_slice($data, max(0, $index - $windowSize + 1), $windowSize);
            $series[] = round(array_sum($window) / max(1, count($window)), 2);
        }

        return $series;
    }

    public function forecastSeries(array $historicalValues, int $forecastDays, int $windowSize = 7): array
    {
        $forecastDays = max(1, $forecastDays);
        $windowSize = max(1, $windowSize);
        $values = array_values($historicalValues);
        $forecast = [];

        for ($i = 0; $i < $forecastDays; $i++) {
            $window = array_slice($values, -$windowSize);
            $next = count($window) > 0 ? round(array_sum($window) / count($window), 2) : 0.0;
            $forecast[] = $next;
            $values[] = $next;
        }

        return $forecast;
    }

    public function calculateStockOutDate(int $currentStock, float $averageDailyUsage, ?Carbon $today = null): ?Carbon
    {
        if ($averageDailyUsage <= 0) {
            return null;
        }

        $days = (int) ceil($currentStock / $averageDailyUsage);

        return ($today ?: now())->copy()->addDays($days);
    }

    public function calculateRecommendedPurchaseQty(int $currentStock, int $minimumStock, int $bufferStock, float $averageDailyUsage, int $forecastDays): int
    {
        $targetStock = max($bufferStock, $averageDailyUsage * max(1, $forecastDays)) + $minimumStock;
        $recommendedQty = (int) ceil($targetStock - $currentStock);

        return max(0, $recommendedQty);
    }

    public function calculateRiskLevel(int $currentStock, int $minimumStock, ?float $daysUntilStockOut): string
    {
        if ($currentStock <= $minimumStock) {
            return 'CRITICAL';
        }

        if ($daysUntilStockOut === null) {
            return 'NO_DATA';
        }

        if ($daysUntilStockOut <= 7) {
            return 'HIGH';
        }

        if ($daysUntilStockOut <= 30) {
            return 'MEDIUM';
        }

        return 'LOW';
    }
}
