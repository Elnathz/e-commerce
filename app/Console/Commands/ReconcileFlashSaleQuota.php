<?php

namespace App\Console\Commands;

use App\Models\FlashSaleItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileFlashSaleQuota extends Command
{
    protected $signature   = 'flash-sale:reconcile';
    protected $description = 'Sync flash_sale_items.sold_count from held flash order_items (source of truth). Run nightly.';

    /**
     * Excludes orders whose flash-quota reservation has been released.
     *
     * orders.status enum (database/migrations/2026_05_10_173213_create_orders_table.php):
     *   pending, paid, processing, shipped, ready_for_pickup, completed, cancelled, refunded
     * orders.payment_status enum (database/migrations/2026_05_22_010000_add_payment_fields_to_orders_table.php):
     *   unpaid, paid, failed, refunded
     *
     * status='cancelled' is OVERLOADED and is NOT by itself a reliable "released"
     * signal — it is reached by two different paths with opposite quota effects:
     *
     *   1. Released (must be EXCLUDED): CheckoutController::cancel,
     *      CancelExpiredOrders, and the PaymentController failure/expiry
     *      webhook all set status='cancelled' AND payment_status='failed',
     *      then call FlashSaleService::release(). The reservation is genuinely
     *      given back.
     *   2. NOT released (must be COUNTED): AutoCancelStaleOrders cancels stale
     *      paid/processing orders left unprocessed for 7+ days. It sets
     *      status='cancelled' but leaves payment_status='paid' (untouched)
     *      and deliberately does NOT call FlashSaleService::release() — the
     *      flash unit was genuinely sold; the refund flow handles the money
     *      side later. This mirrors the Fase 1 voucher rule that paid-order
     *      promotion quota stays consumed after a stale auto-cancel.
     *
     * So the true "released" fingerprint is the pair (status='cancelled' AND
     * payment_status='failed'), not status='cancelled' alone. Everything else
     * — including status='cancelled' with payment_status='paid' — still counts.
     * 'refunded' status is a post-completion return outcome; those orders were
     * already fulfilled and never had their flash reservation released, so
     * they are correctly counted too (no special-casing needed).
     */
    public function handle(): int
    {
        $this->info('Starting flash sale quota reconciliation...');

        // Single GROUP BY query — O(1) vs O(N) individual SUM queries
        $trueCounts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.flash_sale_item_id')
            ->whereNot(function ($q) {
                $q->where('orders.status', 'cancelled')
                  ->where('orders.payment_status', 'failed');
            })
            ->select('order_items.flash_sale_item_id', DB::raw('SUM(order_items.quantity) as true_count'))
            ->groupBy('order_items.flash_sale_item_id')
            ->pluck('true_count', 'order_items.flash_sale_item_id');

        $drifted = 0;
        $synced  = 0;

        FlashSaleItem::each(function (FlashSaleItem $item) use ($trueCounts, &$drifted, &$synced) {
            $trueCount = (int) ($trueCounts[$item->id] ?? 0);

            if ((int) $item->sold_count !== $trueCount) {
                $drifted++;
                Log::warning('FlashSaleItem sold_count drift detected', [
                    'flash_sale_item_id' => $item->id,
                    'stored'             => $item->sold_count,
                    'actual'             => $trueCount,
                ]);
                $item->update(['sold_count' => $trueCount]);
            }
            $synced++;
        });

        $this->info("Reconciliation complete. Checked: {$synced}, Drifted: {$drifted}");

        return self::SUCCESS;
    }
}
