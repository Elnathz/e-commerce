<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'return_number', 'order_id', 'user_id', 'status', 'reason', 'is_partial',
        'evidence_image_1', 'evidence_image_2', 'evidence_image_3',
        'refund_amount', 'refund_method', 'admin_notes', 'return_tracking_number', 'return_courier',
        'return_received_at', 'refund_processed_at', 'expires_at'
    ];

    protected $casts = [
        'is_partial' => 'boolean',
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
            'approved' => ['returned', 'expires'],
            'returned' => ['received'],
            'received' => ['refund_processed'],
            'refund_processed' => ['completed'],
        ];

        return in_array($newStatus, $transitions[$this->status] ?? []);
    }
}
