<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\ShippingProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KomerceShippingService implements ShippingProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.rajaongkir.key') ?: '';
        $this->baseUrl = config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1');
    }

    public function calculateCost(int $originCityId, int $destinationCityId, int $weight, string $courier): array
    {
        if (empty($this->apiKey)) {
            Log::error('Komerce Shipping Service: API Key is missing.');
            return [];
        }

        try {
            $response = Http::timeout(15)
                ->asForm()
                ->withHeaders(['key' => $this->apiKey])
                ->post(rtrim($this->baseUrl, '/') . '/calculate/domestic-cost', [
                    'origin' => $originCityId,
                    'destination' => $destinationCityId,
                    'weight' => $weight,
                    'courier' => $courier
                ]);

            if ($response->successful()) {
                $data = $response->json('data') ?: [];
                
                // Map flat Komerce data structure to standard RajaOngkir structure
                $costs = [];
                foreach ($data as $item) {
                    $etd = isset($item['etd']) ? trim(str_replace(['day', 'days'], '', strtolower($item['etd']))) : '';
                    
                    $costs[] = [
                        'service' => $item['service'] ?? '',
                        'description' => $item['description'] ?? '',
                        'cost' => [
                            [
                                'value' => $item['cost'] ?? 0,
                                'etd' => $etd,
                                'note' => ''
                            ]
                        ]
                    ];
                }

                return [
                    [
                        'code' => $courier,
                        'name' => count($data) > 0 ? ($data[0]['name'] ?? strtoupper($courier)) : strtoupper($courier),
                        'costs' => $costs
                    ]
                ];
            }

            Log::error('Komerce Cost API failed: Code ' . $response->status() . ' Body: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Komerce Cost API Exception: ' . $e->getMessage());
        }

        return [];
    }
}
