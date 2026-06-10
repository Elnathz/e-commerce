<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activitylogs:cleanup {--days=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus log aktivitas yang lebih lama dari N hari (default 30)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $date = now()->subDays($days);

        $deleted = \App\Models\ActivityLog::where('created_at', '<', $date)->delete();

        $this->info("Berhasil menghapus {$deleted} baris log aktivitas (lebih lama dari {$days} hari).");
    }
}
