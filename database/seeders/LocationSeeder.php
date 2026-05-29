<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Province;
use App\Models\City;
use App\Models\District;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds provinces, cities, and districts from a static offline JSON dataset.
     */
    public function run(): void
    {
        $filePath = base_path('database/data/indonesia_wilayah.json');

        if (!file_exists($filePath)) {
            $this->command->error("Offline location data file not found at: {$filePath}");
            $this->command->info("Please run the generator script first:");
            $this->command->info("php scratch/generate_full_dataset.php");
            return;
        }

        $this->command->info('Loading offline location data from JSON...');
        $data = json_decode(file_get_contents($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Failed to parse JSON file: ' . json_last_error_msg());
            return;
        }

        $provincesJson = $data['provinces'] ?? [];
        $citiesJson = $data['cities'] ?? [];
        $districtsJson = $data['districts'] ?? [];

        $this->command->info("Found: " . count($provincesJson) . " provinces, " . count($citiesJson) . " cities, " . count($districtsJson) . " districts.");

        // === 1. SEED PROVINCES ===
        $this->command->info('Seeding provinces...');
        $provMap = []; // Maps rajaongkir_province_id -> internal Province ID

        DB::transaction(function () use ($provincesJson, &$provMap) {
            foreach ($provincesJson as $p) {
                // Use rajaongkir_province_id as the primary lookup key
                $lookupKey = $p['rajaongkir_province_id'] ?? $p['komerce_province_id'] ?? null;
                if (!$lookupKey) continue;

                $province = Province::updateOrCreate(
                    ['rajaongkir_province_id' => $p['rajaongkir_province_id']],
                    [
                        'name' => $p['name'],
                        'komerce_province_id' => $p['komerce_province_id'] ?? null,
                    ]
                );

                // Map both rajaongkir and komerce province IDs to internal ID
                if ($p['rajaongkir_province_id']) {
                    $provMap['ro_' . $p['rajaongkir_province_id']] = $province->id;
                }
                if (!empty($p['komerce_province_id'])) {
                    $provMap['ko_' . $p['komerce_province_id']] = $province->id;
                }
            }
        });
        $this->command->info('✓ Provinces seeded: ' . Province::count());

        // === 2. SEED CITIES (CHUNKED FOR PERFORMANCE) ===
        $this->command->info('Seeding cities...');
        $cityMap = []; // Maps rajaongkir_city_id -> internal City ID

        DB::transaction(function () use ($citiesJson, $provMap, &$cityMap) {
            foreach ($citiesJson as $c) {
                // Resolve province: try rajaongkir first, then komerce
                $internalProvId = $provMap['ro_' . ($c['rajaongkir_province_id'] ?? '')] 
                    ?? $provMap['ko_' . ($c['komerce_province_id'] ?? '')] 
                    ?? null;
                
                if (!$internalProvId) {
                    continue; // Skip if parent province is not seeded
                }

                $lookupField = !empty($c['rajaongkir_city_id']) ? 'rajaongkir_city_id' : 'komerce_city_id';
                $lookupValue = $c[$lookupField];

                $city = City::updateOrCreate(
                    [$lookupField => $lookupValue],
                    [
                        'province_id' => $internalProvId,
                        'name' => $c['name'],
                        'type' => $c['type'],
                        'postal_code' => $c['postal_code'],
                        'rajaongkir_city_id' => $c['rajaongkir_city_id'] ?? null,
                        'komerce_city_id' => $c['komerce_city_id'] ?? null,
                    ]
                );

                // Map both IDs to internal ID
                if (!empty($c['rajaongkir_city_id'])) {
                    $cityMap['ro_' . $c['rajaongkir_city_id']] = $city->id;
                }
                if (!empty($c['komerce_city_id'])) {
                    $cityMap['ko_' . $c['komerce_city_id']] = $city->id;
                }
            }
        });
        $this->command->info('✓ Cities seeded: ' . City::count());

        // === 3. SEED DISTRICTS (BULK INSERT WITH CHUNKS FOR SPEED) ===
        $this->command->info('Seeding districts in chunks...');
        
        // Truncate districts first to avoid duplicate keys on re-seed
        DB::table('districts')->delete();

        $chunkSize = 1000;
        $insertData = [];
        $now = now();
        $skipped = 0;

        foreach ($districtsJson as $d) {
            // Resolve city: try rajaongkir first, then komerce
            $internalCityId = $cityMap['ro_' . ($d['rajaongkir_city_id'] ?? '')] 
                ?? $cityMap['ko_' . ($d['komerce_city_id'] ?? '')] 
                ?? null;

            if (!$internalCityId) {
                $skipped++;
                continue; // Skip if parent city is not seeded
            }

            $insertData[] = [
                'city_id' => $internalCityId,
                'name' => $d['name'],
                'rajaongkir_district_id' => $d['rajaongkir_district_id'] ?? null,
                'komerce_district_id' => $d['komerce_district_id'] ?? null,
                'created_at' => $now,
                'updated_at' => $now
            ];

            if (count($insertData) >= $chunkSize) {
                DB::table('districts')->insert($insertData);
                $insertData = [];
            }
        }

        // Insert remaining districts
        if (count($insertData) > 0) {
            DB::table('districts')->insert($insertData);
        }

        $this->command->info('✓ Districts seeded: ' . District::count() . " (skipped: $skipped)");
        $this->command->info('');
        $this->command->info('=== Location seeding complete ===');
    }
}
