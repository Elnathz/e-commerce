<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use App\Models\PromotionUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePromotionUsages extends Command
{
    protected $signature   = 'promotions:reconcile';
    protected $description = 'Sync promotion used_count from promotion_usages (source of truth). Run nightly.';

    public function handle(): int
    {
        $this->info('Starting promotion reconciliation...');

        // Single GROUP BY query — O(1) vs O(N) individual COUNT queries
        $trueCounts = DB::table('promotion_usages')
            ->whereIn('status', ['reserved', 'confirmed'])
            ->select('promotion_id', DB::raw('COUNT(*) as true_count'))
            ->groupBy('promotion_id')
            ->pluck('true_count', 'promotion_id');

        $drifted  = 0;
        $synced   = 0;

        // Batch update all promotions
        Promotion::each(function (Promotion $promo) use ($trueCounts, &$drifted, &$synced) {
            $trueCount = (int) ($trueCounts[$promo->id] ?? 0);

            if ($promo->used_count !== $trueCount) {
                $drifted++;
                Log::warning('Promotion used_count drift detected', [
                    'promotion_id' => $promo->id,
                    'code'         => $promo->code,
                    'stored'       => $promo->used_count,
                    'actual'       => $trueCount,
                ]);
                // Skip observer (updateQuietly) — reconciliation is not an admin action
                $promo->updateQuietly(['used_count' => $trueCount]);
            }
            $synced++;
        });

        $this->info("Reconciliation complete. Checked: {$synced}, Drifted: {$drifted}");

        return self::SUCCESS;
    }
}
