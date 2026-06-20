<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashSaleItem extends Model
{
    protected $fillable = ['flash_sale_id', 'product_variant_id', 'sale_price', 'quota', 'sold_count'];

    protected function casts(): array
    {
        return ['sale_price' => 'decimal:2'];
    }

    public function flashSale()
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
