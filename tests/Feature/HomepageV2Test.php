<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageV2Test extends TestCase
{
    use RefreshDatabase;

    private function product(Category $cat, string $name, bool $withImage = true): Product
    {
        $p = Product::create(['category_id' => $cat->id, 'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name), 'base_price' => 1000,
            'weight_gram' => 100, 'is_active' => true]);
        ProductVariant::create(['product_id' => $p->id, 'sku' => $name.'-1', 'name' => 'v',
            'price' => 1000, 'stock' => 5, 'is_active' => true]);
        if ($withImage) {
            ProductImage::create(['product_id' => $p->id, 'image_path' => 'products/'.$p->id.'.jpg',
                'is_primary' => true, 'sort_order' => 0]);
        }
        return $p;
    }

    public function test_banners_split_by_placement_and_ordered(): void
    {
        HeroSlide::create(['placement' => 'hero_main', 'image_path' => 'images/banner/banner.png', 'title' => 'M2', 'sort_order' => 2, 'is_active' => true]);
        HeroSlide::create(['placement' => 'hero_main', 'image_path' => 'images/banner/banner.png', 'title' => 'M1', 'sort_order' => 1, 'is_active' => true]);
        foreach (range(1, 5) as $i) {
            HeroSlide::create(['placement' => 'hero_side', 'image_path' => 'images/banner/banner.png', 'title' => "S$i", 'sort_order' => $i, 'is_active' => true]);
        }

        $this->get('/')->assertOk()->assertInertia(fn ($p) => $p
            ->component('Storefront/Index')
            ->has('heroMainBanners', 2)
            ->where('heroMainBanners.0.title', 'M1')
            ->has('heroSideBanners', 4));   // max 4
    }

    public function test_popular_categories_and_banyak_dicari(): void
    {
        $parent = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'is_active' => true]);
        $child = Category::create(['name' => 'Smartphone', 'slug' => 'smartphone', 'parent_id' => $parent->id, 'is_active' => true]);
        $this->product($child, 'HP A');
        $this->product($child, 'HP B', false);   // produk tanpa gambar -> count tetap 2

        $this->get('/')->assertOk()->assertInertia(fn ($p) => $p
            // parent dapat hitungan dari produk di child + ada gambar perwakilan
            ->where('popularCategories.0.name', 'Elektronik')
            ->where('popularCategories.0.product_count', 2)
            ->whereNot('popularCategories.0.image', null)
            // banyakDicari hanya subkategori
            ->where('banyakDicari.0.name', 'Smartphone')
            ->where('banyakDicari.0.product_count', 2));
    }

    public function test_hero_banner_serves_resolved_category_link(): void
    {
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'is_active' => true]);
        HeroSlide::create([
            'placement' => 'hero_main', 'image_path' => 'images/banner/banner.png', 'title' => 'M1',
            'sort_order' => 1, 'is_active' => true, 'link_type' => 'category', 'link_id' => $category->id,
        ]);

        $this->get('/')->assertOk()->assertInertia(fn ($p) => $p
            ->component('Storefront/Index')
            ->where('heroMainBanners.0.cta_url', route('search', ['categories' => [$category->id]])));
    }
}
