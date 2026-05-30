<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // Set iPaymu test config
        config([
            'services.ipaymu.va' => 'test_va_123',
            'services.ipaymu.api_key' => 'test_api_key_123',
            'services.ipaymu.mode' => 'sandbox',
        ]);

        $this->user = User::factory()->create();

        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-pay',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-pay',
            'description' => 'Test',
            'base_price' => 50000,
            'weight_gram' => 500,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-PAY-SKU',
            'name' => 'Default',
            'price' => 50000,
            'stock' => 100,
            'reserved_stock' => 5,
            'is_active' => true,
        ]);

        // Create a pending order
        $this->order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'user_id' => $this->user->id,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'subtotal' => 250000,
            'shipping_cost' => 15000,
            'discount_amount' => 0,
            'total_amount' => 265000,
            'payment_status' => 'unpaid',
            'expired_at' => now()->addHours(24),
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'Test Product',
            'variant_name_snapshot' => 'Default',
            'quantity' => 5,
            'unit_price' => 50000,
            'weight_gram' => 500,
            'subtotal' => 250000,
        ]);
    }

    /**
     * Test payment channels endpoint returns list.
     */
    public function test_payment_channels_returns_list(): void
    {
        $response = $this->getJson('/api/payment-channels');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'channels' => [
                    '*' => ['method', 'channel', 'name', 'group'],
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('channels'));
    }

    /**
     * Test creating payment requires authentication.
     */
    public function test_create_payment_requires_auth(): void
    {
        $response = $this->postJson("/payments/{$this->order->order_number}/pay", [
            'payment_method' => 'va',
            'payment_channel' => 'bca',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test webhook rejects invalid signature.
     */
    public function test_webhook_rejects_invalid_signature(): void
    {
        // Create a payment record first
        Payment::create([
            'order_id' => $this->order->id,
            'gateway_reference' => '12345',
            'merchant_ref' => $this->order->order_number,
            'payment_method' => 'va',
            'payment_channel' => 'bca',
            'payment_name' => 'BCA Virtual Account',
            'amount' => $this->order->total_amount,
            'status' => 'pending',
            'pay_code' => '880012345678',
            'expired_at' => now()->addHours(24),
        ]);

        $response = $this->postJson('/api/payments/webhook', [
            'trx_id' => '12345',
            'reference_id' => $this->order->order_number,
            'status' => 'berhasil',
            'signature' => 'invalid_signature_here',
        ]);

        $response->assertStatus(400);
    }

    /**
     * FR010: Test webhook processes 'berhasil' and finalizes stock.
     */
    public function test_webhook_processes_berhasil_finalizes_stock(): void
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'gateway_reference' => '12345',
            'merchant_ref' => $this->order->order_number,
            'payment_method' => 'va',
            'payment_channel' => 'bca',
            'payment_name' => 'BCA Virtual Account',
            'amount' => $this->order->total_amount,
            'status' => 'pending',
            'pay_code' => '880012345678',
            'expired_at' => now()->addHours(24),
        ]);

        // Build valid callback data with proper signature
        $va = 'test_va_123';
        $callbackData = [
            'trx_id' => '12345',
            'reference_id' => $this->order->order_number,
            'status' => 'berhasil',
            'status_code' => '1',
            'amount' => (string) $this->order->total_amount,
        ];

        ksort($callbackData);
        $signature = hash_hmac('sha256', json_encode($callbackData), $va);
        $callbackData['signature'] = $signature;

        $response = $this->postJson('/api/payments/webhook', $callbackData);

        $response->assertOk();

        // Verify payment updated
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);

        // Verify order updated
        $this->order->refresh();
        $this->assertEquals('paid', $this->order->status);
        $this->assertEquals('paid', $this->order->payment_status);

        // FR010: Verify stock finalized
        $this->variant->refresh();
        $this->assertEquals(95, $this->variant->stock); // 100 - 5
        $this->assertEquals(0, $this->variant->reserved_stock); // 5 - 5
    }

    /**
     * FR015: Test webhook is idempotent on duplicate notification.
     */
    public function test_webhook_idempotent_on_duplicate(): void
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'gateway_reference' => '12345',
            'merchant_ref' => $this->order->order_number,
            'payment_method' => 'va',
            'payment_channel' => 'bca',
            'payment_name' => 'BCA Virtual Account',
            'amount' => $this->order->total_amount,
            'status' => 'paid', // Already finalized
            'pay_code' => '880012345678',
            'paid_at' => now(),
        ]);

        $va = 'test_va_123';
        $callbackData = [
            'trx_id' => '12345',
            'reference_id' => $this->order->order_number,
            'status' => 'berhasil',
        ];

        ksort($callbackData);
        $signature = hash_hmac('sha256', json_encode($callbackData), $va);
        $callbackData['signature'] = $signature;

        $response = $this->postJson('/api/payments/webhook', $callbackData);

        $response->assertOk();

        // Stock should NOT change again
        $this->variant->refresh();
        $this->assertEquals(100, $this->variant->stock); // Unchanged
        $this->assertEquals(5, $this->variant->reserved_stock); // Unchanged
    }

    /**
     * Test webhook releases stock on expired payment.
     */
    public function test_webhook_releases_stock_on_expired(): void
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'gateway_reference' => '12345',
            'merchant_ref' => $this->order->order_number,
            'payment_method' => 'va',
            'payment_channel' => 'bca',
            'payment_name' => 'BCA Virtual Account',
            'amount' => $this->order->total_amount,
            'status' => 'pending',
            'pay_code' => '880012345678',
            'expired_at' => now()->subHour(),
        ]);

        $va = 'test_va_123';
        $callbackData = [
            'trx_id' => '12345',
            'reference_id' => $this->order->order_number,
            'status' => 'expired',
        ];

        ksort($callbackData);
        $signature = hash_hmac('sha256', json_encode($callbackData), $va);
        $callbackData['signature'] = $signature;

        $response = $this->postJson('/api/payments/webhook', $callbackData);

        $response->assertOk();

        // Payment expired
        $payment->refresh();
        $this->assertEquals('expired', $payment->status);

        // Order cancelled
        $this->order->refresh();
        $this->assertEquals('cancelled', $this->order->status);

        // Stock released
        $this->variant->refresh();
        $this->assertEquals(100, $this->variant->stock); // Unchanged
        $this->assertEquals(0, $this->variant->reserved_stock); // 5 - 5
    }

    /**
     * FR018: Test cancel order releases stock.
     */
    public function test_cancel_order_releases_stock(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/orders/{$this->order->order_number}/cancel");

        $response->assertRedirect('/');

        // Order cancelled
        $this->order->refresh();
        $this->assertEquals('cancelled', $this->order->status);
        $this->assertEquals('failed', $this->order->payment_status);
        $this->assertNotNull($this->order->cancelled_at);

        // Stock released
        $this->variant->refresh();
        $this->assertEquals(0, $this->variant->reserved_stock); // 5 - 5
    }

    /**
     * FR016: Test cancel expired orders command.
     */
    public function test_cancel_expired_command(): void
    {
        // Set order as expired
        $this->order->update([
            'expired_at' => now()->subHour(),
        ]);

        Artisan::call('orders:cancel-expired');

        $this->order->refresh();
        $this->assertEquals('cancelled', $this->order->status);
        $this->assertEquals('failed', $this->order->payment_status);

        $this->variant->refresh();
        $this->assertEquals(0, $this->variant->reserved_stock);
    }

    /**
     * Test cannot cancel already paid order.
     */
    public function test_cannot_cancel_paid_order(): void
    {
        $this->order->update([
            'status' => 'paid',
            'payment_status' => 'paid',
        ]);

        $response = $this->actingAs($this->user)
            ->post("/orders/{$this->order->order_number}/cancel");

        $response->assertRedirect();

        // Order should still be paid
        $this->order->refresh();
        $this->assertEquals('paid', $this->order->status);
    }
}
