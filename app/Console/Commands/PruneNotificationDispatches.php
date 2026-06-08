<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNotificationDispatches extends Command
{
    protected $signature   = 'notifications:prune-dispatches';
    protected $description = 'Remove notification_dispatches records older than 30 days.';

    public function handle(): int
    {
        $deleted = DB::table('notification_dispatches')
            ->where('dispatched_at', '<', now()->subDays(30))
            ->delete();

        $this->info("Pruned {$deleted} old notification dispatch records.");
        return self::SUCCESS;
    }
}
