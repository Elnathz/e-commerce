<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashSale extends Model
{
    protected $fillable = ['name', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function items()
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    public function scopeActiveAt($q, $at)
    {
        return $q->where('is_active', true)->where('starts_at', '<=', $at)->where('ends_at', '>', $at);
    }
}
