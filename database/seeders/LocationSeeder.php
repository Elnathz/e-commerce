<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\Province;
use App\Models\City;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Fetches provinces and cities from RajaOngkir API.
     * Falls back to hardcoded province data if API is unreachable.
     */
    public function run(): void
    {
        $apiKey = config('services.rajaongkir.key') ?: env('RAJAONGKIR_API_KEY');
        $baseUrl = config('services.rajaongkir.base_url', 'https://api.rajaongkir.com/starter');

        if (!$apiKey) {
            $this->command->error('RAJAONGKIR_API_KEY is missing. Add it to .env');
            return;
        }

        // === SEED PROVINCES ===
        $provincesSeeded = $this->seedProvinces($apiKey, $baseUrl);

        if (!$provincesSeeded) {
            $this->command->warn('API failed. Using hardcoded Indonesian provinces.');
            $this->seedHardcodedProvinces();
            $provincesSeeded = true;
        }

        // === SEED CITIES ===
        if ($provincesSeeded) {
            $citiesSeeded = $this->seedCities($apiKey, $baseUrl);

            if (!$citiesSeeded) {
                $this->command->warn('Cities API also failed. Cities will need to be seeded later when API is available.');
                $this->command->info('Run: php artisan db:seed --class=LocationSeeder');
            }
        }
    }

    private function seedProvinces(string $apiKey, string $baseUrl): bool
    {
        $this->command->info('Fetching provinces from RajaOngkir/Komerce API...');
        $isKomerce = str_contains($baseUrl, 'komerce.id');

        try {
            $url = $isKomerce 
                ? rtrim($baseUrl, '/') . '/destination/province'
                : rtrim($baseUrl, '/') . '/province';

            $response = Http::retry(3, 2000)
                ->timeout(15)
                ->withHeaders(['key' => $apiKey])
                ->get($url);

            if ($response->successful()) {
                $provinces = $isKomerce ? $response->json('data') : $response->json('rajaongkir.results');

                if (!empty($provinces)) {
                    foreach ($provinces as $province) {
                        $provId = $isKomerce ? $province['id'] : $province['province_id'];
                        $provName = $isKomerce ? $province['name'] : $province['province'];

                        Province::updateOrCreate(
                            ['id' => $provId],
                            ['name' => $provName]
                        );
                    }
                    $this->command->info('✓ Provinces seeded from API: ' . count($provinces) . ' rows.');
                    return true;
                }
            }

            $this->command->error('API returned non-success: ' . $response->status());
            return false;
        } catch (\Exception $e) {
            $this->command->error('API connection failed: ' . $e->getMessage());
            return false;
        }
    }

    private function seedCities(string $apiKey, string $baseUrl): bool
    {
        $this->command->info('Fetching cities from RajaOngkir/Komerce API...');
        $isKomerce = str_contains($baseUrl, 'komerce.id');

        try {
            if ($isKomerce) {
                // Komerce does not have a bulk fetch all cities endpoint.
                // We must iterate over existing provinces in the database and fetch cities for each.
                $provinces = Province::all();
                if ($provinces->isEmpty()) {
                    $this->command->error('No provinces in database. Seed provinces first.');
                    return false;
                }

                $totalCitiesCount = 0;
                $this->command->info('Komerce API detected: Fetching cities for ' . $provinces->count() . ' provinces sequentially...');
                
                foreach ($provinces as $province) {
                    $this->command->comment("Fetching cities for province: {$province->name}...");
                    
                    $url = rtrim($baseUrl, '/') . '/destination/city/' . $province->id;
                    $response = Http::retry(2, 1000)
                        ->timeout(15)
                        ->withHeaders(['key' => $apiKey])
                        ->get($url);

                    if ($response->successful()) {
                        $cities = $response->json('data') ?: [];
                        foreach ($cities as $city) {
                            City::updateOrCreate(
                                ['id' => $city['id']],
                                [
                                    'province_id' => $province->id,
                                    'name' => $city['name'],
                                    'type' => $city['type'] ?? '',
                                    'postal_code' => $city['postal_code'] ?? null
                                ]
                            );
                            $totalCitiesCount++;
                        }
                    } else {
                        $this->command->error("Failed to fetch cities for province {$province->name}: Code " . $response->status());
                    }
                }
                
                $this->command->info("✓ Cities seeded from Komerce API: {$totalCitiesCount} rows.");
                return true;
            } else {
                // Official RajaOngkir API (bulk fetch)
                $response = Http::retry(3, 2000)
                    ->timeout(30)
                    ->withHeaders(['key' => $apiKey])
                    ->get(rtrim($baseUrl, '/') . '/city');

                if ($response->successful()) {
                    $cities = $response->json('rajaongkir.results');

                    if (!empty($cities)) {
                        foreach ($cities as $city) {
                            City::updateOrCreate(
                                ['id' => $city['city_id']],
                                [
                                    'province_id' => $city['province_id'],
                                    'name' => $city['city_name'],
                                    'type' => $city['type'],
                                    'postal_code' => $city['postal_code']
                                ]
                            );
                        }
                        $this->command->info('✓ Cities seeded from API: ' . count($cities) . ' rows.');
                        return true;
                    }
                }

                $this->command->error('Cities API returned non-success: ' . $response->status());
                return false;
            }
        } catch (\Exception $e) {
            $this->command->error('Cities API connection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hardcoded 34 provinces of Indonesia with RajaOngkir IDs.
     * Used as fallback when API is unreachable.
     */
    private function seedHardcodedProvinces(): void
    {
        $provinces = [
            ['id' => 1,  'name' => 'Bali'],
            ['id' => 2,  'name' => 'Bangka Belitung'],
            ['id' => 3,  'name' => 'Banten'],
            ['id' => 4,  'name' => 'Bengkulu'],
            ['id' => 5,  'name' => 'DI Yogyakarta'],
            ['id' => 6,  'name' => 'DKI Jakarta'],
            ['id' => 7,  'name' => 'Gorontalo'],
            ['id' => 8,  'name' => 'Jambi'],
            ['id' => 9,  'name' => 'Jawa Barat'],
            ['id' => 10, 'name' => 'Jawa Tengah'],
            ['id' => 11, 'name' => 'Jawa Timur'],
            ['id' => 12, 'name' => 'Kalimantan Barat'],
            ['id' => 13, 'name' => 'Kalimantan Selatan'],
            ['id' => 14, 'name' => 'Kalimantan Tengah'],
            ['id' => 15, 'name' => 'Kalimantan Timur'],
            ['id' => 16, 'name' => 'Kalimantan Utara'],
            ['id' => 17, 'name' => 'Kepulauan Riau'],
            ['id' => 18, 'name' => 'Lampung'],
            ['id' => 19, 'name' => 'Maluku'],
            ['id' => 20, 'name' => 'Maluku Utara'],
            ['id' => 21, 'name' => 'Nanggroe Aceh Darussalam (NAD)'],
            ['id' => 22, 'name' => 'Nusa Tenggara Barat (NTB)'],
            ['id' => 23, 'name' => 'Nusa Tenggara Timur (NTT)'],
            ['id' => 24, 'name' => 'Papua'],
            ['id' => 25, 'name' => 'Papua Barat'],
            ['id' => 26, 'name' => 'Riau'],
            ['id' => 27, 'name' => 'Sulawesi Barat'],
            ['id' => 28, 'name' => 'Sulawesi Selatan'],
            ['id' => 29, 'name' => 'Sulawesi Tengah'],
            ['id' => 30, 'name' => 'Sulawesi Tenggara'],
            ['id' => 31, 'name' => 'Sulawesi Utara'],
            ['id' => 32, 'name' => 'Sumatera Barat'],
            ['id' => 33, 'name' => 'Sumatera Selatan'],
            ['id' => 34, 'name' => 'Sumatera Utara'],
        ];

        foreach ($provinces as $province) {
            Province::updateOrCreate(
                ['id' => $province['id']],
                ['name' => $province['name']]
            );
        }

        $this->command->info('✓ Hardcoded provinces seeded: ' . count($provinces) . ' rows.');
    }
}
