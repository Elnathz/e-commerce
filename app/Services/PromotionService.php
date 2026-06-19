<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\PromotionHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class PromotionService
{
    /**
     * Validate voucher eligibility WITHOUT reserving quota.
     * Used for real-time validation in cart UI.
     */
    public function validate(string $code, float $subtotal, float $shippingCost, ?string $courierType = null, bool $containsFlash = false): array
    {
        $promotion = Promotion::where('code', strtoupper($code))->first();

        if (!$promotion) {
            return ['valid' => false, 'message' => 'Kode voucher tidak ditemukan.'];
        }

        if (!$promotion->isValid()) {
            if ($promotion->isExpired()) {
                return ['valid' => false, 'message' => 'Voucher sudah kadaluarsa.'];
            }
            return ['valid' => false, 'message' => 'Voucher tidak aktif.'];
        }

        if ($subtotal < $promotion->min_purchase) {
            return [
                'valid'   => false,
                'message' => 'Minimum pembelian Rp ' . number_format($promotion->min_purchase, 0, ',', '.'),
            ];
        }

        // Fast-path global quota check (uses used_count cache)
        if ($promotion->max_usage && $promotion->used_count >= $promotion->max_usage) {
            return ['valid' => false, 'message' => 'Kuota voucher sudah habis.'];
        }

        // Free shipping scope check
        if ($promotion->type === 'free_shipping' && $courierType) {
            if ($promotion->applicable_shipping_type !== 'all') {
                $isInternal = $courierType === 'internal';
                if ($promotion->applicable_shipping_type === 'internal' && !$isInternal) {
                    return ['valid' => false, 'message' => 'Voucher ini hanya berlaku untuk pengiriman area Semarang.'];
                }
                if ($promotion->applicable_shipping_type === 'external' && $isInternal) {
                    return ['valid' => false, 'message' => 'Voucher ini hanya berlaku untuk pengiriman luar Semarang.'];
                }
            }
        }

        // FR030 — Flash Sale gate: voucher must opt in via applies_to_flash_sale
        // to be usable on a cart that contains Flash Sale items.
        if ($containsFlash && !$promotion->applies_to_flash_sale) {
            return ['valid' => false, 'message' => 'Voucher ini tidak berlaku untuk pesanan yang berisi produk Flash Sale.'];
        }

        $discountAmount = $this->calculateDiscount($promotion, $subtotal, $shippingCost);

        return [
            'valid'           => true,
            'promotion_id'    => $promotion->id,
            'code'            => $promotion->code,
            'type'            => $promotion->type,
            'discount_amount' => $discountAmount,
            'message'         => 'Voucher berhasil diterapkan.',
        ];
    }

    /**
     * Phase 1 — Reserve voucher at checkout time.
     * Uses pessimistic lock (lockForUpdate) + used_count fast-path.
     * Source of truth for audit: promotion_usages table.
     */
    public function reserve(string $code, int $userId, int $orderId, float $subtotal, float $shippingCost, ?string $courierType = null, bool $containsFlash = false): PromotionUsage
    {
        return DB::transaction(function () use ($code, $userId, $orderId, $subtotal, $shippingCost, $courierType, $containsFlash) {
            $promotion = Promotion::where('code', strtoupper($code))
                ->lockForUpdate()
                ->firstOrFail();

            // Full eligibility validation inside lock
            $this->assertEligible($promotion, $userId, $subtotal, $shippingCost, $courierType, $containsFlash);

            $discountAmount = $this->calculateDiscount($promotion, $subtotal, $shippingCost);

            // Increment used_count (fast-path cache counter)
            $promotion->increment('used_count');

            return PromotionUsage::create([
                'promotion_id'    => $promotion->id,
                'user_id'         => $userId,
                'order_id'        => $orderId,
                'status'          => 'reserved',
                'discount_applied' => $discountAmount,
            ]);
        });
    }

    /**
     * Phase 2A — Confirm voucher when order is paid.
     * Called by OrderPaid event listener.
     */
    public function confirm(int $orderId): void
    {
        PromotionUsage::where('order_id', $orderId)
            ->where('status', 'reserved')
            ->update(['status' => 'confirmed', 'confirmed_at' => now()]);
    }

    /**
     * Phase 2B — Release voucher when order expires or is cancelled.
     * Restores quota by decrementing used_count.
     * Called by OrderCancelled / OrderExpired event listeners.
     */
    public function release(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $usage = PromotionUsage::where('order_id', $orderId)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->first();

            if (!$usage) return; // Already released or no voucher on this order

            $usage->update(['status' => 'released', 'released_at' => now()]);

            // Restore quota in used_count (cache counter)
            Promotion::where('id', $usage->promotion_id)->decrement('used_count');
        });
    }

    /**
     * Calculate discount amount based on promotion type.
     */
    public function calculateDiscount(Promotion $promotion, float $subtotal, float $shippingCost): float
    {
        return match ($promotion->type) {
            'percentage'   => min(round($subtotal * ($promotion->value / 100), 2), $subtotal),
            'fixed_amount' => min($promotion->value, $subtotal),
            'free_shipping' => $promotion->max_shipping_discount !== null
                ? min($shippingCost, $promotion->max_shipping_discount)
                : $shippingCost,
            default => 0,
        };
    }

    /**
     * Full eligibility assertion (throws on failure).
     */
    protected function assertEligible(Promotion $promotion, int $userId, float $subtotal, float $shippingCost, ?string $courierType, bool $containsFlash = false): void
    {
        if (!$promotion->isValid()) {
            throw new \Exception('Voucher tidak valid atau sudah kadaluarsa.');
        }

        // FR030 — Flash Sale gate: voucher must opt in via applies_to_flash_sale
        // to be usable on a cart that contains Flash Sale items.
        if ($containsFlash && !$promotion->applies_to_flash_sale) {
            throw new \Exception('Voucher ini tidak berlaku untuk pesanan yang berisi produk Flash Sale.');
        }

        if ($subtotal < $promotion->min_purchase) {
            throw new \Exception('Minimum pembelian tidak terpenuhi.');
        }

        // Global quota — used_count fast-path
        if ($promotion->max_usage && $promotion->used_count >= $promotion->max_usage) {
            throw new \Exception('Kuota voucher sudah habis.');
        }

        // Per-user limit — must query promotion_usages (used_count doesn't track per-user)
        if ($promotion->max_usage_per_user) {
            $userUsed = PromotionUsage::where('promotion_id', $promotion->id)
                ->where('user_id', $userId)
                ->whereIn('status', ['reserved', 'confirmed'])
                ->count();

            if ($userUsed >= $promotion->max_usage_per_user) {
                throw new \Exception('Anda sudah mencapai batas penggunaan voucher ini.');
            }
        }

        // Free shipping courier scope
        if ($promotion->type === 'free_shipping' && $courierType && $promotion->applicable_shipping_type !== 'all') {
            $isInternal = $courierType === 'internal';
            if ($promotion->applicable_shipping_type === 'internal' && !$isInternal) {
                throw new \Exception('Voucher ini hanya berlaku untuk pengiriman area Semarang.');
            }
            if ($promotion->applicable_shipping_type === 'external' && $isInternal) {
                throw new \Exception('Voucher ini hanya berlaku untuk pengiriman luar Semarang.');
            }
        }
    }
}
