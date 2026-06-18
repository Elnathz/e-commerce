<?php
namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        $slides = [
            [
                'image_path' => 'images/banner/banner.png', // aset publik existing (tanpa /storage/)
                'title' => 'Belanja Hemat Setiap Hari',
                'subtitle' => 'Ribuan produk pilihan dengan harga terbaik',
                'badge_label' => 'Mulai dari 17RB-an',
                'cta_label' => 'Mulai Belanja',
                'cta_url' => '/search',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'image_path' => 'images/banner/banner.png',
                'title' => 'Promo Spesial Pekan Ini',
                'subtitle' => 'Diskon untuk kategori pilihan',
                'badge_label' => 'Hemat s/d 50%',
                'cta_label' => 'Lihat Promo',
                'cta_url' => '/search?sort=newest',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'image_path' => 'images/banner/banner.png',
                'title' => 'Produk Terbaru Telah Tiba',
                'subtitle' => 'Jadi yang pertama mencobanya',
                'badge_label' => 'New Arrival',
                'cta_label' => 'Jelajahi',
                'cta_url' => '/search?sort=newest',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($slides as $s) {
            HeroSlide::updateOrCreate(['title' => $s['title']], $s);
        }
    }
}
