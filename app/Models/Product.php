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
        'discount_percent',
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

    public function getPriceDisplayAttribute(): array
    {
        $svc = app(\App\Services\PricingService::class);
        $variants = $this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)
            : $this->variants()->where('is_active', true)->get();
        if ($variants->isEmpty()) {
            return ['original' => (float) $this->base_price, 'effective' => (float) $this->base_price,
                    'discount_percent' => 0, 'source' => 'none', 'flash_ends_at' => null,
                    'flash_status' => null, 'flash_sale_item_id' => null];
        }
        return $variants
            ->map(fn ($v) => $svc->priceInfo($v->setRelation('product', $this)))
            ->sortBy('effective')->first();
    }

    protected function casts(): array
    {
        return ['discount_percent' => 'decimal:2'];
    }
}