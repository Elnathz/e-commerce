<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

trait DispatchesAtomicNotification
{
    /**
     * Ensures a notification or event is only dispatched exactly once.
     * Uses notification_dispatches table with a UNIQUE constraint as a gatekeeper.
     * 
     * @param string $eventKey Unique identifier for this event (e.g. order_paid_order_123)
     * @param callable $dispatchLogic Closure that contains the actual dispatch code
     */
    protected function dispatchAtomicNotification(string $eventKey, callable $dispatchLogic): void
    {
        DB::transaction(function () use ($eventKey, $dispatchLogic) {
            try {
                DB::table('notification_dispatches')->insert([
                    'event_key'     => $eventKey,
                    'dispatched_at' => now(),
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    // 23000 = Integrity constraint violation (Duplicate entry)
                    // The event has already been dispatched by another process. Silent skip.
                    return;
                }
                throw $e;
            }

            // Only executed if the insert was successful (no duplicate)
            $dispatchLogic();
        });
    }
}
