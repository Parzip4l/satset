<?php

namespace Tests\Unit;

use App\Services\Bum\ForecastHelper;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BumForecastHelperTest extends TestCase
{
    public function test_average_usage_is_calculated_from_period_days(): void
    {
        $helper = new ForecastHelper();

        $this->assertSame(3.0, $helper->calculateAverageUsage([10, 20], 10));
    }

    public function test_forecast_is_safe_when_history_is_empty(): void
    {
        $helper = new ForecastHelper();

        $this->assertSame([0.0, 0.0, 0.0], $helper->forecastSeries([], 3));
    }

    public function test_stock_out_date_is_null_when_average_usage_is_zero(): void
    {
        $helper = new ForecastHelper();

        $this->assertNull($helper->calculateStockOutDate(10, 0));
    }

    public function test_stock_out_date_uses_daily_average(): void
    {
        $helper = new ForecastHelper();
        $today = Carbon::create(2026, 6, 17);

        $this->assertSame('2026-06-22', $helper->calculateStockOutDate(10, 2, $today)->toDateString());
    }

    public function test_risk_level_is_critical_when_stock_is_below_minimum(): void
    {
        $helper = new ForecastHelper();

        $this->assertSame('CRITICAL', $helper->calculateRiskLevel(5, 5, 90));
    }

    public function test_recommendation_qty_never_negative(): void
    {
        $helper = new ForecastHelper();

        $this->assertSame(0, $helper->calculateRecommendedPurchaseQty(100, 10, 20, 1, 30));
    }
}
