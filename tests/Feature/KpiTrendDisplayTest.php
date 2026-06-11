<?php

namespace Tests\Feature;

use App\Services\AnalyticsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * §1a / tracker #45 (DESIGN-LOCKED): structured KPI trend display.
 *
 * Pure display change — calculateTrend() now returns a structured array instead of
 * a raw percentage. Underlying current/previous values are untouched, so this is
 * financial-safe, but every branch of the new contract still needs coverage:
 *
 *   Bucket A (currency/count):
 *     prev==0 && cur==0  -> {type: 'none',  display: '—'}
 *     prev==0 && cur>0   -> {type: 'new',   display: 'Baru'}
 *     prev>0, |pct|<=200 -> {type: 'percent', display: '<sign><pct>%'}
 *     prev>0, |pct|>200  -> {type: 'delta', display: '<sign>Rp <abs>' or '<sign><abs>'}
 *
 *   Bucket B (rate, e.g. checkout_to_paid_rate / order_return_rate):
 *     always              -> {type: 'delta_points', display: '<sign><abs> pp'}
 */
class KpiTrendDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function calculateTrend(float $current, float $previous, string $bucket = 'count'): array
    {
        $service = new AnalyticsDashboardService();
        $method = new ReflectionMethod(AnalyticsDashboardService::class, 'calculateTrend');
        $method->setAccessible(true);

        return $method->invoke($service, $current, $previous, $bucket);
    }

    private function resolveComparisonLabel(string $period, string $comparePeriod, string $prevStart, string $prevEnd): string
    {
        $service = new AnalyticsDashboardService();

        return $service->resolveComparisonLabel($period, $comparePeriod, $prevStart, $prevEnd);
    }

    public function test_bucket_a_returns_none_when_both_current_and_previous_are_zero()
    {
        $trend = $this->calculateTrend(0, 0, 'currency');

        $this->assertSame('none', $trend['type']);
        $this->assertNull($trend['value']);
        $this->assertSame('—', $trend['display']);
    }

    public function test_bucket_a_returns_baru_when_previous_is_zero_and_current_is_positive()
    {
        $trend = $this->calculateTrend(50000, 0, 'currency');

        $this->assertSame('new', $trend['type']);
        $this->assertNull($trend['value']);
        $this->assertSame('Baru', $trend['display']);
    }

    public function test_bucket_a_returns_percent_when_within_200_percent_threshold()
    {
        // 100 -> 133 = +33% (well within +-200%)
        $trend = $this->calculateTrend(133, 100, 'count');

        $this->assertSame('percent', $trend['type']);
        $this->assertEquals(33.0, $trend['value']);
        $this->assertSame('+33%', $trend['display']);
    }

    public function test_bucket_a_returns_negative_percent_for_a_decrease()
    {
        // 100 -> 92 = -8%
        $trend = $this->calculateTrend(92, 100, 'count');

        $this->assertSame('percent', $trend['type']);
        $this->assertEquals(-8.0, $trend['value']);
        $this->assertSame('-8%', $trend['display']);
    }

    public function test_bucket_a_currency_switches_to_raw_delta_beyond_200_percent_threshold()
    {
        // 1.000.000 -> 4.450.000 = +345% (> 200%) -> raw delta in Rupiah
        $trend = $this->calculateTrend(4450000, 1000000, 'currency');

        $this->assertSame('delta', $trend['type']);
        $this->assertEquals(3450000, $trend['value']);
        $this->assertSame('+Rp 3.450.000', $trend['display']);
    }

    public function test_bucket_a_count_switches_to_raw_delta_beyond_200_percent_threshold()
    {
        // 10 -> 43 = +330% (> 200%) -> raw delta as plain number
        $trend = $this->calculateTrend(43, 10, 'count');

        $this->assertSame('delta', $trend['type']);
        $this->assertEquals(33, $trend['value']);
        $this->assertSame('+33', $trend['display']);
    }

    public function test_bucket_a_negative_delta_beyond_200_percent_threshold_is_signed_minus()
    {
        // 100 -> 0 = -100% (within threshold, NOT >200%) -> still percent
        $trend = $this->calculateTrend(0, 100, 'count');

        $this->assertSame('percent', $trend['type']);
        $this->assertEquals(-100.0, $trend['value']);
        $this->assertSame('-100%', $trend['display']);
    }

    public function test_bucket_a_exactly_200_percent_stays_as_percent_not_delta()
    {
        // 100 -> 300 = exactly +200% -> threshold is "> 200", so this stays percent
        $trend = $this->calculateTrend(300, 100, 'count');

        $this->assertSame('percent', $trend['type']);
        $this->assertSame('+200%', $trend['display']);
    }

    public function test_bucket_b_rate_always_returns_delta_points()
    {
        // 70% -> 78% = +8 percentage points (NOT +11.4% ratio-of-ratio)
        $trend = $this->calculateTrend(78, 70, 'rate');

        $this->assertSame('delta_points', $trend['type']);
        $this->assertEquals(8.0, $trend['value']);
        $this->assertSame('+8 pp', $trend['display']);
    }

    public function test_bucket_b_rate_returns_negative_delta_points_for_a_decrease()
    {
        // 12% -> 9% = -3 percentage points
        $trend = $this->calculateTrend(9, 12, 'rate');

        $this->assertSame('delta_points', $trend['type']);
        $this->assertEquals(-3.0, $trend['value']);
        $this->assertSame('-3 pp', $trend['display']);
    }

    public function test_bucket_b_rate_handles_zero_previous_without_becoming_baru_or_percent()
    {
        // Bucket B never falls into the "Baru"/"—" cases — always a delta in points.
        $trend = $this->calculateTrend(5, 0, 'rate');

        $this->assertSame('delta_points', $trend['type']);
        $this->assertEquals(5.0, $trend['value']);
        $this->assertSame('+5 pp', $trend['display']);
    }

    public function test_get_financial_metrics_returns_structured_trend_objects_and_comparison_label()
    {
        $service = new AnalyticsDashboardService();
        $metrics = $service->getFinancialMetrics('month');

        $this->assertArrayHasKey('comparison_label', $metrics);
        $this->assertIsString($metrics['comparison_label']);

        foreach (['gross_sales_trend', 'total_refund_trend', 'total_orders_trend', 'checkout_created_trend', 'checkout_to_paid_rate_trend', 'order_return_rate_trend'] as $field) {
            $this->assertIsArray($metrics[$field], "{$field} should be a structured trend array");
            $this->assertArrayHasKey('type', $metrics[$field]);
            $this->assertArrayHasKey('value', $metrics[$field]);
            $this->assertArrayHasKey('display', $metrics[$field]);
        }

        $logistics = $metrics['logistics_performance'];
        foreach (['avg_shipping_cost_trend', 'avg_delivery_days_trend'] as $field) {
            $this->assertIsArray($logistics[$field], "{$field} should be a structured trend array");
            $this->assertArrayHasKey('type', $logistics[$field]);
        }
    }

    public function test_resolve_comparison_label_for_known_periods()
    {
        $this->assertSame('vs 7 hari sebelumnya', $this->resolveComparisonLabel('7days', 'previous_period', '2026-05-01', '2026-05-07'));
        $this->assertSame('vs bulan lalu', $this->resolveComparisonLabel('month', 'previous_period', '2026-05-01', '2026-05-31'));
        $this->assertSame('vs tahun lalu', $this->resolveComparisonLabel('month', 'previous_year', '2025-06-01', '2025-06-30'));
    }
}
