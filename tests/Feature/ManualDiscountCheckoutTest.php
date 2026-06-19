<?php
namespace Tests\Feature;
use App\Models\{User, Category, Product, ProductVariant, Cart, CartItem, Order, OrderItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualDiscountCheckoutTest extends TestCase {
    use RefreshDatabase;

    public function test_order_uses_effective_price_with_manual_discount(): void {
        $user = User::factory()->create();
        $cat = Category::create(['name' => 'C', 'slug' => 'c1']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'slug' => 'p1', 'description' => 'd', 'base_price' => 100000, 'discount_percent' => 20, 'weight_gram' => 100, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'sk1', 'name' => 'V', 'price' => 100000, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true]);
        $cart = Cart::create(['user_id' => $user->id]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price_snapshot' => 80000]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup', 'item_ids' => (string) $item->id,
        ])->assertRedirect();

        $order = Order::first();
        $this->assertEqualsWithDelta(160000, $order->subtotal, 0.01);
        $this->assertEqualsWithDelta(80000, OrderItem::first()->unit_price, 0.01);
    }

    public function test_placement_blocks_when_consented_price_stale(): void {
        $user = User::factory()->create();
        $cat = Category::create(['name' => 'C', 'slug' => 'c2']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'slug' => 'p2', 'description' => 'd', 'base_price' => 100000, 'discount_percent' => 20, 'weight_gram' => 100, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'sk2', 'name' => 'V', 'price' => 100000, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true]);
        $cart = Cart::create(['user_id' => $user->id]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price_snapshot' => 80000]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup', 'item_ids' => (string) $item->id,
            'consented_prices' => [$item->id => 75000], // beda dari effective 80000
        ])->assertSessionHas('error');
        $this->assertNull(Order::first());
    }

    public function test_placement_succeeds_when_consented_price_matches(): void {
        $user = User::factory()->create();
        $cat = Category::create(['name' => 'C', 'slug' => 'c3']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'slug' => 'p3', 'description' => 'd', 'base_price' => 100000, 'discount_percent' => 20, 'weight_gram' => 100, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'sk3', 'name' => 'V', 'price' => 100000, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true]);
        $cart = Cart::create(['user_id' => $user->id]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price_snapshot' => 80000]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup', 'item_ids' => (string) $item->id,
            'consented_prices' => [$item->id => 80000], // sama dengan effective 80000
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertFalse(session()->has('error'));

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEqualsWithDelta(80000, $order->subtotal, 0.01);
        $this->assertEqualsWithDelta(80000, OrderItem::first()->unit_price, 0.01);
    }
}
