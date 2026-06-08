<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MonitorQueueHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor queue health and alert if backlog is too old.';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\Alerting\SystemAlertService $alertService)
    {
        $queues = [
            'payment' => ['critical' => 5], // 5 mins
            'refund' => ['critical' => 5],
            'reconcile' => ['critical' => 5],
            'default' => ['warning' => 15], // 15 mins
            'emails' => ['warning' => 15],
            'exports' => ['warning' => 15],
        ];

        foreach ($queues as $queueName => $thresholds) {
            $size = \Illuminate\Support\Facades\Queue::size($queueName);
            
            if ($size > 0) {
                // In database driver, we can query jobs directly
                $oldestJob = \Illuminate\Support\Facades\DB::table('jobs')
                    ->where('queue', $queueName)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($oldestJob) {
                    // created_at is timestamp in jobs table
                    $ageInMinutes = round((time() - $oldestJob->created_at) / 60);

                    if (isset($thresholds['critical']) && $ageInMinutes >= $thresholds['critical']) {
                        $alertService->dispatch('Critical Queue Backlog', 'critical', [
                            'queue' => $queueName,
                            'size' => $size,
                            'age_minutes' => $ageInMinutes,
                        ]);
                    } elseif (isset($thresholds['warning']) && $ageInMinutes >= $thresholds['warning']) {
                        $alertService->dispatch('Queue Backlog Warning', 'warning', [
                            'queue' => $queueName,
                            'size' => $size,
                            'age_minutes' => $ageInMinutes,
                        ]);
                    }
                }
            }
        }

        $this->info('Queue health check completed.');
    }
}
