<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_variant_id', 'product_name_snapshot',
        'variant_name_snapshot', 'quantity', 'unit_price', 'weight_gram', 'subtotal'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function returnRequestItems()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    /**
     * Item is blocked from review while its return is in an active
     * (in-progress) state: submitted/approved/received/inspected.
     * Once resolved (rejected/refund_processed/completed) or never
     * returned, this is false and the item is reviewable.
     */
    public function hasActiveReturn(): bool
    {
        return ReturnRequestItem::where('order_item_id', $this->id)
            ->whereHas('returnRequest', function ($query) {
                $query->whereIn('status', ['submitted', 'approved', 'received', 'inspected']);
            })
            ->exists();
    }

    /**
     * §1c / #44: review return-badge. Resolves the most recently updated
     * "resolved" return outcome (rejected/refund_processed/completed) for
     * this item — `cancelled` and still-active returns never produce a
     * badge. Requires `returnRequestItems.returnRequest` to be eager-loaded
     * (no additional queries are run here).
     *
     * @return array{outcome: 'refunded'|'requested', return_request_id: int}|null
     */
    public function reviewReturnBadge(): ?array
    {
        $resolved = $this->returnRequestItems
            ->map(fn (ReturnRequestItem $item) => $item->returnRequest)
            ->filter(fn (?ReturnRequest $returnRequest) => $returnRequest && in_array($returnRequest->status, ['rejected', 'refund_processed', 'completed'], true))
            ->sortByDesc(fn (ReturnRequest $returnRequest) => $returnRequest->updated_at)
            ->first();

        if (!$resolved) {
            return null;
        }

        return [
            'outcome' => $resolved->status === 'rejected' ? 'requested' : 'refunded',
            'return_request_id' => $resolved->id,
        ];
    }
}
