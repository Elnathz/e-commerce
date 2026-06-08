<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionUsage extends Model
{
    protected $fillable = [
        'promotion_id', 'user_id', 'order_id',
        'status', 'discount_applied',
        'confirmed_at', 'released_at',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'confirmed_at'     => 'datetime',
        'released_at'      => 'datetime',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
