<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class MigrationHelpers
{
    /**
     * Backfill the `paid_at` column for existing orders that have been paid.
     * It uses the latest successful payment timestamp.
     */
    public static function backfillOrderPaidAt(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $orders = DB::table('orders')
                ->whereIn('status', ['paid','processing','shipped','completed','refunded'])
                ->whereNull('paid_at')
                ->get();
            
            foreach ($orders as $order) {
                $paidAt = DB::table('payments')
                    ->where('order_id', $order->id)
                    ->where('status', 'paid')
                    ->max('paid_at');
                
                if ($paidAt) {
                    DB::table('orders')->where('id', $order->id)->update(['paid_at' => $paidAt]);
                }
            }
            return;
        }

        DB::statement("
            UPDATE orders o
            INNER JOIN (
                SELECT order_id, MAX(paid_at) AS paid_at
                FROM payments
                WHERE status = 'paid'
                  AND paid_at IS NOT NULL
                GROUP BY order_id
            ) p ON p.order_id = o.id
            SET o.paid_at = p.paid_at
            WHERE o.status IN ('paid','processing','shipped','completed','refunded')
            AND o.paid_at IS NULL
        ");
    }
}
