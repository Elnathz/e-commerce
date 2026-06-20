<?php

namespace Tests\Feature;

use App\Models\{User, Category, Product, ProductVariant, Cart, CartItem, Promotion, PromotionUsage, Order, OrderItem};
use App\Services\PromotionService;
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

    /**
     * Review hardening (FR030): CheckoutController::store() reserves the voucher
     * INSIDE the order-placement DB::transaction. An unknown voucher_code makes
     * the firstOrFail() lookup throw, which must propagate out of the closure and
     * roll back the ENTIRE transaction — order, order items, stock reservation,
     * stock decrement, and the cart deletion — leaving the cart exactly as it was.
     * Without this atomicity, a bad voucher could leak a leftover order / reserved
     * stock while charging nothing, or silently drop the cart item.
     */
    public function test_invalid_voucher_rolls_back_entire_order_placement(): void
    {
        [$user, $item] = $this->setupCart(100000, 2); // subtotal 200000
        $variant = $item->variant;
        $originalStock = $variant->stock;
        $originalReserved = $variant->reserved_stock;

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'method' => 'pickup',
            'item_ids' => (string) $item->id,
            'voucher_code' => 'KODENGACO', // does not exist -> firstOrFail() throws inside transaction
        ]);

        $response->assertRedirect('/cart');
        $response->assertSessionHas('error');

        // No order / order items were created — the whole transaction rolled back.
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());

        // Stock reservation made earlier in the same transaction must be undone too.
        $variant->refresh();
        $this->assertSame($originalReserved, $variant->reserved_stock);
        $this->assertSame($originalStock, $variant->stock);

        // Cart item must survive untouched — it was never deleted.
        $this->assertNotNull(CartItem::find($item->id));

        // No promotion_usages row leaked for this failed attempt.
        $this->assertDatabaseCount('promotion_usages', 0);
    }

    /**
     * Fase 1 Task 6: PromotionService::confirm() flips reserved -> confirmed
     * when an order is paid (mirrors PaymentController::handlePaymentSuccess()
     * calling confirm() directly — the webhook itself is out of scope here).
     * used_count must stay at 1 — a confirmed usage is still counted quota.
     */
    public function test_voucher_confirmed_on_paid(): void
    {
        [$user, $item] = $this->setupCart(100000, 2); // subtotal 200000
        Auth::login($user);
        $promo = Promotion::create([
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

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('promotion_usages', ['order_id' => $order->id, 'status' => 'reserved']);
        $this->assertSame(1, $promo->refresh()->used_count);

        // Drive the payment-success path's promotion confirmation directly
        // (PaymentController::handlePaymentSuccess() calls this same line).
        app(PromotionService::class)->confirm($order->id);

        $usage = PromotionUsage::where('order_id', $order->id)->first();
        $this->assertSame('confirmed', $usage->status);
        $this->assertNotNull($usage->confirmed_at);
        // Confirmed usage is still counted quota — used_count must NOT change.
        $this->assertSame(1, $promo->refresh()->used_count);
    }

    /**
     * Fase 1 Task 6 (financial bug fix): customer-initiated cancel via
     * orders.cancel must release the reserved promotion quota (FR030),
     * not just the reserved stock. Before the fix, used_count leaked and
     * the per-user limit was wrongly consumed forever.
     */
    public function test_voucher_released_on_customer_cancel(): void
    {
        [$user, $item] = $this->setupCart(100000, 2); // subtotal 200000
        $variant = $item->variant;
        Auth::login($user);
        $promo = Promotion::create([
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

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame(1, $promo->refresh()->used_count);

        $variant->refresh();
        $reservedBeforeCancel = $variant->reserved_stock;
        $this->assertSame(2, $reservedBeforeCancel);

        $this->actingAs($user)
            ->post(route('orders.cancel', $order->order_number))
            ->assertRedirect(route('home'));

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('failed', $order->payment_status);

        $usage = PromotionUsage::where('order_id', $order->id)->first();
        $this->assertSame('released', $usage->status);
        $this->assertNotNull($usage->released_at);

        // Quota restored — the financial bug this task fixes.
        $this->assertSame(0, $promo->refresh()->used_count);

        // Stock release must still work (fix didn't regress existing behavior).
        $variant->refresh();
        $this->assertSame(0, $variant->reserved_stock);
    }

    /**
     * Fase 1 Task 6 (financial bug fix): the expiry cron (orders:cancel-expired)
     * must also release the reserved promotion quota, mirroring the
     * customer-cancel fix above.
     */
    public function test_voucher_released_on_expiry_cron(): void
    {
        [$user, $item] = $this->setupCart(100000, 2); // subtotal 200000
        $variant = $item->variant;
        Auth::login($user);
        $promo = Promotion::create([
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

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame(1, $promo->refresh()->used_count);

        // Force expiry
        $order->update(['expired_at' => now()->subHour()]);

        $this->artisan('orders:cancel-expired')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('failed', $order->payment_status);

        $usage = PromotionUsage::where('order_id', $order->id)->first();
        $this->assertSame('released', $usage->status);
        $this->assertNotNull($usage->released_at);

        // Quota restored — the financial bug this task fixes.
        $this->assertSame(0, $promo->refresh()->used_count);

        // Stock release must still work (fix didn't regress existing behavior).
        $variant->refresh();
        $this->assertSame(0, $variant->reserved_stock);
    }

    /**
     * Critical review fix (Fase 1 Task 8): CheckoutController::index() reads the
     * carried-forward voucher code via $request->query('voucher') and previously
     * called strtoupper() on it directly. Laravel's query() can return an ARRAY
     * for a crafted URL like ?voucher[]=x, and strtoupper(array) throws an
     * uncaught TypeError -> HTTP 500, breaking the checkout page entirely.
     * The guard must coerce any non-string (array/null) to null and only
     * uppercase a real string, while leaving normal carry-forward intact.
     */
    public function test_checkout_index_carries_voucher_query_safely(): void
    {
        [$user, $item] = $this->setupCart(100000, 1);

        // Case 1: normal string voucher code is carried forward and uppercased.
        $this->actingAs($user)->get(route('checkout.index', [
            'method' => 'pickup',
            'items' => $item->id,
            'voucher' => 'disc10',
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Storefront/Checkout')
            ->where('initialVoucher', 'DISC10')
        );

        // Case 2 (regression guard): malformed array voucher param must NOT 500.
        // query('voucher') returns ['x'] here, not a string.
        $this->actingAs($user)->get(route('checkout.index', [
            'method' => 'pickup',
            'items' => $item->id,
            'voucher' => ['x'],
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Storefront/Checkout')
            ->where('initialVoucher', null)
        );

        // Case 3: no voucher param at all -> initialVoucher stays null.
        $this->actingAs($user)->get(route('checkout.index', [
            'method' => 'pickup',
            'items' => $item->id,
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Storefront/Checkout')
            ->where('initialVoucher', null)
        );
    }
}
