<?php

namespace Tests\Feature;

use App\Models\{User, Category, Product, ProductVariant, Cart, CartItem, Promotion};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class VoucherCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function setupCart(float $price = 100000, int $qty = 1): array
    {
        $user = User::factory()->create();
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid()]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'P',
            'slug' => 'p-' . uniqid(),
            'description' => 'd',
            'base_price' => $price,
            'weight_gram' => 100,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 's-' . uniqid(),
            'name' => 'V',
            'price' => $price,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);
        $cart = Cart::create(['user_id' => $user->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price_snapshot' => $price,
        ]);
        return [$user, $item];
    }

    public function test_validate_computes_discount_server_side(): void
    {
        [$user, $item] = $this->setupCart(100000, 2); // subtotal 200000
        // PromotionObserver::created() logs Auth::id() as admin_id (FK to users,
        // NOT NULL) — authenticate before creating the Promotion fixture.
        Auth::login($user);
        Promotion::create([
            'code' => 'DISC10',
            'name' => 'Disc 10',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'is_active' => true,
            'applies_to_flash_sale' => false,
        ]);

        $this->actingAs($user)->postJson(route('promotions.validate'), [
            'code' => 'DISC10',
            'item_ids' => (string) $item->id,
            'method' => 'pickup',
            'shipping_cost' => 0,
        ])->assertOk()->assertJson(['valid' => true, 'discount_amount' => 20000]);
    }

    public function test_validate_rejects_unknown_code(): void
    {
        [$user, $item] = $this->setupCart(100000, 1);

        $this->actingAs($user)->postJson(route('promotions.validate'), [
            'code' => 'NOPE',
            'item_ids' => (string) $item->id,
            'method' => 'pickup',
            'shipping_cost' => 0,
        ])->assertOk()->assertJson(['valid' => false]);
    }

    public function test_checkout_applies_voucher_and_sets_discount(): void
    {
        [$user, $item] = $this->setupCart(100000, 2); // subtotal 200000
        // PromotionObserver::created() logs Auth::id() as admin_id (FK to users,
        // NOT NULL) — authenticate before creating the Promotion fixture.
        Auth::login($user);
        \App\Models\Promotion::create([
            'code' => 'DISC10',
            'name' => 'Disc 10',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'is_active' => true,
            'applies_to_flash_sale' => false,
        ]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup', 'item_ids' => (string) $item->id, 'voucher_code' => 'DISC10',
        ])->assertRedirect();

        $order = \App\Models\Order::first();
        $this->assertNotNull($order);
        $this->assertEqualsWithDelta(200000, $order->subtotal, 0.01);
        $this->assertEqualsWithDelta(20000, $order->discount_amount, 0.01);
        $this->assertEqualsWithDelta(180000, $order->total_amount, 0.01);
        $this->assertSame('DISC10', $order->voucher_code);
        $this->assertDatabaseHas('promotion_usages', ['order_id' => $order->id, 'status' => 'reserved']);
    }

    public function test_validate_free_shipping_voucher_respects_internal_courier_scope(): void
    {
        [$user, $item] = $this->setupCart(50000, 1); // subtotal 50000

        Auth::login($user);
        Promotion::create([
            'code' => 'INTERNALONLY',
            'name' => 'Internal Only',
            'type' => 'free_shipping',
            'value' => 0,
            'min_purchase' => 0,
            'is_active' => true,
            'applicable_shipping_type' => 'internal',
            'applies_to_flash_sale' => false,
        ]);

        // External courier (jne) should be rejected for an internal-only voucher.
        $this->actingAs($user)->postJson(route('promotions.validate'), [
            'code' => 'INTERNALONLY',
            'item_ids' => (string) $item->id,
            'method' => 'delivery',
            'courier' => 'jne',
            'shipping_cost' => 15000,
        ])->assertOk()->assertJson(['valid' => false]);

        // Internal courier should be accepted and discount equals shipping cost.
        $this->actingAs($user)->postJson(route('promotions.validate'), [
            'code' => 'INTERNALONLY',
            'item_ids' => (string) $item->id,
            'method' => 'delivery',
            'courier' => 'internal',
            'shipping_cost' => 15000,
        ])->assertOk()->assertJson(['valid' => true, 'discount_amount' => 15000]);
    }
}
