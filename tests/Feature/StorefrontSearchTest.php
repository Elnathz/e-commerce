<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(Category $category, string $name): Product
    {
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'base_price' => 1000000,
            'weight_gram' => 100,
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id, 'sku' => $name . '-1', 'name' => 'v',
            'price' => 1000000, 'stock' => 5, 'is_active' => true,
        ]);

        return $product;
    }

    public function test_parent_category_filter_includes_child_products(): void
    {
        $parent = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'is_active' => true]);
        $child = Category::create(['name' => 'Smartphone', 'slug' => 'smartphone', 'parent_id' => $parent->id, 'is_active' => true]);
        $other = Category::create(['name' => 'Furniture', 'slug' => 'furniture', 'is_active' => true]);

        $this->makeProduct($child, 'HP Keren');     // produk di child dari parent yang dipilih
        $this->makeProduct($other, 'Sofa');         // di kategori lain — tidak boleh muncul

        // Memilih PARENT (Elektronik) harus menyertakan produk dari child (Smartphone).
        $this->get('/search?categories[]=' . $parent->id)
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Storefront/Search')
                ->where('totalResults', 1)
                ->where('products.data.0.name', 'HP Keren'));
    }

    public function test_child_category_filter_still_works(): void
    {
        $parent = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'is_active' => true]);
        $child = Category::create(['name' => 'Smartphone', 'slug' => 'smartphone', 'parent_id' => $parent->id, 'is_active' => true]);

        $this->makeProduct($child, 'HP Keren');

        $this->get('/search?categories[]=' . $child->id)
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('totalResults', 1));
    }

    public function test_listing_exposes_price_display_without_fake_discount(): void
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-list']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'Multi', 'slug' => 'multi', 'description' => 'd', 'base_price' => 100000, 'weight_gram' => 100, 'is_active' => true]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'L', 'name' => 'Large', 'price' => 100000, 'stock' => 5, 'reserved_stock' => 0, 'is_active' => true]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'S', 'name' => 'Small', 'price' => 80000, 'stock' => 5, 'reserved_stock' => 0, 'is_active' => true]);

        $this->get('/search?q=Multi')->assertInertia(fn ($p) => $p
            ->where('products.data.0.price_display.source', 'none')
            ->where('products.data.0.price_display.effective', 80000));
    }
}
