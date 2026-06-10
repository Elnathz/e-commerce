<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Events\ReturnRequestStatusChanged;
use App\Models\ActivityLog;

class RecordActivityLog
{
    public function handleOrderStatus(OrderStatusChanged $event): void
    {
        $typeMap = [
            'paid'       => 'order.paid',
            'processing' => 'order.processing',
            'shipped'    => 'order.shipped',
            'completed'  => 'order.completed',
            'cancelled'  => 'order.cancelled',
        ];

        $type = $typeMap[$event->toStatus] ?? "order.{$event->toStatus}";

        ActivityLog::create([
            'type'          => $type,
            'actor_type'    => auth()->check() ? 'admin' : 'system',
            'actor_id'      => auth()->id(),
            'actor_name'    => auth()->user()?->name ?? 'System',
            'subject_type'  => 'order',
            'subject_id'    => $event->order->id,
            'subject_label' => $event->order->order_number,
            'description'   => "Order {$event->order->order_number} berubah dari {$event->fromStatus} ke {$event->toStatus}",
            'metadata'      => ['amount' => $event->order->total_amount],
        ]);
    }

    public function handleReturnStatus(ReturnRequestStatusChanged $event): void
    {
        $typeMap = [
            'submitted' => 'return.submitted',
            'approved'  => 'return.approved',
            'received'  => 'return.received',
            'inspected' => 'return.inspected',
            'refund_processed' => 'return.refund_processed',
            'rejected'  => 'return.rejected',
            'completed' => 'return.completed',
        ];

        $type = $typeMap[$event->toStatus] ?? "return.{$event->toStatus}";

        ActivityLog::create([
            'type'          => $type,
            'actor_type'    => auth()->check() ? 'admin' : 'system',
            'actor_id'      => auth()->id(),
            'actor_name'    => auth()->user()?->name ?? 'System',
            'subject_type'  => 'return_request',
            'subject_id'    => $event->returnRequest->id,
            'subject_label' => $event->returnRequest->return_number,
            'description'   => "Retur {$event->returnRequest->return_number} berubah dari {$event->fromStatus} ke {$event->toStatus}",
            'metadata'      => [],
        ]);
    }
}
