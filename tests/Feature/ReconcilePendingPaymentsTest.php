<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class ReconcilePendingPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = User::factory()->create()->id;
    }

    public function test_case_a_pending_to_paid()
    {
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-1', 'user_id' => $this->userId, 'status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => 100, 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)
        ]);

        Artisan::call('payment:reconcile');

        $order = Order::find($orderId);
        $this->assertEquals('paid', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_case_b_cancelled_stays_cancelled()
    {
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-2', 'user_id' => $this->userId, 'status' => 'cancelled', 'payment_status' => 'failed', 'subtotal' => 100, 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)
        ]);

        Artisan::call('payment:reconcile');

        $this->assertEquals('cancelled', Order::find($orderId)->status);
    }

    public function test_case_c_refunded_stays_refunded()
    {
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-3', 'user_id' => $this->userId, 'status' => 'refunded', 'payment_status' => 'refunded', 'subtotal' => 100, 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)
        ]);

        Artisan::call('payment:reconcile');

        $this->assertEquals('refunded', Order::find($orderId)->status);
    }

    /**
     * Order pending for only 10 minutes is outside the 1-hour reconciliation
     * window and must be left alone — avoids prematurely marking orders the
     * customer is still actively paying for.
     */
    public function test_case_d_recent_pending_order_not_touched()
    {
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-4', 'user_id' => $this->userId, 'status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => 100, 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)
        ]);

        $exitCode = Artisan::call('payment:reconcile');

        $this->assertEquals(0, $exitCode);

        $order = Order::find($orderId);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);
    }
}
