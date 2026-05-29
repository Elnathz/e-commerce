<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\ShippingProviderInterface;
use Illuminate\Support\Facades\Cache;

class ShippingService
{
    protected ShippingProviderInterface $provider;

    public function __construct(ShippingProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Get shipping costs (with caching).
     *
     * @param int $originCityId
     * @param int $destinationId (City ID or District ID based on active provider)
     * @param int $weight In grams
     * @param string $courier
     * @return array
     */
    public function getShippingCosts(int $originCityId, int $destinationId, int $weight, string $courier): array
    {
        // Define clean cache key
        $cacheKey = sprintf(
            'shipping_cost:%d:%d:%s:%d',
            $originCityId,
            $destinationId,
            strtolower(trim($courier)),
            $weight
        );

        // Cache TTL: 12 hours (43200 seconds) - production safe
        return Cache::remember($cacheKey, 43200, function () use ($originCityId, $destinationId, $weight, $courier) {
            return $this->provider->calculateCost($originCityId, $destinationId, $weight, $courier);
        });
    }

    /**
     * Clear shipping cost cache for specific parameters.
     */
    public function clearCostCache(int $originCityId, int $destinationId, int $weight, string $courier): bool
    {
        $cacheKey = sprintf(
            'shipping_cost:%d:%d:%s:%d',
            $originCityId,
            $destinationId,
            strtolower(trim($courier)),
            $weight
        );

        return Cache::forget($cacheKey);
    }
}
