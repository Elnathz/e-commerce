<?php

namespace Tests\Feature;

use App\Helpers\MigrationHelpers;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MigrationBackfillTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-BF-' . uniqid(),
            'user_id' => $this->user->id,
            'status' => 'paid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 100000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 110000,
            'payment_status' => 'paid',
            'paid_at' => null,
        ], $overrides));
    }

    private function createPayment(Order $order, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'order_id' => $order->id,
            'merchant_ref' => $order->order_number,
            'payment_method' => 'va',
            'payment_channel' => 'bca',
            'payment_name' => 'BCA Virtual Account',
            'amount' => $order->total_amount,
            'status' => 'pending',
            'paid_at' => null,
        ], $overrides));
    }

    /** (a) Single paid payment backfills order.paid_at from the payment's paid_at */
    public function test_backfills_paid_at_from_single_paid_payment(): void
    {
        $order = $this->createOrder(['status' => 'paid', 'paid_at' => null]);
        $paidAt = Carbon::parse('2026-06-01 10:00:00');

        $this->createPayment($order, [
            'status' => 'paid',
            'paid_at' => $paidAt,
        ]);

        MigrationHelpers::backfillOrderPaidAt();

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($order->paid_at->equalTo($paidAt));
    }

    /** (b) Multiple payments (one failed, multiple paid) → uses the MAX paid_at among paid payments */
    public function test_backfills_paid_at_using_max_of_paid_payments(): void
    {
        $order = $this->createOrder(['status' => 'paid', 'paid_at' => null]);

        $this->createPayment($order, [
            'status' => 'failed',
            'paid_at' => null,
        ]);

        $olderPaidAt = Carbon::parse('2026-06-01 09:00:00');
        $newerPaidAt = Carbon::parse('2026-06-02 14:30:00');

        $this->createPayment($order, [
            'status' => 'paid',
            'paid_at' => $olderPaidAt,
        ]);

        $this->createPayment($order, [
            'status' => 'paid',
            'paid_at' => $newerPaidAt,
        ]);

        MigrationHelpers::backfillOrderPaidAt();

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($order->paid_at->equalTo($newerPaidAt));
    }

    /** (c) No paid payment exists → order.paid_at stays null */
    public function test_paid_at_stays_null_when_no_paid_payment_exists(): void
    {
        $order = $this->createOrder(['status' => 'paid', 'paid_at' => null]);

        $this->createPayment($order, [
            'status' => 'failed',
            'paid_at' => null,
        ]);

        MigrationHelpers::backfillOrderPaidAt();

        $order->refresh();
        $this->assertNull($order->paid_at);
    }

    /** (d) order.paid_at already set → not overwritten by backfill */
    public function test_existing_paid_at_is_not_overwritten(): void
    {
        $existingPaidAt = Carbon::parse('2026-05-20 08:00:00');
        $order = $this->createOrder(['status' => 'paid', 'paid_at' => $existingPaidAt]);

        $differentPaidAt = Carbon::parse('2026-06-05 16:00:00');
        $this->createPayment($order, [
            'status' => 'paid',
            'paid_at' => $differentPaidAt,
        ]);

        MigrationHelpers::backfillOrderPaidAt();

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($order->paid_at->equalTo($existingPaidAt));
    }
}
