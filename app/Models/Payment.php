<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'gateway_reference', 'merchant_ref',
        'payment_method', 'payment_channel', 'payment_name',
        'amount', 'fee_amount', 'status',
        'pay_code', 'pay_url',
        'paid_at', 'expired_at', 'callback_payload',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'callback_payload' => 'array',
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
    ];

    /**
     * Payment belongs to an Order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope: only pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if this payment has been finalized (no longer actionable)
     */
    public function isFinalized(): bool
    {
        return in_array($this->status, ['paid', 'failed', 'expired', 'refunded']);
    }
}
