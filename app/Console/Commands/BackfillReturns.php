<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReturnRequest;

class BackfillReturns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-returns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill legacy return requests to have return_request_items and return_histories';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backfill for legacy returns...');

        $returns = ReturnRequest::whereDoesntHave('items')->get();
        $count = 0;

        foreach ($returns as $ret) {
            // Check if it has order items
            $order = $ret->order;
            if (!$order || $order->items->isEmpty()) {
                continue;
            }

            // Create items assuming full return for legacy if not partial, or just first item if partial (since we didn't track it)
            // But honestly let's just add all items since it's legacy
            foreach ($order->items as $orderItem) {
                $ret->items()->create([
                    'order_item_id' => $orderItem->id,
                    'quantity' => $orderItem->quantity,
                    'reason_code' => 'other',
                    'reason_notes' => 'Legacy migrated return',
                    'condition' => 'other',
                    'refund_amount' => 0,
                ]);
            }

            // Create basic history
            if ($ret->histories()->count() === 0) {
                $ret->histories()->create([
                    'from_status' => null,
                    'to_status' => $ret->status,
                    'actor_id' => $ret->user_id,
                    'actor_type' => 'App\Models\User',
                    'notes' => 'Legacy return backfilled.',
                ]);
            }

            $count++;
        }

        $this->info("Backfilled $count legacy returns.");
    }
}
