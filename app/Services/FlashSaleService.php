<?php
namespace App\Services;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class FlashSaleService {
    /**
     * Guard against price inversion: a flash sale_price must always be the
     * cheapest price available for the variant. It must be strictly below
     * the normal variant price AND strictly below any active manual
     * discount (discount_price override or product-level discount_percent).
     *
     * The manual-effective price is delegated to
     * PricingService::manualEffectivePrice() (single source of truth, no
     * drift) rather than PricingService::priceInfo(), because priceInfo()
     * may already resolve to an existing active FLASH price (source='flash')
     * for this variant. Comparing a new proposed flash price against an old
     * flash price would be the wrong comparison — we need the MANUAL
     * effective price specifically, with flash interference removed.
     */
    public function assertSalePriceValid(ProductVariant $v, float $salePrice): void {
        $original = (float) $v->price;

        if ($salePrice >= $original) {
            throw new \Exception('Harga flash harus di bawah harga normal varian.');
        }

        $manualEffective = app(PricingService::class)->manualEffectivePrice($v);

        if ($manualEffective < $original && $salePrice >= $manualEffective) {
            throw new \Exception('Harga flash harus di bawah harga diskon manual yang berlaku.');
        }
    }

    /**
     * Reserve `$qty` units of a Flash Sale item's quota at checkout time.
     *
     * Locks the row (lockForUpdate) inside a transaction to serialize
     * concurrent reservations against the same flash item — this is the
     * race-condition guard equivalent to PromotionService::reserve().
     *
     * The quota CHECK is conditional (`quota !== null`): unlimited-quota
     * items skip the exhaustion guard. The `sold_count` INCREMENT is
     * UNCONDITIONAL — it always runs, even for unlimited-quota items —
     * because `sold_count` is the "units sold under flash pricing" counter
     * used for the storefront "X terjual" display and reconciled nightly
     * against actual flash order_items (ReconcileFlashSaleQuota). Making
     * the increment conditional on quota would make sold_count silently
     * stop tracking unlimited items and drift from the order_items truth.
     *
     * @throws \Exception when quota is set and would be exceeded by $qty.
     */
    public function reserve(int $flashSaleItemId, int $qty): void {
        DB::transaction(function () use ($flashSaleItemId, $qty) {
            $item = FlashSaleItem::lockForUpdate()->findOrFail($flashSaleItemId);

            if ($item->quota !== null && $item->sold_count + $qty > $item->quota) {
                throw new \Exception('Kuota Flash Sale habis.');
            }

            $item->increment('sold_count', $qty);
        });
    }

    /**
     * Release previously reserved Flash Sale quota for an order (on cancel
     * or expiry). For every order_item tied to a flash_sale_item_id,
     * decrements that item's sold_count by the order_item's quantity.
     *
     * The decrement is clamped via min($oi->quantity, $item->sold_count)
     * so a release can never push sold_count negative.
     *
     * IDEMPOTENCY: unlike PromotionService::release() (which self-guards via
     * a usage row's status='reserved'), this method has no per-order-item
     * status marker — it unconditionally decrements whatever order_items it
     * finds. A second call against the same already-released order WOULD
     * double-decrement. Safety here is delegated to:
     *   1. Callers being guarded to invoke release() exactly once per order
     *      (the cancel/expire paths wired in Task 6 recheck order status /
     *      canBeCancelled() before acting, so a cancelled order can't be
     *      cancelled or expired a second time).
     *   2. ReconcileFlashSaleQuota (Task 9), the nightly source-of-truth job
     *      that recomputes sold_count from actual flash order_items and
     *      heals any drift caused by a missed guard or edge case.
     * This mirrors the brief's explicit instruction not to over-engineer a
     * per-item marker now; the min() clamp is the only defense built here.
     */
    public function release(Order $order): void {
        DB::transaction(function () use ($order) {
            foreach ($order->items()->whereNotNull('flash_sale_item_id')->get() as $oi) {
                $item = FlashSaleItem::lockForUpdate()->find($oi->flash_sale_item_id);
                if ($item) {
                    $item->decrement('sold_count', min($oi->quantity, $item->sold_count));
                }
            }
        });
    }
}
