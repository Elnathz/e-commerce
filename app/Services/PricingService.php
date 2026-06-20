<?php
namespace App\Services;
use App\Models\FlashSaleItem;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;

class PricingService {
    public function priceInfo(ProductVariant $v, ?Carbon $at = null): array {
        $at ??= now();
        $original = (float) $v->price;
        $flashStatus = null;

        $item = $this->activeFlashItem($v, $at);
        if ($item) {
            $hasQuotaLeft = $item->quota === null || $item->sold_count < $item->quota;
            if ((float) $item->sale_price < $original && $hasQuotaLeft) {
                return $this->info($original, (float) $item->sale_price, 'flash',
                    optional($item->flashSale)->ends_at?->toISOString(), 'active', $item->id);
            }
            if (!$hasQuotaLeft) $flashStatus = 'sold_out';
        }

        if ($v->discount_price !== null && (float) $v->discount_price < $original) {
            return $this->info($original, (float) $v->discount_price, 'manual', null, $flashStatus, null);
        }
        $pct = (float) ($v->product->discount_percent ?? 0);
        if ($pct > 0) {
            $eff = round($original * (1 - $pct / 100), 2);
            if ($eff < $original) return $this->info($original, $eff, 'manual', null, $flashStatus, null);
        }
        return $this->info($original, $original, 'none', null, $flashStatus, null);
    }

    public function effectivePrice(ProductVariant $v, ?Carbon $at = null): float {
        return $this->priceInfo($v, $at)['effective'];
    }

    public function activeFlashItem(ProductVariant $v, Carbon $at): ?FlashSaleItem {
        return FlashSaleItem::where('product_variant_id', $v->id)
            ->whereHas('flashSale', fn ($q) => $q->activeAt($at))
            ->with('flashSale')->first();
    }

    private function info(float $original, float $eff, string $source, ?string $flashEnds, ?string $flashStatus, ?int $flashItemId): array {
        return [
            'original' => $original,
            'effective' => $eff,
            'discount_percent' => $original > 0 ? (int) round(($original - $eff) / $original * 100) : 0,
            'source' => $source,
            'flash_ends_at' => $flashEnds,
            'flash_status' => $flashStatus,
            'flash_sale_item_id' => $flashItemId,
        ];
    }
}
