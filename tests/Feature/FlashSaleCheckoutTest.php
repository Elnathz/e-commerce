<?php

namespace Tests\Feature;

use App\Models\{User, Category, Product, ProductVariant, Cart, CartItem, FlashSale, FlashSaleItem, Order, OrderItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashSaleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds: user + category + product + variant (+ optional flash sale on
     * the variant) + cart + cart item. Mirrors the setupCart() pattern used
     * by VoucherCheckoutTest / ManualDiscountCheckoutTest / FlashSaleServiceTest.
     *
     * @return array{0: User, 1: CartItem, 2: ?FlashSaleItem}
     */
    private function setupCart(
        float $price = 100000,
        int $qty = 1,
        ?float $salePrice = null,
        ?int $quota = null,
        int $soldCount = 0,
        ?\Illuminate\Support\Carbon $startsAt = null,
        ?\Illuminate\Support\Carbon $endsAt = null,
    ): array {
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

        $fsItem = null;
        if ($salePrice !== null) {
            $sale = FlashSale::create([
                'name' => 'FS',
                'starts_at' => $startsAt ?? now()->subMinute(),
                'ends_at' => $endsAt ?? now()->addHour(),
                'is_active' => true,
            ]);
            $fsItem = FlashSaleItem::create([
                'flash_sale_id' => $sale->id,
                'product_variant_id' => $variant->id,
                'sale_price' => $salePrice,
                'quota' => $quota,
                'sold_count' => $soldCount,
            ]);
        }

        $cart = Cart::create(['user_id' => $user->id]);
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price_snapshot' => $salePrice ?? $price,
        ]);

        return [$user, $item, $fsItem];
    }

    /**
     * T-link: a normal flash placement (active window, quota available)
     * must charge the flash sale_price as unit_price, link the OrderItem
     * to the FlashSaleItem, increment sold_count by the ordered quantity,
     * and roll the flash price into order.subtotal.
     */
    public function test_order_item_records_flash_price_and_link(): void
    {
        [$user, $item, $fsItem] = $this->setupCart(
            price: 100000,
            qty: 2,
            salePrice: 60000,
            quota: 10,
            soldCount: 0,
        );

        $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup',
            'item_ids' => (string) $item->id,
            'consented_prices' => [$item->id => 60000],
        ])->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order, 'Order must be created for a valid active flash placement.');

        $orderItem = OrderItem::first();
        $this->assertNotNull($orderItem);
        $this->assertEqualsWithDelta(60000, $orderItem->unit_price, 0.01);
        $this->assertSame($fsItem->id, $orderItem->flash_sale_item_id);

        $this->assertEqualsWithDelta(120000, $order->subtotal, 0.01); // 60000 * 2

        $this->assertSame(2, $fsItem->fresh()->sold_count);
    }

    /**
     * T3 (race): quota=1. First order (user A) succeeds and exhausts the
     * quota (sold_count -> 1). Second order (user B), placed sequentially
     * after, must fail placement (price_changed via the re-check under
     * lock sees sold_count >= quota) — no order created for B, sold_count
     * stays at 1 (not double-incremented).
     */
    public function test_quota_one_only_one_order_succeeds(): void
    {
        [$userA, $itemA, $fsItem] = $this->setupCart(
            price: 100000,
            qty: 1,
            salePrice: 60000,
            quota: 1,
            soldCount: 0,
        );

        // User B shares the SAME flash item / variant — build a second cart
        // against the same variant manually (setupCart always creates a new
        // product+variant, so wire user B's cart item to fsItem's variant).
        $userB = User::factory()->create();
        $cartB = Cart::create(['user_id' => $userB->id]);
        $itemB = CartItem::create([
            'cart_id' => $cartB->id,
            'product_variant_id' => $fsItem->product_variant_id,
            'quantity' => 1,
            'unit_price_snapshot' => 60000,
        ]);

        // User A places first — succeeds, exhausts quota.
        $this->actingAs($userA)->post(route('checkout.store'), [
            'method' => 'pickup',
            'item_ids' => (string) $itemA->id,
            'consented_prices' => [$itemA->id => 60000],
        ])->assertRedirect();

        $this->assertSame(1, Order::count());
        $this->assertSame(1, $fsItem->fresh()->sold_count);

        // User B places second — quota exhausted under lock -> price_changed
        // (unified expiry+soldout UX) -> whole transaction rolls back.
        $responseB = $this->actingAs($userB)->post(route('checkout.store'), [
            'method' => 'pickup',
            'item_ids' => (string) $itemB->id,
            'consented_prices' => [$itemB->id => 60000],
        ]);

        $responseB->assertRedirect('/cart');
        $responseB->assertSessionHas('error');

        // Still only the ONE order from user A — no order created for B.
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItem::count());

        // sold_count must NOT have been incremented a second time.
        $this->assertSame(1, $fsItem->fresh()->sold_count);

        // User B's cart item must survive untouched.
        $this->assertNotNull(CartItem::find($itemB->id));
    }

    /**
     * T4 (expiry): flash window already ended before placement. Client
     * still submits consented_prices = flash price (stale view). store()
     * must re-check validity under lock, find the window expired, and
     * fail placement with the price_changed message — no order created,
     * sold_count unchanged.
     */
    public function test_expired_flash_blocks_with_price_changed(): void
    {
        [$user, $item, $fsItem] = $this->setupCart(
            price: 100000,
            qty: 1,
            salePrice: 60000,
            quota: 10,
            soldCount: 0,
            startsAt: now()->subHours(3),
            endsAt: now()->subHour(), // already ended
        );

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup',
            'item_ids' => (string) $item->id,
            // Client still thinks the flash price applies (stale view).
            'consented_prices' => [$item->id => 60000],
        ]);

        $response->assertRedirect('/cart');
        $response->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, $fsItem->fresh()->sold_count);

        $this->assertNotNull(CartItem::find($item->id));
    }
}
