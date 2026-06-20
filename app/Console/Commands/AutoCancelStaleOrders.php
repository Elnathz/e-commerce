<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCancelStaleOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-cancel-stale';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically cancel orders that have been stuck in paid/processing for more than 7 days and flag as needing refund';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting stale orders check...');

        $thresholdDate = now()->subDays(7);

        // Cari pesanan yang mandek di paid/processing lebih dari 7 hari
        $staleOrders = \App\Models\Order::with('items.productVariant')
            ->whereIn('status', ['paid', 'processing'])
            ->where('updated_at', '<', $thresholdDate)
            ->get();

        $count = 0;
        foreach ($staleOrders as $order) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                // Kembalikan stok untuk setiap item di pesanan
                foreach ($order->items as $item) {
                    if ($item->productVariant) {
                        $item->productVariant->increment('stock', $item->quantity);
                    }
                }

                // FR030: Voucher quota is intentionally NOT released here. These orders
                // are already paid/processing, so their promotion_usages are 'confirmed'
                // (a counted terminal state — the voucher was genuinely redeemed). The
                // refund flow handles the financial reversal; the quota stays consumed.
                // Do NOT add release() (it no-ops on non-'reserved' usages) or a manual
                // used_count decrement here.
                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Otomatis dibatalkan oleh sistem karena tidak diproses penjual selama lebih dari 7 hari. Menunggu proses refund.',
                ]);
            });

            $count++;
            
            \Illuminate\Support\Facades\Log::info("Order {$order->order_number} auto-cancelled due to being stale. Stock replenished. Awaiting refund.");
        }

        $this->info("Completed. Auto-cancelled {$count} stale orders.");
    }
}
