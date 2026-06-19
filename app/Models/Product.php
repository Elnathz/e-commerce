<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'base_price',
        'weight_gram',
        'is_active',
        'average_rating',
        'review_count',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function outOfStockVariants()
    {
        return $this->hasMany(ProductVariant::class)->whereRaw('(stock - reserved_stock) <= 0');
    }

    public function criticalVariants()
    {
        return $this->hasMany(ProductVariant::class)->whereRaw('(stock - reserved_stock) > 0');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}