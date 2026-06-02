<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AutoCancelStaleOrdersTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $category;
    private $product;
    private $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'description' => 'Test Description',
            'base_price' => 100000,
            'weight_gram' => 500,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'TEST-SKU',
            'name' => 'Standard',
            'price' => 90000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);
    }

    public function test_stale_orders_are_auto_cancelled_and_stock_replenished(): void
    {
        // 1. Create a stale order (paid, updated 8 days ago)
        $stalePaidOrder = Order::create([
            'order_number' => 'ORD-STALE-PAID',
            'user_id' => $this->user->id,
            'status' => 'paid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 90000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        \Illuminate\Support\Facades\DB::table('orders')
            ->where('id', $stalePaidOrder->id)
            ->update([
                'created_at' => now()->subDays(8),
                'updated_at' => now()->subDays(8),
            ]);

        OrderItem::create([
            'order_id' => $stalePaidOrder->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => $this->product->name,
            'variant_name_snapshot' => $this->variant->name,
            'quantity' => 2,
            'unit_price' => 90000,
            'weight_gram' => 500,
            'subtotal' => 180000,
        ]);

        // 2. Create a stale order (processing, updated 8 days ago)
        $staleProcessingOrder = Order::create([
            'order_number' => 'ORD-STALE-PROC',
            'user_id' => $this->user->id,
            'status' => 'processing',
            'fulfillment_type' => 'delivery',
            'subtotal' => 90000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        \Illuminate\Support\Facades\DB::table('orders')
            ->where('id', $staleProcessingOrder->id)
            ->update([
                'created_at' => now()->subDays(8),
                'updated_at' => now()->subDays(8),
            ]);

        OrderItem::create([
            'order_id' => $staleProcessingOrder->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => $this->product->name,
            'variant_name_snapshot' => $this->variant->name,
            'quantity' => 1,
            'unit_price' => 90000,
            'weight_gram' => 500,
            'subtotal' => 90000,
        ]);

        // 3. Create an active order (paid, updated today)
        $activeOrder = Order::create([
            'order_number' => 'ORD-ACTIVE',
            'user_id' => $this->user->id,
            'status' => 'paid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 90000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $activeOrder->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => $this->product->name,
            'variant_name_snapshot' => $this->variant->name,
            'quantity' => 1,
            'unit_price' => 90000,
            'weight_gram' => 500,
            'subtotal' => 90000,
        ]);

        // Initial variant stock is 10
        $this->assertEquals(10, $this->variant->stock);

        // Run the command
        Artisan::call('orders:auto-cancel-stale');

        // Check if stale orders are cancelled
        $stalePaidOrder->refresh();
        $staleProcessingOrder->refresh();
        $activeOrder->refresh();

        $this->assertEquals('cancelled', $stalePaidOrder->status);
        $this->assertEquals('cancelled', $staleProcessingOrder->status);
        $this->assertEquals('paid', $activeOrder->status); // Unchanged

        // Check if stock is replenished: 10 + 2 (from stalePaidOrder) + 1 (from staleProcessingOrder) = 13
        $this->variant->refresh();
        $this->assertEquals(13, $this->variant->stock);
    }
}
