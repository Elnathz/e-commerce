<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin Products page revamp (.planning/admin_pages_ux_plan.md, Fase B).
 * Table + filter (q/category/status/filter=low_stock), per-variant stock
 * indicators (available = stock - reserved_stock), and stat cards reusing
 * AnalyticsDashboardService::countLowStockProducts() for `low_stock`.
 */
class AdminProductIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function createProduct(Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Produk ' . Str::random(6),
            'slug' => Str::slug('produk-' . Str::random(8)),
            'base_price' => 10000,
            'weight_gram' => 100,
            'is_active' => true,
        ], $overrides));
    }

    private function createVariant(Product $product, array $overrides = []): ProductVariant
    {
        return ProductVariant::create(array_merge([
            'product_id' => $product->id,
            'sku' => 'SKU-' . Str::random(8),
            'name' => 'Default',
            'price' => 10000,
            'stock' => 100,
            'reserved_stock' => 0,
            'is_active' => true,
        ], $overrides));
    }

    public function test_low_stock_filter_excludes_healthy_product()
    {
        $category = Category::create(['name' => 'Kategori A', 'slug' => 'kategori-a']);
        $product = $this->createProduct($category, ['name' => 'Produk Sehat']);
        $this->createVariant($product, ['stock' => 100, 'reserved_stock' => 0]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['filter' => 'low_stock']));

        $response->assertOk();
        $names = array_column($response->viewData('page')['props']['products']['data'], 'name');

        $this->assertNotContains('Produk Sehat', $names);
    }

    public function test_low_stock_filter_includes_product_with_one_critical_variant()
    {
        $category = Category::create(['name' => 'Kategori B', 'slug' => 'kategori-b']);
        $product = $this->createProduct($category, ['name' => 'Produk Campuran']);
        $this->createVariant($product, ['stock' => 100, 'reserved_stock' => 0]); // available 100
        $this->createVariant($product, ['stock' => 2, 'reserved_stock' => 0]); // available 2 <= threshold(5)

        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['filter' => 'low_stock']));

        $response->assertOk();
        $names = array_column($response->viewData('page')['props']['products']['data'], 'name');

        $this->assertContains('Produk Campuran', $names);
    }

    public function test_low_stock_filter_includes_fully_out_of_stock_product()
    {
        $category = Category::create(['name' => 'Kategori C', 'slug' => 'kategori-c']);
        $product = $this->createProduct($category, ['name' => 'Produk Habis']);
        $this->createVariant($product, ['stock' => 0, 'reserved_stock' => 0]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['filter' => 'low_stock']));

        $response->assertOk();
        $names = array_column($response->viewData('page')['props']['products']['data'], 'name');

        $this->assertContains('Produk Habis', $names);
    }

    public function test_out_of_stock_stat_excludes_product_with_some_available()
    {
        $category = Category::create(['name' => 'Kategori D', 'slug' => 'kategori-d']);

        // Critical but not "habis": one variant 0, another 10 available.
        $partial = $this->createProduct($category, ['name' => 'Produk Sebagian Kritis']);
        $this->createVariant($partial, ['stock' => 0, 'reserved_stock' => 0]);
        $this->createVariant($partial, ['stock' => 10, 'reserved_stock' => 0]);

        // Fully out of stock.
        $empty = $this->createProduct($category, ['name' => 'Produk Total Habis']);
        $this->createVariant($empty, ['stock' => 0, 'reserved_stock' => 0]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.index'));

        $response->assertOk();
        $stats = $response->viewData('page')['props']['stats'];

        $this->assertSame(1, $stats['out_of_stock']);
    }

    public function test_search_by_name_and_sku_returns_single_product()
    {
        $category = Category::create(['name' => 'Kategori E', 'slug' => 'kategori-e']);

        $target = $this->createProduct($category, ['name' => 'Kaos Polos Unik']);
        $this->createVariant($target, ['sku' => 'KAOS-MERAH-M']);
        $this->createVariant($target, ['sku' => 'KAOS-MERAH-L']);

        $other = $this->createProduct($category, ['name' => 'Celana Jeans']);
        $this->createVariant($other, ['sku' => 'CELANA-HITAM-M']);

        // Search by name.
        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['q' => 'Kaos Polos']));
        $response->assertOk();
        $data = $response->viewData('page')['props']['products']['data'];
        $this->assertCount(1, $data);
        $this->assertSame('Kaos Polos Unik', $data[0]['name']);

        // Search by SKU matching multiple variants of the same product — must not duplicate.
        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['q' => 'KAOS-MERAH']));
        $response->assertOk();
        $data = $response->viewData('page')['props']['products']['data'];
        $this->assertCount(1, $data);
        $this->assertSame('Kaos Polos Unik', $data[0]['name']);
    }

    public function test_filter_by_category_and_status()
    {
        $categoryA = Category::create(['name' => 'Kategori F', 'slug' => 'kategori-f']);
        $categoryB = Category::create(['name' => 'Kategori G', 'slug' => 'kategori-g']);

        $this->createProduct($categoryA, ['name' => 'Produk Aktif F', 'is_active' => true]);
        $this->createProduct($categoryA, ['name' => 'Produk Nonaktif F', 'is_active' => false]);
        $this->createProduct($categoryB, ['name' => 'Produk Aktif G', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.index', [
            'category' => $categoryA->id,
            'status' => 'active',
        ]));

        $response->assertOk();
        $names = array_column($response->viewData('page')['props']['products']['data'], 'name');

        $this->assertSame(['Produk Aktif F'], $names);
    }

    public function test_stats_shape()
    {
        $category = Category::create(['name' => 'Kategori H', 'slug' => 'kategori-h']);

        $active = $this->createProduct($category, ['name' => 'Aktif', 'is_active' => true]);
        $this->createVariant($active, ['stock' => 100, 'reserved_stock' => 0]);

        $inactive = $this->createProduct($category, ['name' => 'Nonaktif', 'is_active' => false]);
        $this->createVariant($inactive, ['stock' => 1, 'reserved_stock' => 0]); // critical (<=5)

        $response = $this->actingAs($this->admin)->get(route('admin.products.index'));

        $response->assertOk();
        $stats = $response->viewData('page')['props']['stats'];

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['active']);
        $this->assertArrayHasKey('low_stock', $stats);
        $this->assertArrayHasKey('out_of_stock', $stats);
    }

    public function test_non_admin_cannot_access()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('admin.products.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_set_manual_discount(): void
    {
        $category = Category::create(['name' => 'Kategori Diskon', 'slug' => 'kategori-diskon']);
        $product = $this->createProduct($category, ['name' => 'Produk Diskon', 'base_price' => 100000]);
        $variant = $this->createVariant($product, ['price' => 100000]);

        // Product discount_percent persists via admin.products.update.
        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product->id), [
            'category_id' => $category->id,
            'name' => $product->name,
            'description' => null,
            'base_price' => 100000,
            'discount_percent' => 20,
            'weight_gram' => 100,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertEqualsWithDelta(20, $product->fresh()->discount_percent, 0.01);

        // Variant discount_price persists via admin.products.variants.update, must stay < price.
        $response = $this->actingAs($this->admin)->put(route('admin.products.variants.update', $variant->id), [
            'sku' => $variant->sku,
            'name' => $variant->name,
            'price' => 100000,
            'discount_price' => 75000,
            'stock' => 100,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertEqualsWithDelta(75000, $variant->fresh()->discount_price, 0.01);

        // Invalid discount_price >= price must be rejected (lt: invariant).
        $invalidResponse = $this->actingAs($this->admin)->put(route('admin.products.variants.update', $variant->id), [
            'sku' => $variant->sku,
            'name' => $variant->name,
            'price' => 100000,
            'discount_price' => 100000,
            'stock' => 100,
            'is_active' => true,
        ]);

        $invalidResponse->assertSessionHasErrors('discount_price');
        $this->assertEqualsWithDelta(75000, $variant->fresh()->discount_price, 0.01);
    }
}
