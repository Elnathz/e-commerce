<?php
namespace App\Services;
use App\Models\ProductVariant;

class FlashSaleService {
    /**
     * Guard against price inversion: a flash sale_price must always be the
     * cheapest price available for the variant. It must be strictly below
     * the normal variant price AND strictly below any active manual
     * discount (discount_price override or product-level discount_percent).
     *
     * The manual-effective price is computed inline here (replicating
     * PricingService's manual precedence) rather than delegating to
     * PricingService::priceInfo(), because priceInfo() may already resolve
     * to an existing active FLASH price (source='flash') for this variant.
     * Comparing a new proposed flash price against an old flash price would
     * be the wrong comparison — we need the MANUAL effective price specifically,
     * with flash interference removed.
     */
    public function assertSalePriceValid(ProductVariant $v, float $salePrice): void {
        $original = (float) $v->price;

        if ($salePrice >= $original) {
            throw new \Exception('Harga flash harus di bawah harga normal varian.');
        }

        $manualEffective = $this->manualEffectivePrice($v, $original);

        if ($manualEffective < $original && $salePrice >= $manualEffective) {
            throw new \Exception('Harga flash harus di bawah harga diskon manual yang berlaku.');
        }
    }

    /**
     * Compute the manual-only effective price, ignoring any flash sale
     * activity entirely. Mirrors PricingService::priceInfo()'s manual
     * branch precedence: discount_price override takes priority over the
     * product-level discount_percent; if neither applies (or doesn't
     * actually lower the price), the effective price is the original price.
     */
    private function manualEffectivePrice(ProductVariant $v, float $original): float {
        if ($v->discount_price !== null && (float) $v->discount_price < $original) {
            return (float) $v->discount_price;
        }

        $pct = (float) ($v->product->discount_percent ?? 0);
        if ($pct > 0) {
            $eff = round($original * (1 - $pct / 100), 2);
            if ($eff < $original) {
                return $eff;
            }
        }

        return $original;
    }
}
