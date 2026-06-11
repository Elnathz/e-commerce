<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Services\AnalyticsDashboardService;

/**
 * §2.7 Today Focus Summary — roll-up dari priority_score (§2.5/#49), tanpa backend
 * baru: agregasi $feed (full, sebelum slice 10), sla_breaches, dan low_stock_count
 * yang sudah dihitung getOperationalMetrics(). Lihat tracker #51.
 */
class TodayFocusSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Test User', 'email' => 'today-focus-' . uniqid() . '@test.com',
            'password' => bcrypt('password'), 'role' => 'customer',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeOrder(int $userId, string $status, $paidAt = null): int
    {
        return DB::table('orders')->insertGetId([
            'order_number' => 'ORD-' . uniqid(), 'user_id' => $userId, 'status' => $status,
            'payment_status' => $paidAt ? 'paid' : 'unpaid',
            'subtotal' => 100000, 'total_amount' => 100000, 'fulfillment_type' => 'delivery',
            'paid_at' => $paidAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeReturnRequest(int $userId, int $orderId, string $status, array $extra = []): int
    {
        return DB::table('return_requests')->insertGetId(array_merge([
            'return_number' => 'RET-' . uniqid(), 'order_id' => $orderId, 'user_id' => $userId,
            'status' => $status, 'reason' => 'Barang rusak', 'evidence_image_1' => 'evidence/a.jpg',
            'created_at' => now(), 'updated_at' => now(),
        ], $extra));
    }

    private function makeCategory(): int
    {
        return DB::table('categories')->insertGetId([
            'name' => 'Kategori Test', 'slug' => 'kategori-test-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Produk dengan stok kritis: (stock - reserved_stock) <= LOW_STOCK_THRESHOLD (default 5). */
    private function makeLowStockProduct(int $categoryId): int
    {
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'name' => 'Produk Test ' . uniqid(),
            'slug' => 'produk-test-' . uniqid(), 'base_price' => 50000, 'weight_gram' => 1000,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('product_variants')->insert([
            'product_id' => $productId, 'sku' => 'SKU-' . uniqid(), 'name' => 'Default',
            'price' => 50000, 'stock' => 2, 'reserved_stock' => 0, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $productId;
    }

    public function test_is_all_clear_when_nothing_needs_attention()
    {
        $service = new AnalyticsDashboardService();
        $metrics = $service->getOperationalMetrics();

        $this->assertSame([], $metrics['today_focus']['items']);
        $this->assertTrue($metrics['today_focus']['is_all_clear']);
    }

    public function test_order_paid_overdue_appears_in_today_focus_and_matches_sla_breaches()
    {
        $userId = $this->makeUser();
        $this->makeOrder($userId, 'paid', now()->subHours(30)); // > 24h threshold → critical

        $service = new AnalyticsDashboardService();
        $metrics = $service->getOperationalMetrics();

        $byKey = collect($metrics['today_focus']['items'])->keyBy('key');

        $this->assertFalse($metrics['today_focus']['is_all_clear']);
        $this->assertTrue($byKey->has('order_paid_overdue'));
        $this->assertSame(1, $byKey['order_paid_overdue']['count']);
        $this->assertSame('critical', $byKey['order_paid_overdue']['severity']);
        $this->assertSame(['order_paid'], $byKey['order_paid_overdue']['filter_types']);
        $this->assertSame($metrics['sla_breaches']['order_paid_overdue'], $byKey['order_paid_overdue']['count']);

        // Integritas §2.7-C: item yang dihitung today_focus harus ada & severity sama di priority_actions.
        $feedItem = collect($metrics['priority_actions'])->firstWhere('type', 'order_paid');
        $this->assertNotNull($feedItem);
        $this->assertSame('critical', $feedItem['severity']);
    }

    public function test_return_sla_overdue_combines_submitted_and_received()
    {
        $userId = $this->makeUser();

        $orderForSubmitted = $this->makeOrder($userId, 'completed', now()->subDays(5));
        $this->makeReturnRequest($userId, $orderForSubmitted, 'submitted', [
            'created_at' => now()->subHours(30), // > 24h threshold → critical
            'updated_at' => now()->subHours(30),
        ]);

        $orderForReceived = $this->makeOrder($userId, 'completed', now()->subDays(5));
        $this->makeReturnRequest($userId, $orderForReceived, 'received', [
            'return_received_at' => now()->subHours(50), // > 48h threshold → critical
        ]);

        $service = new AnalyticsDashboardService();
        $metrics = $service->getOperationalMetrics();

        $byKey = collect($metrics['today_focus']['items'])->keyBy('key');

        $this->assertTrue($byKey->has('return_sla_overdue'));
        $this->assertSame(2, $byKey['return_sla_overdue']['count']);
        $this->assertSame('critical', $byKey['return_sla_overdue']['severity']);
        $this->assertSame(['return_submitted', 'return_received'], $byKey['return_sla_overdue']['filter_types']);
        $this->assertSame(
            $metrics['sla_breaches']['return_submitted_overdue'] + $metrics['sla_breaches']['return_received_overdue'],
            $byKey['return_sla_overdue']['count'],
        );
    }

    public function test_low_stock_item_uses_warning_severity_below_critical_threshold()
    {
        $categoryId = $this->makeCategory();
        $this->makeLowStockProduct($categoryId);
        $this->makeLowStockProduct($categoryId);

        $service = new AnalyticsDashboardService();
        $metrics = $service->getOperationalMetrics();

        $byKey = collect($metrics['today_focus']['items'])->keyBy('key');

        $this->assertFalse($metrics['today_focus']['is_all_clear']);
        $this->assertTrue($byKey->has('low_stock'));
        $this->assertSame(2, $byKey['low_stock']['count']);
        $this->assertSame('warning', $byKey['low_stock']['severity']);
        $this->assertSame($metrics['low_stock_count'], $byKey['low_stock']['count']);
        $this->assertNull($byKey['low_stock']['filter_types']);
    }

    public function test_approaching_sla_warning_item_from_feed_severity()
    {
        $userId = $this->makeUser();
        // age=20h: >= 75% of 24h (18h) but < 24h → severity warning
        $this->makeOrder($userId, 'paid', now()->subHours(20));

        $service = new AnalyticsDashboardService();
        $metrics = $service->getOperationalMetrics();

        $byKey = collect($metrics['today_focus']['items'])->keyBy('key');

        $this->assertFalse($metrics['today_focus']['is_all_clear']);
        $this->assertSame(0, $metrics['sla_breaches']['order_paid_overdue']);
        $this->assertFalse($byKey->has('order_paid_overdue'));
        $this->assertTrue($byKey->has('approaching_sla'));
        $this->assertSame(1, $byKey['approaching_sla']['count']);
        $this->assertSame('warning', $byKey['approaching_sla']['severity']);
    }
}
