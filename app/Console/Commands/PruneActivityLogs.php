<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class PruneActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity:prune {--days=365}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune activity logs older than a specific number of days';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $days    = (int) $this->option('days');
        $cutoff  = now()->subDays($days);
        $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} activity log records older than {$days} days.");
        Log::channel('daily')->info("ActivityLog pruned", ['deleted' => $deleted]);
    }
}
