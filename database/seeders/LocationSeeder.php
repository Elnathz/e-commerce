<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\Province;
use App\Models\City;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apiKey = env('RAJAONGKIR_API_KEY');

        if (!$apiKey) {
            $this->command->error('RAJAONGKIR_API_KEY is missing in .env');
            return;
        }

        $this->command->info('Fetching provinces from RajaOngkir...');
        $response = Http::timeout(30)->withHeaders(['key' => $apiKey])
            ->get('https://api.rajaongkir.com/starter/province');

        if ($response->successful()) {
            $provinces = $response->json('rajaongkir.results');
            foreach ($provinces as $province) {
                Province::updateOrCreate(
                    ['id' => $province['province_id']],
                    ['name' => $province['province']]
                );
            }
            $this->command->info('Provinces seeded successfully.');
        } else {
            $this->command->error('Failed to fetch provinces: ' . $response->body());
        }

        $this->command->info('Fetching cities from RajaOngkir...');
        $response = Http::timeout(30)->withHeaders(['key' => $apiKey])
            ->get('https://api.rajaongkir.com/starter/city');

        if ($response->successful()) {
            $cities = $response->json('rajaongkir.results');
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
            $this->command->info('Cities seeded successfully.');
        } else {
            $this->command->error('Failed to fetch cities: ' . $response->body());
        }
    }
}
