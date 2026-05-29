<?php

namespace App\Services\Shipping\Contracts;

interface ShippingProviderInterface
{
    /**
     * Calculate shipping cost.
     *
     * @param int $originCityId Provider-specific origin city ID
     * @param int $destinationCityId Provider-specific destination ID (city or subdistrict)
     * @param int $weight Weight in grams
     * @param string $courier Courier code (jne, pos, tiki, etc.)
     * @return array Standardized array of courier services and costs
     */
    public function calculateCost(int $originCityId, int $destinationCityId, int $weight, string $courier): array;
}
