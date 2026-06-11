<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use App\Services\Payment\IPaymuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class NotificationCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_payment_webhook_idempotency_prevents_duplicate_notifications()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        
        $category = \App\Models\Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category'
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'base_price' => 50000,
            'weight_gram' => 100,
            'description' => 'Test description'
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-01',
            'name' => 'Default',
            'variant_type' => 'default',
            'price' => 50000,
            'stock' => 10,
            'reserved_stock' => 2,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-12345',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 100000,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'total_amount' => 100000,
        ]);

        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'variant_name_snapshot' => $variant->name,
            'quantity' => 2,
            'unit_price' => 50000,
            'weight_gram' => 100,
            'subtotal' => 100000,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'merchant_ref' => $order->order_number,
            'amount' => 100000,
            'payment_method' => 'qris',
            'payment_channel' => 'qris',
            'payment_name' => 'QRIS',
            'status' => 'pending',
        ]);

        // 2. Mock IPaymuService to bypass signature validation
        $this->mock(IPaymuService::class, function (MockInterface $mock) {
            $mock->shouldReceive('verifyCallbackSignature')->andReturn(true);
        });

        // 3. Payload Webhook Berhasil
        $payload = [
            'reference_id' => $order->order_number,
            'trx_id' => 'TRX12345',
            'status' => 'berhasil',
            'status_code' => 1,
            'amount' => 100000,
        ];

        // 4. Kirim Webhook Pertama
        $response1 = $this->postJson('/api/payments/webhook', $payload);
        $response1->assertStatus(200);

        // Pastikan Notifikasi terkirim tepat 1 kali
        Notification::assertSentTo(
            [$user],
            OrderStatusNotification::class,
            function ($notification) use ($user, $order) {
                return $notification->toArray($user)['order_id'] === $order->id && $notification->toArray($user)['type'] === 'order_paid';
            }
        );

        // Reset fake untuk menguji pemanggilan kedua
        Notification::fake();

        // 5. Kirim Webhook Kedua (Duplikat / Retry dari Payment Gateway)
        $response2 = $this->postJson('/api/payments/webhook', $payload);
        $response2->assertStatus(200); // Harus tetap 200 agar gateway tidak terus mengulang

        // Pastikan Notifikasi TIDAK terkirim lagi
        Notification::assertNothingSent();

        // Pastikan status database konsisten
        $this->assertEquals('paid', $payment->fresh()->status);
        $this->assertEquals('paid', $order->fresh()->status);
        
        // Stock: reserved_stock harus berkurang, stock berkurang
        // Awal: stock=10, reserved=2
        // Setelah paid: stock=8, reserved=0
        $this->assertEquals(8, $variant->fresh()->stock);
        $this->assertEquals(0, $variant->fresh()->reserved_stock);
    }
}
