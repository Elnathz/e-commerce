<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'order_item_id', 'user_id', 'product_id', 'rating', 'comment', 'is_published', 'admin_reply', 'replied_at', 'is_edited'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_edited' => 'boolean',
        'replied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function images()
    {
        return $this->hasMany(ReviewImage::class);
    }
}
