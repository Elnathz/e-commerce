<?php

namespace Tests\Feature;

use App\Models\{User, Category, Product, ProductVariant, Cart, CartItem, FlashSale, FlashSaleItem, Order, OrderItem, Promotion, PromotionUsage, ReturnRequest, ReturnRequestItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Fase 2 Task 6 (T9): cancelling a pending order that holds a flash-sale
     * item must release the reserved flash quota — mirroring the Fase 1
     * fix for PromotionService::release() (test_voucher_released_on_customer_cancel).
     * Drives the REAL customer cancel route (orders.cancel), not the
     * service directly, so the test proves FlashSaleService::release() is
     * actually wired into CheckoutController::cancel().
     */
    public function test_cancel_releases_flash_quota(): void
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
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);

        // Quota reserved by placement.
        $this->assertSame(2, $fsItem->fresh()->sold_count);

        $this->actingAs($user)
            ->post(route('orders.cancel', $order->order_number))
            ->assertRedirect(route('home'));

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('failed', $order->payment_status);

        // Flash quota restored — the financial fix this task wires in.
        $this->assertSame(0, $fsItem->fresh()->sold_count);
    }

    /**
     * Fase 2 Task 6 (T10): a return for a flash-sale item must NOT restore
     * the flash quota — a returned flash item stays "sold" against the
     * flash allocation. Builds a PAID/completed order with a flash-linked
     * order item (following ReturnRequestTest's setUp pattern) and drives
     * the REAL customer return submission route (returns.store).
     */
    public function test_return_does_not_restore_flash_quota(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid()]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'P',
            'slug' => 'p-' . uniqid(),
            'description' => 'd',
            'base_price' => 100000,
            'weight_gram' => 100,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 's-' . uniqid(),
            'name' => 'V',
            'price' => 100000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $sale = FlashSale::create([
            'name' => 'FS',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $fsItem = FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'sale_price' => 60000,
            'quota' => 10,
            'sold_count' => 2, // 2 units already "sold" under flash pricing
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FLASH-RET-1',
            'user_id' => $user->id,
            'status' => 'completed',
            'completed_at' => now(),
            'fulfillment_type' => 'delivery',
            'subtotal' => 120000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 130000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'flash_sale_item_id' => $fsItem->id,
            'product_name_snapshot' => $product->name,
            'variant_name_snapshot' => $variant->name,
            'quantity' => 2,
            'unit_price' => 60000,
            'weight_gram' => 100,
            'subtotal' => 120000,
        ]);

        $response = $this->actingAs($user)->post(route('returns.store', $order->order_number), [
            'reason' => 'Defective product',
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'quantity' => 2,
                    'reason_code' => 'defective',
                    'condition' => 'opened',
                ],
            ],
            'images' => [
                UploadedFile::fake()->image('evidence.jpg'),
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\ReturnRequest::count());

        // Flash quota must stay UNCHANGED — a returned flash item is still "sold".
        $this->assertSame(2, $fsItem->fresh()->sold_count);
    }

    /**
     * Fase 2 whole-branch review fix: cancel() must be idempotent under
     * concurrency. The order is re-locked inside the transaction but must
     * ALSO re-check status under the lock before calling
     * FlashSaleService::release() (non-idempotent — see its docblock).
     * Without the guard, two concurrent (or sequential) calls to the cancel
     * route would double-decrement sold_count. Drives the REAL customer
     * cancel route TWICE — the second call sees status='cancelled' under
     * the lock and must no-op (no double release).
     */
    public function test_double_cancel_releases_flash_quota_once(): void
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
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);

        // Quota reserved by placement: sold_count went 0 -> 2.
        $this->assertSame(2, $fsItem->fresh()->sold_count);

        // First cancel — releases the quota (2 -> 0) and marks the order cancelled.
        $this->actingAs($user)
            ->post(route('orders.cancel', $order->order_number))
            ->assertRedirect(route('home'));

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(0, $fsItem->fresh()->sold_count);

        // Second cancel on the SAME already-cancelled order (sequential call
        // simulates the race outcome — the second request sees status=
        // 'cancelled' under the lock). Must be a safe no-op: sold_count stays
        // at 0 (not driven negative / not double-released), order stays
        // cancelled. canBeCancelled() is false post-cancel, so the route
        // returns the "tidak dapat dibatalkan" error rather than re-entering
        // the transaction at all — but either way sold_count must never move.
        $this->actingAs($user)
            ->post(route('orders.cancel', $order->order_number));

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        // Decremented exactly once (== original 0, not negative / not -2x).
        $this->assertSame(0, $fsItem->fresh()->sold_count);
    }

    /**
     * Fase 2 Task 10 (T6 flash, T7): the containsFlash gate (Fase 1 Task 2,
     * wired in Fase 2 Task 5) must reject a voucher whose
     * applies_to_flash_sale=false when the cart being placed contains a
     * flash-sale item. The reserve() call happens INSIDE the placement
     * transaction (CheckoutController::store()), so a rejected voucher must
     * roll back the ENTIRE order placement — no Order/OrderItem leaked, flash
     * sold_count not incremented, cart item untouched, session error set.
     *
     * Counter-case in the same test: the identical flash cart with a voucher
     * that DOES opt in (applies_to_flash_sale=true) must succeed — proving
     * the gate is flash-aware, not simply blocking all vouchers on flash carts.
     */
    public function test_voucher_blocked_on_flash_order_when_flag_off(): void
    {
        [$user, $item, $fsItem] = $this->setupCart(
            price: 100000,
            qty: 1,
            salePrice: 60000,
            quota: 10,
            soldCount: 0,
        );

        // PromotionObserver::created() logs Auth::id() as admin_id (FK to users,
        // NOT NULL) — authenticate before creating the Promotion fixtures.
        Auth::login($user);
        Promotion::create([
            'code' => 'NOFLASH',
            'name' => 'No Flash',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'is_active' => true,
            'applies_to_flash_sale' => false,
        ]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup',
            'item_ids' => (string) $item->id,
            'consented_prices' => [$item->id => 60000],
            'voucher_code' => 'NOFLASH',
        ]);

        $response->assertRedirect('/cart');
        $response->assertSessionHas('error');

        // Whole placement transaction rolled back — no order leaked.
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());

        // Flash quota must NOT have been incremented — reserve() throws AFTER
        // the flash sold_count increment in the same transaction, so the
        // rollback must also undo it.
        $this->assertSame(0, $fsItem->fresh()->sold_count);

        // No promotion_usages row leaked for the rejected attempt.
        $this->assertDatabaseCount('promotion_usages', 0);

        // Cart item must survive untouched — it was never deleted.
        $this->assertNotNull(CartItem::find($item->id));

        // Counter-case: the SAME flash cart with a voucher that opts in
        // (applies_to_flash_sale=true) must succeed.
        Promotion::create([
            'code' => 'FLASHOK',
            'name' => 'Flash OK',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'is_active' => true,
            'applies_to_flash_sale' => true,
        ]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup',
            'item_ids' => (string) $item->id,
            'consented_prices' => [$item->id => 60000],
            'voucher_code' => 'FLASHOK',
        ])->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order, 'A flag-on voucher must be accepted on a flash-containing cart.');
        $this->assertSame('FLASHOK', $order->voucher_code);

        // subtotal = 60000 (flash effective price) * 1; discount = 10% = 6000.
        $this->assertEqualsWithDelta(60000, $order->subtotal, 0.01);
        $this->assertEqualsWithDelta(6000, $order->discount_amount, 0.01);
        $this->assertEqualsWithDelta(54000, $order->total_amount, 0.01);

        $this->assertDatabaseHas('promotion_usages', ['order_id' => $order->id, 'status' => 'reserved']);

        // Flash quota now reserved by the successful placement.
        $this->assertSame(1, $fsItem->fresh()->sold_count);
    }

    /**
     * Fase 2 Task 10 (T6 flash, T7): combined flash + voucher proration.
     * Cart has ONE flash item (unit_price = sale_price = 60000, qty 1) and
     * ONE normal item (price = 100000, qty 1). A percentage voucher with
     * applies_to_flash_sale=true is applied at checkout.
     *
     * subtotal      = 60000 + 100000 = 160000
     * voucher 10%   -> discount_amount = 16000
     * discount_ratio = 16000 / 160000 = 0.1
     *
     * After placement, the customer returns ONLY the flash item (qty 1).
     * Refund must use the flash item's EFFECTIVE (sale) unit_price — proving
     * Fase 1 Task 5's discount_amount/subtotal proration composes correctly
     * with a flash-priced order_item — and shipping must NOT be refunded:
     *   refund = unit_price * qty * (1 - ratio) = 60000 * 1 * 0.9 = 54000
     */
    public function test_voucher_allowed_and_prorated_with_flash(): void
    {
        $user = User::factory()->create();
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid()]);

        // --- Flash item: base price 100000, flash sale_price 60000 ---
        $flashProduct = Product::create([
            'category_id' => $cat->id,
            'name' => 'Flash P',
            'slug' => 'flash-p-' . uniqid(),
            'description' => 'd',
            'base_price' => 100000,
            'weight_gram' => 100,
            'is_active' => true,
        ]);
        $flashVariant = ProductVariant::create([
            'product_id' => $flashProduct->id,
            'sku' => 'fs-' . uniqid(),
            'name' => 'V',
            'price' => 100000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);
        $sale = FlashSale::create([
            'name' => 'FS',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $fsItem = FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $flashVariant->id,
            'sale_price' => 60000,
            'quota' => 10,
            'sold_count' => 0,
        ]);

        // --- Normal item: price 100000 (no flash) ---
        $normalProduct = Product::create([
            'category_id' => $cat->id,
            'name' => 'Normal P',
            'slug' => 'normal-p-' . uniqid(),
            'description' => 'd',
            'base_price' => 100000,
            'weight_gram' => 100,
            'is_active' => true,
        ]);
        $normalVariant = ProductVariant::create([
            'product_id' => $normalProduct->id,
            'sku' => 'n-' . uniqid(),
            'name' => 'V',
            'price' => 100000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::create(['user_id' => $user->id]);
        $flashCartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $flashVariant->id,
            'quantity' => 1,
            'unit_price_snapshot' => 60000,
        ]);
        $normalCartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $normalVariant->id,
            'quantity' => 1,
            'unit_price_snapshot' => 100000,
        ]);

        Auth::login($user);
        Promotion::create([
            'code' => 'FLASH10',
            'name' => 'Flash 10',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'is_active' => true,
            'applies_to_flash_sale' => true,
        ]);

        $itemIds = $flashCartItem->id . ',' . $normalCartItem->id;
        $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup',
            'item_ids' => $itemIds,
            'consented_prices' => [
                $flashCartItem->id => 60000,
                $normalCartItem->id => 100000,
            ],
            'voucher_code' => 'FLASH10',
        ])->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order);

        // subtotal = 60000 (flash effective) + 100000 (normal) = 160000.
        $this->assertEqualsWithDelta(160000, $order->subtotal, 0.01);
        // discount = 10% of 160000 = 16000.
        $this->assertEqualsWithDelta(16000, $order->discount_amount, 0.01);
        $this->assertSame('FLASH10', $order->voucher_code);

        $flashOrderItem = OrderItem::where('flash_sale_item_id', $fsItem->id)->first();
        $this->assertNotNull($flashOrderItem);
        $this->assertEqualsWithDelta(60000, $flashOrderItem->unit_price, 0.01);

        // Mark the order completed so the return route's eligibility window passes.
        $order->update(['status' => 'completed', 'completed_at' => now()]);

        Storage::fake('public');

        $response = $this->actingAs($user)->post(route('returns.store', $order->order_number), [
            'reason' => 'Defective product',
            'items' => [
                [
                    'order_item_id' => $flashOrderItem->id,
                    'quantity' => 1,
                    'reason_code' => 'defective',
                    'condition' => 'opened',
                ],
            ],
            'images' => [
                UploadedFile::fake()->image('evidence.jpg'),
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $returnRequest = ReturnRequest::first();
        $this->assertNotNull($returnRequest);

        // Drive the SAME proration formula the admin Return show page uses
        // (Admin\ReturnController::calculateProratedRefund): discount_ratio =
        // discount_amount / subtotal = 16000 / 160000 = 0.1.
        $discountRatio = $order->discount_amount / $order->subtotal;
        $this->assertEqualsWithDelta(0.1, $discountRatio, 0.0001);

        $itemSubtotal = 1 * $flashOrderItem->unit_price; // qty * effective(sale) unit_price = 60000
        $expectedRefund = round($itemSubtotal * (1 - $discountRatio), 2); // 60000 * 0.9 = 54000
        $this->assertEqualsWithDelta(54000, $expectedRefund, 0.01);

        $refund = app(\App\Http\Controllers\Admin\ReturnController::class)
            ->calculateProratedRefund($order, $itemSubtotal);
        $this->assertEqualsWithDelta(54000, $refund, 0.01);

        // Confirm the same value surfaces through the real admin Show endpoint.
        $admin = User::factory()->create(['role' => 'admin']);
        $showResponse = $this->actingAs($admin)->get(route('admin.returns.show', $returnRequest->id));
        $showResponse->assertStatus(200);
        $items = $showResponse->viewData('page')['props']['returnRequest']['items'];
        $this->assertCount(1, $items);
        $this->assertEquals(54000, $items[0]['suggested_refund']);

        // Shipping is never part of the refund formula (pickup order, shipping_cost = 0 anyway).
        $this->assertEqualsWithDelta(0, $order->shipping_cost, 0.01);
    }
}
