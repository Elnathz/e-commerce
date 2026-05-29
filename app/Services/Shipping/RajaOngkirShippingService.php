<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\ShippingProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RajaOngkirShippingService implements ShippingProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.rajaongkir.key') ?: '';
        $this->baseUrl = config('services.rajaongkir.base_url', 'https://api.rajaongkir.com/starter');
    }

    public function calculateCost(int $originCityId, int $destinationCityId, int $weight, string $courier): array
    {
        if (empty($this->apiKey)) {
            Log::error('RajaOngkir Shipping Service: API Key is missing.');
            return [];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['key' => $this->apiKey])
                ->post(rtrim($this->baseUrl, '/') . '/cost', [
                    'origin' => $originCityId,
                    'destination' => $destinationCityId,
                    'weight' => $weight,
                    'courier' => $courier
                ]);

            if ($response->successful()) {
                return $response->json('rajaongkir.results') ?: [];
            }

            Log::error('RajaOngkir Cost API failed: Code ' . $response->status() . ' Body: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('RajaOngkir Cost API Exception: ' . $e->getMessage());
        }

        return [];
    }
}
