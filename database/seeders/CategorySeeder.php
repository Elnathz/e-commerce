<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('seed_categories');

        // === PARENT CATEGORIES ===
        $elektronik = \App\Models\Category::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
            'image_path' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $rumah = \App\Models\Category::create([
            'name' => 'Rumah & Kehidupan',
            'slug' => 'rumah-kehidupan',
            'image_path' => null,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $outdoor = \App\Models\Category::create([
            'name' => 'Olahraga & Outdoor',
            'slug' => 'olahraga-outdoor',
            'image_path' => null,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // === SUBCATEGORIES ===
        // Helper: copy image if exists
        $copyImage = function (string $source, string $target) {
            $srcPath = public_path('images/category/' . $source);
            if (file_exists($srcPath)) {
                \Illuminate\Support\Facades\File::copy($srcPath, storage_path('app/public/' . $target));
            }
            return $target;
        };

        // Elektronik → Smartphone
        $smartphoneImg = $copyImage('ipon.png', 'seed_categories/ipon.png');
        \App\Models\Category::create([
            'parent_id' => $elektronik->id,
            'name' => 'Smartphone',
            'slug' => 'smartphone',
            'image_path' => $smartphoneImg,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Rumah & Kehidupan → Furniture
        $furnitureImg = $copyImage('sofa.png', 'seed_categories/sofa.png');
        \App\Models\Category::create([
            'parent_id' => $rumah->id,
            'name' => 'Furniture',
            'slug' => 'furniture',
            'image_path' => $furnitureImg,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Rumah & Kehidupan → Perlengkapan Rumah
        \App\Models\Category::create([
            'parent_id' => $rumah->id,
            'name' => 'Perlengkapan Rumah',
            'slug' => 'perlengkapan-rumah',
            'image_path' => null,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Olahraga & Outdoor → Camping & Memancing
        \App\Models\Category::create([
            'parent_id' => $outdoor->id,
            'name' => 'Camping & Memancing',
            'slug' => 'camping-memancing',
            'image_path' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
