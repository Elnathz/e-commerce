<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Returns page reskin (.planning/admin_pages_ux_plan.md, Fase B).
 * Adds an additive `q` search filter (return_number / order.order_number /
 * user.name) and a `stats` summary block ordered by severity
 * (Perlu Refund > Perlu Inspeksi > Menunggu Persetujuan).
 */
class AdminReturnIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function createOrder(string $orderNumber, User $user): Order
    {
        return Order::create([
            'order_number' => $orderNumber,
            'user_id' => $user->id,
            'status' => 'completed',
            'fulfillment_type' => 'delivery',
            'subtotal' => 90000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);
    }

    private function createReturn(string $returnNumber, Order $order, User $user, string $status): ReturnRequest
    {
        return ReturnRequest::create([
            'return_number' => $returnNumber,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => $status,
            'reason' => 'Test reason',
            'evidence_image_1' => 'test.jpg',
        ]);
    }

    public function test_search_matches_return_number_order_and_customer()
    {
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Budi Santoso']);

        $order1 = $this->createOrder('ORD-AAA111', $customer);
        $return1 = $this->createReturn('RET-XYZ001', $order1, $customer, 'submitted');

        $order2 = $this->createOrder('ORD-BBB222', $customer);
        $return2 = $this->createReturn('RET-OTHER002', $order2, $customer, 'submitted');

        $otherCustomer = User::factory()->create(['role' => 'customer', 'name' => 'Citra Lestari']);
        $order3 = $this->createOrder('ORD-CCC333', $otherCustomer);
        $return3 = $this->createReturn('RET-OTHER003', $order3, $otherCustomer, 'submitted');

        // Search by return_number.
        $response = $this->actingAs($this->admin)->get(route('admin.returns.index', ['q' => 'XYZ001']));
        $response->assertOk();
        $numbers = array_column($response->viewData('page')['props']['returns']['data'], 'return_number');
        $this->assertSame(['RET-XYZ001'], $numbers);

        // Search by order.order_number.
        $response = $this->actingAs($this->admin)->get(route('admin.returns.index', ['q' => 'BBB222']));
        $response->assertOk();
        $numbers = array_column($response->viewData('page')['props']['returns']['data'], 'return_number');
        $this->assertSame(['RET-OTHER002'], $numbers);

        // Search by user.name.
        $response = $this->actingAs($this->admin)->get(route('admin.returns.index', ['q' => 'Citra']));
        $response->assertOk();
        $numbers = array_column($response->viewData('page')['props']['returns']['data'], 'return_number');
        $this->assertSame(['RET-OTHER003'], $numbers);
    }

    public function test_stats_count_operational_statuses()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $order1 = $this->createOrder('ORD-S001', $customer);
        $this->createReturn('RET-S001', $order1, $customer, 'submitted');

        $order2 = $this->createOrder('ORD-S002', $customer);
        $this->createReturn('RET-S002', $order2, $customer, 'submitted');

        $order3 = $this->createOrder('ORD-S003', $customer);
        $this->createReturn('RET-S003', $order3, $customer, 'received');

        $order4 = $this->createOrder('ORD-S004', $customer);
        $this->createReturn('RET-S004', $order4, $customer, 'inspected');

        $order5 = $this->createOrder('ORD-S005', $customer);
        $this->createReturn('RET-S005', $order5, $customer, 'completed');

        $response = $this->actingAs($this->admin)->get(route('admin.returns.index'));

        $response->assertOk();
        $stats = $response->viewData('page')['props']['stats'];

        $this->assertSame(1, $stats['awaiting_refund']); // inspected
        $this->assertSame(1, $stats['awaiting_inspection']); // received
        $this->assertSame(2, $stats['awaiting_approval']); // submitted
    }

    public function test_non_admin_cannot_access()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('admin.returns.index'));

        $response->assertForbidden();
    }
}
