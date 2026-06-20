<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\FlashSale;
use Illuminate\Support\Facades\Auth;

class FlashSaleObserver
{
    public function created(FlashSale $flashSale): void
    {
        ActivityLog::create([
            'type'          => 'flash_sale.created',
            'actor_type'    => Auth::check() ? 'admin' : 'system',
            'actor_id'      => Auth::id(),
            'actor_name'    => Auth::user()?->name ?? 'System',
            'subject_type'  => 'flash_sale',
            'subject_id'    => $flashSale->id,
            'subject_label' => $flashSale->name,
            'description'   => "Flash Sale {$flashSale->name} dibuat",
            'metadata'      => ['after' => $flashSale->toArray()],
        ]);
    }

    public function updated(FlashSale $flashSale): void
    {
        $dirty = $flashSale->getDirty();
        if (empty($dirty)) return;

        // Build JSON snapshot: {before: {field: old}, after: {field: new}}
        $before = [];
        $after  = [];
        foreach ($dirty as $field => $newValue) {
            $before[$field] = $flashSale->getOriginal($field);
            $after[$field]  = $newValue;
        }

        ActivityLog::create([
            'type'          => 'flash_sale.updated',
            'actor_type'    => Auth::check() ? 'admin' : 'system',
            'actor_id'      => Auth::id(),
            'actor_name'    => Auth::user()?->name ?? 'System',
            'subject_type'  => 'flash_sale',
            'subject_id'    => $flashSale->id,
            'subject_label' => $flashSale->name,
            'description'   => "Flash Sale {$flashSale->name} diperbarui",
            'metadata'      => compact('before', 'after'),
        ]);
    }

    public function deleted(FlashSale $flashSale): void
    {
        ActivityLog::create([
            'type'          => 'flash_sale.deleted',
            'actor_type'    => Auth::check() ? 'admin' : 'system',
            'actor_id'      => Auth::id(),
            'actor_name'    => Auth::user()?->name ?? 'System',
            'subject_type'  => 'flash_sale',
            'subject_id'    => $flashSale->id,
            'subject_label' => $flashSale->name,
            'description'   => "Flash Sale {$flashSale->name} dihapus",
            'metadata'      => ['before' => $flashSale->toArray()],
        ]);
    }
}
