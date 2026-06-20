<?php

namespace Tests\Feature;

use App\Models\{User, Category, Product, ProductVariant, FlashSale, FlashSaleItem, Order, OrderItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2 Task 9 (T11): ReconcileFlashSaleQuota — the nightly safety net that
 * heals flash_sale_items.sold_count drift caused by the non-idempotent
 * FlashSaleService::release() (Task 6). Source of truth = SUM(order_items.quantity)
 * for flash-linked order_items on orders whose reservation is still HELD.
 *
 * status='cancelled' alone is NOT a reliable "released" signal — it is
 * overloaded. The true released fingerprint is status='cancelled' AND
 * payment_status='failed' (CheckoutController::cancel, CancelExpiredOrders,
 * PaymentController failure webhook). AutoCancelStaleOrders also sets
 * status='cancelled' but leaves payment_status='paid' and never releases
 * the flash reservation — those must still be COUNTED (mirrors the Fase 1
 * voucher rule that paid-order promotion quota stays consumed).
 */
class ReconcileFlashSaleQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function makeVariant(): ProductVariant
    {
        $user = User::factory()->create(); // keep ids distinct across calls; unused otherwise
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

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 's-' . uniqid(),
            'name' => 'V',
            'price' => 100000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);
    }

    private function makeFlashItem(ProductVariant $variant, int $soldCount): FlashSaleItem
    {
        $sale = FlashSale::create([
            'name' => 'FS',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ]);

        return FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'sale_price' => 60000,
            'quota' => 10,
            'sold_count' => $soldCount,
        ]);
    }

    private function makeOrder(string $status, string $paymentStatus = 'unpaid'): Order
    {
        return Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'fulfillment_type' => 'pickup',
            'subtotal' => 120000,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'total_amount' => 120000,
            'payment_method' => 'qris',
            'payment_status' => $paymentStatus,
        ]);
    }

    private function makeOrderItem(Order $order, ProductVariant $variant, FlashSaleItem $fsItem, int $qty): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'flash_sale_item_id' => $fsItem->id,
            'product_name_snapshot' => 'P',
            'variant_name_snapshot' => 'V',
            'quantity' => $qty,
            'unit_price' => 60000,
            'weight_gram' => 100,
            'subtotal' => 60000 * $qty,
        ]);
    }

    /**
     * T11: a flash_sale_item with a drifted sold_count (stored 5) must be
     * recomputed to the true held quantity. qty=2 on a still-held order
     * (pending) counts; qty=3 on a cancelled+failed order (reservation
     * genuinely released) must NOT count. True sold_count = 2.
     */
    public function test_reconcile_fixes_drifted_sold_count(): void
    {
        $variant = $this->makeVariant();
        $fsItem = $this->makeFlashItem($variant, soldCount: 5); // drifted: stored 5, true should be 2

        $heldOrder = $this->makeOrder('pending');
        $this->makeOrderItem($heldOrder, $variant, $fsItem, qty: 2);

        $cancelledOrder = $this->makeOrder('cancelled', 'failed');
        $this->makeOrderItem($cancelledOrder, $variant, $fsItem, qty: 3);

        $this->artisan('flash-sale:reconcile')->assertExitCode(0);

        $this->assertSame(2, $fsItem->fresh()->sold_count);
    }

    /**
     * A flash_sale_item with zero held order_items (none at all, or only
     * cancelled ones) must reconcile down to 0 — not stay drifted.
     */
    public function test_reconcile_zeroes_item_with_no_held_orders(): void
    {
        $variant = $this->makeVariant();
        $fsItem = $this->makeFlashItem($variant, soldCount: 7); // drifted, no real orders at all

        $this->artisan('flash-sale:reconcile')->assertExitCode(0);

        $this->assertSame(0, $fsItem->fresh()->sold_count);
    }

    /**
     * A flash_sale_item whose only order_items sit on a cancelled+failed
     * order (reservation released — CheckoutController::cancel /
     * CancelExpiredOrders / PaymentController failure fingerprint) must
     * reconcile to 0, even though order_items exist.
     */
    public function test_reconcile_zeroes_item_with_only_cancelled_orders(): void
    {
        $variant = $this->makeVariant();
        $fsItem = $this->makeFlashItem($variant, soldCount: 4);

        $cancelledOrder = $this->makeOrder('cancelled', 'failed');
        $this->makeOrderItem($cancelledOrder, $variant, $fsItem, qty: 4);

        $this->artisan('flash-sale:reconcile')->assertExitCode(0);

        $this->assertSame(0, $fsItem->fresh()->sold_count);
    }

    /**
     * AutoCancelStaleOrders fingerprint: status='cancelled' but
     * payment_status='paid' (left untouched — it cancels stale paid/processing
     * orders without releasing the flash reservation, mirroring the Fase 1
     * voucher rule that paid-order quota stays consumed). The reconcile job
     * must COUNT these qty toward sold_count, NOT exclude them, because the
     * "released" fingerprint is specifically status='cancelled' AND
     * payment_status='failed' — not status='cancelled' alone.
     */
    public function test_reconcile_counts_stale_cancelled_paid_order(): void
    {
        $variant = $this->makeVariant();
        $fsItem = $this->makeFlashItem($variant, soldCount: 0); // drifted: stored 0, true should be 5

        $staleCancelledPaidOrder = $this->makeOrder('cancelled', 'paid');
        $this->makeOrderItem($staleCancelledPaidOrder, $variant, $fsItem, qty: 5);

        $this->artisan('flash-sale:reconcile')->assertExitCode(0);

        $this->assertSame(5, $fsItem->fresh()->sold_count);
    }
}
