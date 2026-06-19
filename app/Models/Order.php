<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'status', 'fulfillment_type', 'subtotal',
        'shipping_cost', 'discount_amount', 'voucher_code', 'discount_on_shipping', 'total_amount', 'shipping_address_snapshot',
        'notes', 'cancelled_at', 'cancelled_reason', 'courier', 'shipping_service', 'tracking_number',
        'shipped_at', 'delivered_at', 'completed_at', 'expired_at',
        'payment_method', 'payment_status', 'paid_at', 'refund_status',
    ];

    protected $casts = [
        'shipping_address_snapshot' => 'array',
        'cancelled_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasManyThrough(Review::class, OrderItem::class);
    }

    /**
     * All payment attempts for this order (1:N - supports retry)
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The latest pending payment (if any)
     */
    public function activePayment()
    {
        return $this->hasOne(Payment::class)->where('status', 'pending')->latest();
    }

    /**
     * Check if order has been paid
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if order can be cancelled by customer
     */
    public function canBeCancelled(): bool
    {
        return $this->status === 'pending' && $this->payment_status !== 'paid';
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class, 'order_id');
    }

    public function getRemainingReturnableItems()
    {
        $returnedItemsQty = \Illuminate\Support\Facades\DB::table('return_request_items')
            ->join('return_requests', 'return_requests.id', '=', 'return_request_items.return_request_id')
            ->where('return_requests.order_id', $this->id)
            ->whereNotIn('return_requests.status', ['rejected', 'cancelled'])
            ->select('order_item_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as returned_qty'))
            ->groupBy('order_item_id')
            ->get()
            ->keyBy('order_item_id');

        $remaining = [];
        foreach ($this->items as $item) {
            $returned = $returnedItemsQty->has($item->id) ? $returnedItemsQty->get($item->id)->returned_qty : 0;
            $qty = $item->quantity - $returned;
            if ($qty > 0) {
                $remaining[$item->id] = $qty;
            }
        }
        return $remaining;
    }

    /**
     * Check if order can be returned by customer
     */
    public function canBeReturned(): bool
    {
        if (!in_array($this->status, ['shipped', 'completed'])) {
            return false;
        }

        $referenceDate = $this->completed_at ?? $this->delivered_at;
        if ($referenceDate && $referenceDate->diffInDays(now()) > 7) {
            return false;
        }

        return count($this->getRemainingReturnableItems()) > 0;
    }
}
