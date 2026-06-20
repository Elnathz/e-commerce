<?php
namespace Tests\Feature;
use App\Models\{Category, Product, ProductVariant};
use App\Services\FlashSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashSaleServiceTest extends TestCase {
    use RefreshDatabase;
    private function variant(float $price = 100000): ProductVariant {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-'.uniqid()]);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'P', 'slug' => 'p-'.uniqid(), 'description' => 'd', 'base_price' => $price, 'weight_gram' => 100, 'is_active' => true]);
        return ProductVariant::create(['product_id' => $p->id, 'sku' => 's-'.uniqid(), 'name' => 'V', 'price' => $price, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true]);
    }

    public function test_sale_price_must_be_below_price_and_manual(): void {
        $v = $this->variant(100000); // manual 70000
        $v->update(['discount_price' => 70000]);
        $svc = app(FlashSaleService::class);
        $this->expectException(\Exception::class);
        $svc->assertSalePriceValid($v->fresh(), 80000); // 80000 >= manual 70000 -> reject
    }

    public function test_sale_price_must_be_below_normal_price(): void {
        $v = $this->variant(100000); // no manual discount
        $svc = app(FlashSaleService::class);
        $this->expectException(\Exception::class);
        $svc->assertSalePriceValid($v->fresh(), 100000); // 100000 >= price -> reject
    }

    public function test_sale_price_below_both_price_and_manual_passes(): void {
        $v = $this->variant(100000); // manual 70000
        $v->update(['discount_price' => 70000]);
        $svc = app(FlashSaleService::class);
        $svc->assertSalePriceValid($v->fresh(), 60000); // below manual 70000 and price 100000 -> ok
        $this->assertTrue(true); // no exception thrown
    }
}
