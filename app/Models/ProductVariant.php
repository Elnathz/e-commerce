<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'variant_type',
        'price',
        'discount_price',
        'stock',
        'reserved_stock',
        'weight_gram',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_variant_id');
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'discount_price' => 'decimal:2'];
    }
}
