<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'return_number', 'order_id', 'user_id', 'status', 'reason', 'inspection_result',
        'evidence_image_1', 'evidence_image_2', 'evidence_image_3', 'evidence_image_4', 'evidence_image_5',
        'refund_amount', 'refund_method', 'admin_notes', 'return_tracking_number', 'return_courier',
        'return_received_at', 'refund_processed_at', 'expires_at'
    ];

    protected $casts = [
        'return_received_at' => 'datetime',
        'refund_processed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            'submitted' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['waiting_customer_shipment', 'expires'],
            'waiting_customer_shipment' => ['customer_shipped', 'expires'],
            'customer_shipped' => ['received'],
            'received' => ['inspected', 'rejected'],
            'inspected' => ['refund_processed', 'completed'],
            'refund_processed' => ['completed'],
        ];

        return in_array($newStatus, $transitions[$this->status] ?? []);
    }

    public function items()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function histories()
    {
        return $this->hasMany(ReturnHistory::class);
    }
}
