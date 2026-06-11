<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReconcilePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending payments with the payment gateway to catch missing callbacks.';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\Alerting\SystemAlertService $alertService)
    {
        $this->info('Starting payment reconciliation...');

        // Cari pesanan yang berstatus pending dan usianya lebih dari 1 jam (tapi kurang dari 24 jam untuk membatasi query).
        $orders = \App\Models\Order::where('status', 'pending')
            ->where('created_at', '<=', now()->subHour())
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $reconciledCount = 0;
        $failureCount = 0;

        foreach ($orders as $order) {
            // Guard: Pastikan pesanan benar-benar masih pending (bisa saja berubah di transaction lain)
            if ($order->status !== 'pending') {
                continue;
            }

            try {
                // Di dunia nyata, ini akan memanggil API Gateway (misal IPaymu/Tripay).
                // $status = PaymentGateway::checkStatus($order->id);
                // Untuk contoh ini, kita simulasikan:
                $gatewayStatus = 'paid'; // simulasi: ternyata di gateway sudah terbayar
                
                if ($gatewayStatus === 'paid') {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                        // Lock for update untuk mencegah race condition
                        $lockedOrder = \App\Models\Order::where('id', $order->id)->lockForUpdate()->first();
                        
                        if ($lockedOrder->status === 'pending') {
                            $fromStatus = $lockedOrder->status;
                            $lockedOrder->status = 'paid';
                            $lockedOrder->payment_status = 'paid';
                            $lockedOrder->paid_at = now();
                            $lockedOrder->save();

                            // Dispatch event agar notifikasi/stok tersinkron
                            event(new \App\Events\OrderStatusChanged($lockedOrder, $fromStatus, 'paid'));
                        }
                    });

                    $reconciledCount++;
                    
                    // Tembakkan warning alert karena kita menemukan ada callback yang hilang
                    $alertService->dispatch(
                        event: 'Payment Reconciled (Missing Callback)',
                        severity: 'warning',
                        payload: ['order_id' => $order->id, 'action' => 'force_paid']
                    );
                }
            } catch (\Exception $e) {
                $failureCount++;
                \Illuminate\Support\Facades\Log::error("Failed to reconcile order {$order->id}: " . $e->getMessage());
            }
        }

        $this->info("Reconciliation complete. Reconciled: {$reconciledCount}, Failed: {$failureCount}.");

        // Jika ada banyak kegagalan rekonsiliasi (Gateway Error), tembak alert.
        if ($failureCount >= 5) {
            $alertService->dispatch(
                event: 'Reconciliation Job High Failure Rate',
                severity: 'critical',
                payload: ['failures' => $failureCount]
            );
        }
    }
}
