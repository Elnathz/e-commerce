<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('seed_products');

        $smartphoneCat = \App\Models\Category::query()->where('slug', 'smartphone')->first();
        $perlengkapanCat = \App\Models\Category::query()->where('slug', 'perlengkapan-rumah')->first();
        $campingCat = \App\Models\Category::query()->where('slug', 'camping-memancing')->first();

        $products = [
            [
                'category_id' => $smartphoneCat->id,
                'name' => 'iPhone 17 Pro Max',
                'slug' => 'iphone-17-pro-max',
                'description' => 'The ultimate iPhone experience with cutting-edge performance and stunning cameras.',
                'base_price' => 27000000,
                'weight_gram' => 300,
                'general_images' => [], // Belum ada foto umum untuk Pro Max
                'variants' => [
                    ['sku' => 'IP17PM-BLU', 'name' => 'Blue Titanium', 'variant_type' => 'Warna', 'price' => 25000000, 'stock' => 50, 'images' => ['ipon17promaxblue.webp']],
                    ['sku' => 'IP17PM-ORG', 'name' => 'Orange Titanium', 'variant_type' => 'Warna', 'price' => 25000000, 'stock' => 30, 'images' => ['ipon17promaxorange.webp']],
                    ['sku' => 'IP17PM-WHT', 'name' => 'White Titanium', 'variant_type' => 'Warna', 'price' => 27000000, 'stock' => 20, 'images' => ['ipon17promaxwhite.webp']],
                ]
            ],
            [
                'category_id' => $smartphoneCat->id,
                'name' => 'iPhone 17',
                'slug' => 'iphone-17',
                'description' => 'A beautifully designed smartphone with everything you need.',
                'base_price' => 17000000,
                'weight_gram' => 250,
                'general_images' => [
                    'ip17/iphone-17-all.webp',
                    'ip17/iphone-17-umum-screen.webp',
                ],
                'variants' => [
                    [
                        'sku' => 'IP17-BLK', 'name' => 'Black', 'variant_type' => 'Warna', 'price' => 15000000, 'stock' => 0,
                        'images' => [
                            'ip17/ipon17blackutama.webp',
                            'ip17/black/iphone-17-black-depan.webp',
                            'ip17/black/iphone-17-black-kamera.webp',
                            'ip17/black/iphone-17-black-samping.webp',
                        ]
                    ],
                    [
                        'sku' => 'IP17-WHT', 'name' => 'White', 'variant_type' => 'Warna', 'price' => 17000000, 'stock' => 3,
                        'images' => [
                            'ip17/ipon17whiteutama.webp',
                            'ip17/white/iphone-17-white-depan.webp',
                            'ip17/white/iphone-17-white-camera.webp',
                            'ip17/white/iphone-17-white-sampingwebp.webp',
                        ]
                    ],
                ]
            ],
            [
                'category_id' => $perlengkapanCat->id,
                'name' => 'Kantong Plastik Vakum Pakaian',
                'slug' => 'kantong-plastik-vakum',
                'description' => 'Hemat tempat di koper atau lemari dengan kantong vakum kedap udara.',
                'base_price' => 50000,
                'weight_gram' => 200,
                'general_images' => [],
                'variants' => [
                    ['sku' => 'VAC-5070', 'name' => '50x70 cm', 'variant_type' => 'Ukuran', 'price' => 35000, 'stock' => 200, 'images' => ['kantongplastikvakum.jpg']],
                    ['sku' => 'VAC-6080', 'name' => '60x80 cm', 'variant_type' => 'Ukuran', 'price' => 50000, 'stock' => 150, 'images' => ['kantongplastikvakum.jpg']],
                ]
            ],
            [
                'category_id' => $perlengkapanCat->id,
                'name' => 'Pelapis Kabel Insulasi Bakar',
                'slug' => 'pelapis-kabel-insulasi',
                'description' => 'Lindungi kabel Anda agar tidak mudah putus dan korsleting.',
                'base_price' => 20000,
                'weight_gram' => 50,
                'general_images' => [],
                'variants' => [
                    ['sku' => 'CBL-BLK', 'name' => 'Hitam', 'variant_type' => 'Warna', 'price' => 15000, 'stock' => 0, 'images' => ['pelapiskabelinsulasi.jpg']],
                    ['sku' => 'CBL-RED', 'name' => 'Merah', 'variant_type' => 'Warna', 'price' => 15000, 'stock' => 500, 'images' => ['pelapiskabelinsulasi.jpg']],
                ]
            ],
            [
                'category_id' => $campingCat->id,
                'name' => 'Rell Pancing Berkualitas',
                'slug' => 'rell-pancing',
                'description' => 'Rell pancing tarikan ringan, anti karat untuk memancing di laut maupun air tawar.',
                'base_price' => 150000,
                'weight_gram' => 450,
                'general_images' => [],
                'variants' => [
                    ['sku' => 'REEL-1000', 'name' => 'Ukuran 1000', 'variant_type' => 'Ukuran', 'price' => 150000, 'stock' => 4, 'images' => ['relpancing.jpg']],
                    ['sku' => 'REEL-2000', 'name' => 'Ukuran 2000', 'variant_type' => 'Ukuran', 'price' => 175000, 'stock' => 1, 'images' => ['relpancing.jpg']],
                ]
            ],
            [
                'category_id' => $campingCat->id,
                'name' => 'Tenda Camping Otomatis Anti Air',
                'slug' => 'tenda-camping-otomatis',
                'description' => 'Tenda mudah dirakit, cukup ditarik langsung berdiri. Bahan waterproof kuat dari hujan dan angin.',
                'base_price' => 450000,
                'weight_gram' => 2500,
                'general_images' => [],
                'variants' => [
                    ['sku' => 'TENT-2P', 'name' => 'Kapasitas 2 Orang', 'variant_type' => 'Kapasitas', 'price' => 350000, 'stock' => 15, 'images' => ['Tenda.jpg']],
                    ['sku' => 'TENT-4P', 'name' => 'Kapasitas 4 Orang', 'variant_type' => 'Kapasitas', 'price' => 450000, 'stock' => 10, 'images' => ['Tenda.jpg']],
                ]
            ]
        ];

        foreach ($products as $pData) {
            $product = \App\Models\Product::create([
                'category_id' => $pData['category_id'],
                'name' => $pData['name'],
                'slug' => $pData['slug'],
                'description' => $pData['description'],
                'base_price' => $pData['base_price'],
                'weight_gram' => $pData['weight_gram'],
                'is_active' => true,
            ]);

            $sortOrder = 0;

            // ── Foto Umum (product_variant_id = null) ──
            foreach ($pData['general_images'] as $idx => $imgPath) {
                $source = public_path('images/product/' . $imgPath);
                $target = 'seed_products/' . uniqid() . '_' . basename($imgPath);

                if (file_exists($source)) {
                    \Illuminate\Support\Facades\File::copy($source, storage_path('app/public/' . $target));

                    $product->images()->create([
                        'product_variant_id' => null,
                        'image_path' => $target,
                        'is_primary' => $idx === 0, // Foto umum pertama = primary
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }

            // ── Varian + Foto Varian ──
            $isFirstVariant = empty($pData['general_images']); // Jika tidak ada foto umum, varian pertama jadi primary

            foreach ($pData['variants'] as $vData) {
                $variant = $product->variants()->create([
                    'sku' => $vData['sku'],
                    'name' => $vData['name'],
                    'variant_type' => $vData['variant_type'] ?? null,
                    'price' => $vData['price'],
                    'stock' => $vData['stock'],
                    'weight_gram' => $pData['weight_gram'],
                    'is_active' => true,
                ]);

                // Attach semua foto varian
                foreach ($vData['images'] as $imgIdx => $imgPath) {
                    $source = public_path('images/product/' . $imgPath);
                    $target = 'seed_products/' . uniqid() . '_' . basename($imgPath);

                    if (file_exists($source)) {
                        \Illuminate\Support\Facades\File::copy($source, storage_path('app/public/' . $target));

                        $product->images()->create([
                            'product_variant_id' => $variant->id,
                            'image_path' => $target,
                            'is_primary' => $isFirstVariant && $imgIdx === 0,
                            'sort_order' => $sortOrder++,
                        ]);
                    }
                }

                $isFirstVariant = false;
            }
        }
    }
}
