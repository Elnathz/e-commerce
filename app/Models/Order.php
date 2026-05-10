<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'status', 'fulfillment_type', 'subtotal',
        'shipping_cost', 'discount_amount', 'total_amount', 'shipping_address_snapshot',
        'notes', 'cancelled_at', 'cancelled_reason', 'courier', 'tracking_number',
        'shipped_at', 'delivered_at'
    ];

    protected $casts = [
        'shipping_address_snapshot' => 'array',
        'cancelled_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
