<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Services\AnalyticsDashboardService;

class PriorityActionsFeedOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Test User', 'email' => 'priority-feed-' . uniqid() . '@test.com',
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

    /**
     * Acceptance test §2.5: 3 item lintas kategori dengan severity berbeda → urutan deterministik.
     *
     * - Return diterima 50 jam lalu (>= 48j threshold) → severity critical, category_weight=3.
     * - Order dibayar 30 jam lalu (>= 24j threshold) → severity critical, category_weight=2.
     * - Retur diajukan 1 jam lalu (< 18j = 75% dari 24j) → severity info, category_weight=1.
     *
     * Severity DOMINAN: kedua item critical (return_received & order_paid) harus berada
     * di atas item info (return_submitted), terlepas dari category_weight/age-nya.
     * Di antara dua item critical, category_weight memutus seri: return_received (3) > order_paid (2).
     */
    public function test_priority_score_orders_items_by_severity_then_category_then_age()
    {
        $userId = $this->makeUser();

        $orderPaidId = $this->makeOrder($userId, 'paid', now()->subHours(30));

        $orderForReturnSubmitted = $this->makeOrder($userId, 'completed', now()->subDays(5));
        $returnSubmittedId = $this->makeReturnRequest($userId, $orderForReturnSubmitted, 'submitted', [
            'created_at' => now()->subHours(1),
            'updated_at' => now()->subHours(1),
        ]);

        $orderForReturnReceived = $this->makeOrder($userId, 'completed', now()->subDays(5));
        $returnReceivedId = $this->makeReturnRequest($userId, $orderForReturnReceived, 'received', [
            'return_received_at' => now()->subHours(50),
        ]);

        $service = new AnalyticsDashboardService();
        $feed = $service->getPriorityActionsFeed();

        $types = collect($feed)->map(fn ($item) => $item['type'] . ':' . $item['id'])->values()->toArray();

        $expectedOrder = [
            'return_received:' . $returnReceivedId,
            'order_paid:' . $orderPaidId,
            'return_submitted:' . $returnSubmittedId,
        ];

        $this->assertEquals($expectedOrder, $types, 'Feed harus terurut: return_received (critical) > order_paid (critical) > return_submitted (info)');

        $byKey = collect($feed)->keyBy(fn ($item) => $item['type'] . ':' . $item['id']);

        $this->assertEquals('critical', $byKey['return_received:' . $returnReceivedId]['severity']);
        $this->assertEquals('critical', $byKey['order_paid:' . $orderPaidId]['severity']);
        $this->assertEquals('info', $byKey['return_submitted:' . $returnSubmittedId]['severity']);

        // Severity DOMINAN: priority_score critical-tier harus selalu > info-tier, tanpa syarat.
        $this->assertGreaterThan(
            $byKey['return_submitted:' . $returnSubmittedId]['priority_score'],
            $byKey['order_paid:' . $orderPaidId]['priority_score'],
        );
    }

    /**
     * category_weight sebagai tiebreaker: dua item bersseverity sama (info), tapi
     * category_weight order_paid (2) > return_submitted (1). Item order_paid harus
     * menang walau usianya LEBIH MUDA — membuktikan category_weight mengalahkan age.
     */
    public function test_priority_score_uses_category_weight_as_tiebreaker_within_same_severity()
    {
        $userId = $this->makeUser();

        // age=1h, < 18h (75% dari 24h) → info, category_weight=2
        $orderPaidId = $this->makeOrder($userId, 'paid', now()->subHours(1));

        $orderForReturn = $this->makeOrder($userId, 'completed', now()->subDays(5));
        // age=5h, < 18h → info, category_weight=1, tapi LEBIH TUA dari order_paid di atas
        $returnSubmittedId = $this->makeReturnRequest($userId, $orderForReturn, 'submitted', [
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        $service = new AnalyticsDashboardService();
        $feed = $service->getPriorityActionsFeed();
        $byKey = collect($feed)->keyBy(fn ($item) => $item['type'] . ':' . $item['id']);

        $this->assertEquals('info', $byKey['order_paid:' . $orderPaidId]['severity']);
        $this->assertEquals('info', $byKey['return_submitted:' . $returnSubmittedId]['severity']);

        $this->assertGreaterThan(
            $byKey['return_submitted:' . $returnSubmittedId]['priority_score'],
            $byKey['order_paid:' . $orderPaidId]['priority_score'],
            'order_paid (category_weight=2) harus mengalahkan return_submitted (category_weight=1) walau lebih muda',
        );
    }

    /**
     * age sebagai tiebreaker terakhir: dua item tipe & severity sama, yang lebih tua
     * (paid_at lebih lampau) harus berada di atas.
     */
    public function test_priority_score_uses_age_as_final_tiebreaker()
    {
        $userId = $this->makeUser();

        $olderOrderId = $this->makeOrder($userId, 'paid', now()->subHours(5));
        $newerOrderId = $this->makeOrder($userId, 'paid', now()->subHours(2));

        $service = new AnalyticsDashboardService();
        $feed = $service->getPriorityActionsFeed();
        $byKey = collect($feed)->keyBy(fn ($item) => $item['type'] . ':' . $item['id']);

        $this->assertEquals('info', $byKey['order_paid:' . $olderOrderId]['severity']);
        $this->assertEquals('info', $byKey['order_paid:' . $newerOrderId]['severity']);

        $this->assertGreaterThan(
            $byKey['order_paid:' . $newerOrderId]['priority_score'],
            $byKey['order_paid:' . $olderOrderId]['priority_score'],
            'Order yang lebih tua (5 jam) harus diatas order yang lebih muda (2 jam) saat severity & category sama',
        );
    }
}
