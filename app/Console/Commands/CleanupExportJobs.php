<?php

namespace App\Console\Commands;

use App\Models\ExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExportJobs extends Command
{
    protected $signature   = 'exports:cleanup';
    protected $description = 'Delete expired export files and job records.';

    public function handle(): int
    {
        $expired = ExportJob::where('expires_at', '<', now())
            ->where('status', 'completed')
            ->get();

        $count = 0;
        foreach ($expired as $job) {
            if ($job->file_path && Storage::exists($job->file_path)) {
                Storage::delete($job->file_path);
            }
            $job->delete();
            $count++;
        }

        // Also clean failed jobs older than 7 days
        ExportJob::where('status', 'failed')
            ->where('created_at', '<', now()->subDays(7))
            ->delete();

        $this->info("Cleaned up {$count} expired export jobs.");
        return self::SUCCESS;
    }
}
