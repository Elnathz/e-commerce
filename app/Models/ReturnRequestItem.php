<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'reason_code',
        'reason_notes',
        'condition',
        'refund_amount',
        'is_restocked',
    ];

    protected $casts = [
        'is_restocked' => 'boolean',
    ];

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
