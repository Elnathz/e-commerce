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
     * There is NO separate "expired" order status — CancelExpiredOrders sets
     * status='cancelled' (payment_status='failed') on payment-deadline expiry,
     * same as a customer cancel (CheckoutController::cancel) and a failed/expired
     * payment webhook (PaymentController). All three call sites that invoke
     * FlashSaleService::release() land on status='cancelled'. 'refunded' is a
     * post-completion return outcome — those orders were already fulfilled and
     * never had their flash reservation released, so they must NOT be excluded.
     * Pending/paid/processing/etc. all still HOLD their reservation, so they
     * are intentionally NOT restricted to paid-only.
     */
    private const RELEASED_STATUSES = ['cancelled'];

    public function handle(): int
    {
        $this->info('Starting flash sale quota reconciliation...');

        // Single GROUP BY query — O(1) vs O(N) individual SUM queries
        $trueCounts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.flash_sale_item_id')
            ->whereNotIn('orders.status', self::RELEASED_STATUSES)
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
