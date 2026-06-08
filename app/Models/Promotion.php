<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'value', 'min_purchase',
        'max_usage', 'max_usage_per_user',
        'max_shipping_discount', 'applicable_shipping_type',
        'used_count', 'valid_from', 'valid_until', 'is_active', 'description',
    ];

    protected $casts = [
        'value'                  => 'decimal:2',
        'min_purchase'           => 'decimal:2',
        'max_shipping_discount'  => 'decimal:2',
        'is_active'              => 'boolean',
        'valid_from'             => 'datetime',
        'valid_until'            => 'datetime',
    ];

    public function usages()
    {
        return $this->hasMany(PromotionUsage::class);
    }

    public function histories()
    {
        return $this->hasMany(PromotionHistory::class);
    }

    /** Effective active usage = reserved + confirmed (source of truth for auditing) */
    public function effectiveUsedCount(): int
    {
        return $this->usages()->whereIn('status', ['reserved', 'confirmed'])->count();
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active
            && !$this->isExpired()
            && (!$this->valid_from || $this->valid_from->isPast());
    }
}
