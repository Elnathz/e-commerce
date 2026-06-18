<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_sends_active_ordered_slides(): void
    {
        HeroSlide::create(['image_path' => 'images/banner/banner.png', 'title' => 'B', 'sort_order' => 2, 'is_active' => true]);
        HeroSlide::create(['image_path' => 'images/banner/banner.png', 'title' => 'A', 'sort_order' => 1, 'is_active' => true]);
        HeroSlide::create(['image_path' => 'images/banner/banner.png', 'title' => 'Hidden', 'sort_order' => 0, 'is_active' => false]);

        $this->get('/')->assertOk()->assertInertia(fn ($p) =>
            $p->component('Storefront/Index')
              ->has('heroSlides', 2)
              ->where('heroSlides.0.title', 'A')   // terurut sort_order, hanya aktif
        );
    }

    public function test_on_sale_products_only_includes_discounted(): void
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c', 'is_active' => true]);
        $sale = Product::create(['category_id' => $cat->id, 'name' => 'Sale', 'slug' => 'sale', 'base_price' => 100000, 'weight_gram' => 100, 'is_active' => true]);
        ProductVariant::create(['product_id' => $sale->id, 'sku' => 'S1', 'name' => 'v', 'price' => 80000, 'stock' => 5, 'is_active' => true]);

        $full = Product::create(['category_id' => $cat->id, 'name' => 'Full', 'slug' => 'full', 'base_price' => 50000, 'weight_gram' => 100, 'is_active' => true]);
        ProductVariant::create(['product_id' => $full->id, 'sku' => 'F1', 'name' => 'v', 'price' => 50000, 'stock' => 5, 'is_active' => true]);

        $this->get('/')->assertOk()->assertInertia(fn ($p) =>
            $p->has('onSaleProducts', 1)
              ->where('onSaleProducts.0.name', 'Sale')
        );
    }
}
