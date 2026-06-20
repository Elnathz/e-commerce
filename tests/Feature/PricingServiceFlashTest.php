<?php
namespace Tests\Feature;
use App\Models\{Category, Product, ProductVariant, FlashSale, FlashSaleItem};
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricingServiceFlashTest extends TestCase {
    use RefreshDatabase;
    private function variant(float $price = 100000): ProductVariant {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-'.uniqid()]);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'P', 'slug' => 'p-'.uniqid(), 'description' => 'd', 'base_price' => $price, 'weight_gram' => 100, 'is_active' => true]);
        return ProductVariant::create(['product_id' => $p->id, 'sku' => 's-'.uniqid(), 'name' => 'V', 'price' => $price, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true]);
    }
    private function flash(ProductVariant $v, float $salePrice, $startOffset, $endOffset, ?int $quota = null, int $sold = 0): FlashSaleItem {
        $sale = FlashSale::create(['name' => 'FS', 'starts_at' => now()->addMinutes($startOffset), 'ends_at' => now()->addMinutes($endOffset), 'is_active' => true]);
        return FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_variant_id' => $v->id, 'sale_price' => $salePrice, 'quota' => $quota, 'sold_count' => $sold]);
    }

    public function test_active_flash_wins(): void {
        $v = $this->variant(100000); $this->flash($v, 60000, -10, 10);
        $info = app(PricingService::class)->priceInfo($v->fresh());
        $this->assertSame('flash', $info['source']);
        $this->assertEqualsWithDelta(60000, $info['effective'], 0.01);
        $this->assertNotNull($info['flash_ends_at']);
    }
    public function test_flash_before_window_not_applied(): void {
        $v = $this->variant(100000); $this->flash($v, 60000, 10, 20);
        $this->assertSame('none', app(PricingService::class)->priceInfo($v->fresh())['source']);
    }
    public function test_flash_after_window_not_applied(): void {
        $v = $this->variant(100000); $this->flash($v, 60000, -20, -10);
        $this->assertSame('none', app(PricingService::class)->priceInfo($v->fresh())['source']);
    }
    public function test_flash_sold_out_falls_through_with_status(): void {
        $v = $this->variant(100000); $this->flash($v, 60000, -10, 10, quota: 5, sold: 5);
        $info = app(PricingService::class)->priceInfo($v->fresh());
        $this->assertSame('none', $info['source']);
        $this->assertSame('sold_out', $info['flash_status']);
        $this->assertEqualsWithDelta(100000, $info['effective'], 0.01);
    }

    // --- N+1 fast path: when `flashSaleItems.flashSale` is eager-loaded the
    //     active flash item resolves in-memory (no per-variant query). ---

    public function test_eager_loaded_flash_resolves_without_query(): void {
        $v = $this->variant(100000); $this->flash($v, 60000, -10, 10);
        $loaded = ProductVariant::with('flashSaleItems.flashSale')->find($v->id);

        DB::connection()->enableQueryLog();
        $item = app(PricingService::class)->activeFlashItem($loaded, now());
        $queries = DB::connection()->getQueryLog();
        DB::connection()->disableQueryLog();

        $this->assertNotNull($item, 'Active flash item must resolve from the eager-loaded relation.');
        $this->assertCount(0, $queries, 'No per-variant flash query when flashSaleItems is eager-loaded.');
    }

    public function test_eager_loaded_path_resolves_same_flash_price(): void {
        $v = $this->variant(100000); $this->flash($v, 60000, -10, 10);
        $loaded = ProductVariant::with('flashSaleItems.flashSale')->find($v->id);
        $info = app(PricingService::class)->priceInfo($loaded);
        $this->assertSame('flash', $info['source']);
        $this->assertEqualsWithDelta(60000, $info['effective'], 0.01);
    }

    public function test_eager_loaded_out_of_window_flash_excluded(): void {
        // In-memory activeAt replication must exclude a not-yet-started window.
        $v = $this->variant(100000); $this->flash($v, 60000, 10, 20);
        $loaded = ProductVariant::with('flashSaleItems.flashSale')->find($v->id);
        $this->assertNull(app(PricingService::class)->activeFlashItem($loaded, now()));
    }
}
