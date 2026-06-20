<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:cancel-expired';

    /**
     * The console command description.
     */
    protected $description = 'Cancel orders that have exceeded their payment deadline and release reserved stock (FR016)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredOrders = Order::where('status', 'pending')
            ->where('expired_at', '<', now())
            ->with('items')
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('No expired orders found.');
            return self::SUCCESS;
        }

        $this->info("Found {$expiredOrders->count()} expired order(s). Processing...");
        $cancelledCount = 0;

        foreach ($expiredOrders as $order) {
            try {
                DB::transaction(function () use ($order) {
                    // Lock the order row to prevent race with webhook
                    $lockedOrder = Order::where('id', $order->id)
                        ->lockForUpdate()
                        ->first();

                    // Double-check: might have been paid between query and lock
                    if ($lockedOrder->status !== 'pending') {
                        return;
                    }

                    // Release reserved stock for all order items
                    foreach ($order->items as $item) {
                        ProductVariant::where('id', $item->product_variant_id)
                            ->where('reserved_stock', '>=', $item->quantity)
                            ->update([
                                'reserved_stock' => DB::raw('reserved_stock - ' . (int) $item->quantity),
                            ]);
                    }

                    // Mark all pending payments as expired
                    $lockedOrder->payments()->where('status', 'pending')->update([
                        'status' => 'expired',
                    ]);

                    // Cancel the order
                    $lockedOrder->update([
                        'status' => 'cancelled',
                        'payment_status' => 'failed',
                        'cancelled_at' => now(),
                        'cancelled_reason' => 'Batas waktu pembayaran habis (otomatis)',
                    ]);

                    // Release stock (variant may be null if it was deleted)
                    foreach ($order->items as $item) {
                        $item->productVariant?->increment('stock', $item->quantity);
                    }

                    // Release reserved promotion quota (FR030)
                    app(\App\Services\PromotionService::class)->release($lockedOrder->id);

                    // Release reserved flash sale quota (Fase 2 Task 6)
                    app(\App\Services\FlashSaleService::class)->release($lockedOrder);
                });

                $cancelledCount++;
                $this->line("  ✓ Cancelled: {$order->order_number}");

            } catch (\Exception $e) {
                $this->error("  ✗ Failed to cancel {$order->order_number}: {$e->getMessage()}");
                Log::error('CancelExpiredOrders: failed', [
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Cancelled {$cancelledCount} order(s).");
        Log::info("CancelExpiredOrders: cancelled {$cancelledCount} order(s)");

        return self::SUCCESS;
    }
}
