<?php

namespace App\Observers;

use App\Models\Promotion;
use App\Models\PromotionHistory;
use Illuminate\Support\Facades\Auth;

class PromotionObserver
{
    public function created(Promotion $promotion): void
    {
        PromotionHistory::create([
            'promotion_id' => $promotion->id,
            'admin_id'     => Auth::id(),
            'action'       => 'created',
            'changes'      => ['after' => $promotion->toArray()],
        ]);
    }

    public function updating(Promotion $promotion): void
    {
        $dirty = $promotion->getDirty();
        if (empty($dirty)) return;

        // Build JSON snapshot: {before: {field: old}, after: {field: new}}
        $before = [];
        $after  = [];
        foreach ($dirty as $field => $newValue) {
            $before[$field] = $promotion->getOriginal($field);
            $after[$field]  = $newValue;
        }

        PromotionHistory::create([
            'promotion_id' => $promotion->id,
            'admin_id'     => Auth::id() ?? 0, // 0 = system/scheduled
            'action'       => isset($dirty['is_active'])
                ? ($dirty['is_active'] ? 'reactivated' : 'deactivated')
                : 'updated',
            'changes' => compact('before', 'after'),
        ]);
    }
}
