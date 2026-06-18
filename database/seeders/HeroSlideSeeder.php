<?php
namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        $main = [
            ['title' => 'Belanja Hemat Setiap Hari', 'cta_url' => '/search', 'sort_order' => 1],
            ['title' => 'Promo Spesial Pekan Ini', 'cta_url' => '/search?sort=newest', 'sort_order' => 2],
            ['title' => 'Produk Terbaru Telah Tiba', 'cta_url' => '/search?sort=newest', 'sort_order' => 3],
        ];
        foreach ($main as $m) {
            HeroSlide::updateOrCreate(
                ['title' => $m['title']],
                ['placement' => 'hero_main', 'image_path' => 'images/banner/banner.png',
                 'cta_url' => $m['cta_url'], 'sort_order' => $m['sort_order'], 'is_active' => true]
            );
        }
        for ($i = 1; $i <= 4; $i++) {
            HeroSlide::updateOrCreate(
                ['title' => "Banner Samping $i"],
                ['placement' => 'hero_side', 'image_path' => 'images/banner/banner.png',
                 'cta_url' => '/search', 'sort_order' => $i, 'is_active' => true]
            );
        }
    }
}
