<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'status', 'fulfillment_type', 'subtotal',
        'shipping_cost', 'discount_amount', 'total_amount', 'shipping_address_snapshot',
        'notes', 'cancelled_at', 'cancelled_reason', 'courier', 'shipping_service', 'tracking_number',
        'shipped_at', 'delivered_at', 'expired_at',
        'payment_method', 'payment_status', 'paid_at',
    ];

    protected $casts = [
        'shipping_address_snapshot' => 'array',
        'cancelled_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
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

    /**
     * The return request for this order (if any)
     */
    public function returnRequest()
    {
        return $this->hasOne(ReturnRequest::class, 'order_id');
    }

    /**
     * Check if order can be returned by customer
     */
    public function canBeReturned(): bool
    {
        return in_array($this->status, ['shipped', 'completed']) && !$this->returnRequest()->exists();
    }
}
