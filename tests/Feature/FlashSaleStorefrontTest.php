<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashSaleStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(float $price = 100000): Product
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid()]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Produk ' . uniqid(), 'slug' => 'p-' . uniqid(),
            'description' => 'd', 'base_price' => $price, 'weight_gram' => 100, 'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id, 'sku' => 's-' . uniqid(), 'name' => 'V',
            'price' => $price, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true,
        ]);

        return $product->load('variants');
    }

    private function flash(Product $product, float $salePrice, int $startOffsetMin, int $endOffsetMin, ?int $quota = null, int $sold = 0): FlashSaleItem
    {
        $sale = FlashSale::create([
            'name' => 'FS ' . uniqid(),
            'starts_at' => now()->addMinutes($startOffsetMin),
            'ends_at' => now()->addMinutes($endOffsetMin),
            'is_active' => true,
        ]);

        return FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $product->variants->first()->id,
            'sale_price' => $salePrice,
            'quota' => $quota,
            'sold_count' => $sold,
        ]);
    }

    public function test_homepage_exposes_active_flash_products_with_quota(): void
    {
        $product = $this->product(100000);
        $this->flash($product, 60000, -10, 60, quota: 50, sold: 12);

        $this->get('/')->assertInertia(fn ($p) => $p
            ->where('flashSale.products.0.id', $product->id)
            ->where('flashSale.products.0.price_display.source', 'flash')
            ->where('flashSale.products.0.price_display.effective', 60000)
            ->where('flashSale.products.0.flash_quota.sold', 12)
            ->where('flashSale.products.0.flash_quota.quota', 50)
            ->whereNot('flashSale.ends_at', null)
        );
    }

    public function test_sold_out_flash_is_excluded(): void
    {
        $product = $this->product(100000);
        $this->flash($product, 60000, -10, 60, quota: 5, sold: 5); // sold out

        $this->get('/')->assertInertia(fn ($p) => $p->where('flashSale.products', []));
        $this->get(route('flash-sale.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->where('products', []));
    }

    public function test_out_of_window_flash_is_excluded(): void
    {
        $future = $this->product(100000);
        $this->flash($future, 60000, 30, 90); // not started yet
        $past = $this->product(80000);
        $this->flash($past, 50000, -90, -30); // already ended

        $this->get('/')->assertInertia(fn ($p) => $p->where('flashSale.products', []));
    }

    public function test_product_with_cheaper_nonflash_variant_excluded(): void
    {
        // Product whose flash is on the expensive variant (200000 -> sale 150000),
        // but it also has a cheaper non-flash variant (50000). price_display resolves
        // to the 50000 variant (source 'none'), so the card would look non-flash in
        // the rail -> it must be excluded.
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid()]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Multi ' . uniqid(), 'slug' => 'm-' . uniqid(),
            'description' => 'd', 'base_price' => 200000, 'weight_gram' => 100, 'is_active' => true,
        ]);
        $flashVariant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'big-' . uniqid(), 'name' => 'Besar',
            'price' => 200000, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'small-' . uniqid(), 'name' => 'Kecil',
            'price' => 50000, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true,
        ]);
        $sale = FlashSale::create(['name' => 'FS', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'is_active' => true]);
        FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_variant_id' => $flashVariant->id, 'sale_price' => 150000, 'quota' => null, 'sold_count' => 0]);

        $this->get('/')->assertInertia(fn ($p) => $p
            ->where('flashSale.products', [])
            ->where('flashSale.ends_at', null)
        );
    }

    public function test_flash_sale_page_lists_active_products(): void
    {
        $product = $this->product(100000);
        $this->flash($product, 60000, -10, 60, quota: null, sold: 7); // unlimited quota

        $this->get(route('flash-sale.index'))->assertOk()->assertInertia(fn ($p) => $p
            ->component('Storefront/FlashSale')
            ->where('products.0.id', $product->id)
            ->where('products.0.price_display.source', 'flash')
            ->where('products.0.flash_quota.sold', 7)
            ->where('products.0.flash_quota.quota', null)
        );
    }
}
