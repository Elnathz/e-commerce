<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FASE 4.3 / P1.1 — KPI deep-links ("Actionable KPI Links — filter tanggal ke
 * halaman terkait", implementation_plan.md). Adds optional `date_from`/`date_to`
 * query params (filtering on `created_at`) to the existing admin Orders and
 * Returns index endpoints, so dashboard KPI cards can deep-link into a list
 * scoped to the selected dashboard period. Pure additive list-filter — no
 * change to any financial calculation.
 */
class AdminListDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function createOrder(string $orderNumber, string $status, string $createdAt): Order
    {
        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id' => $this->admin->id,
            'status' => $status,
            'fulfillment_type' => 'delivery',
            'subtotal' => 90000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'qris',
            'payment_status' => $status === 'pending' ? 'unpaid' : 'paid',
        ]);

        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $order;
    }

    private function createReturn(string $returnNumber, Order $order, string $status, string $createdAt): ReturnRequest
    {
        $return = ReturnRequest::create([
            'return_number' => $returnNumber,
            'order_id' => $order->id,
            'user_id' => $this->admin->id,
            'status' => $status,
            'reason' => 'Test reason',
            'evidence_image_1' => 'test.jpg',
        ]);

        DB::table('return_requests')->where('id', $return->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $return;
    }

    public function test_orders_index_filters_by_date_range_via_created_at()
    {
        $this->createOrder('ORD-IN-RANGE', 'completed', '2026-06-05 10:00:00');
        $this->createOrder('ORD-OUT-OF-RANGE', 'completed', '2026-05-01 10:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-11',
        ]));

        $response->assertOk();
        $orders = $response->viewData('page')['props']['orders']['data'];
        $orderNumbers = array_column($orders, 'order_number');

        $this->assertContains('ORD-IN-RANGE', $orderNumbers);
        $this->assertNotContains('ORD-OUT-OF-RANGE', $orderNumbers);
    }

    public function test_orders_index_combines_status_and_date_filters()
    {
        $this->createOrder('ORD-COMPLETED-IN-RANGE', 'completed', '2026-06-05 10:00:00');
        $this->createOrder('ORD-PENDING-IN-RANGE', 'pending', '2026-06-05 10:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index', [
            'status' => 'completed',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-11',
        ]));

        $response->assertOk();
        $orders = $response->viewData('page')['props']['orders']['data'];
        $orderNumbers = array_column($orders, 'order_number');

        $this->assertContains('ORD-COMPLETED-IN-RANGE', $orderNumbers);
        $this->assertNotContains('ORD-PENDING-IN-RANGE', $orderNumbers);
    }

    public function test_orders_index_without_date_params_is_unaffected()
    {
        $this->createOrder('ORD-A', 'completed', '2026-06-05 10:00:00');
        $this->createOrder('ORD-B', 'completed', '2026-05-01 10:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index'));

        $response->assertOk();
        $orders = $response->viewData('page')['props']['orders']['data'];
        $orderNumbers = array_column($orders, 'order_number');

        $this->assertContains('ORD-A', $orderNumbers);
        $this->assertContains('ORD-B', $orderNumbers);
    }

    public function test_returns_index_filters_by_date_range_via_created_at()
    {
        $orderA = $this->createOrder('ORD-FOR-RETURN-A', 'completed', '2026-05-01 10:00:00');
        $orderB = $this->createOrder('ORD-FOR-RETURN-B', 'completed', '2026-05-01 10:00:00');

        $this->createReturn('RET-IN-RANGE', $orderA, 'submitted', '2026-06-05 10:00:00');
        $this->createReturn('RET-OUT-OF-RANGE', $orderB, 'submitted', '2026-05-01 10:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.returns.index', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-11',
        ]));

        $response->assertOk();
        $returns = $response->viewData('page')['props']['returns']['data'];
        $returnNumbers = array_column($returns, 'return_number');

        $this->assertContains('RET-IN-RANGE', $returnNumbers);
        $this->assertNotContains('RET-OUT-OF-RANGE', $returnNumbers);
    }
}
