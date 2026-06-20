<?php
namespace Tests\Feature;
use App\Models\{Category, FlashSale, FlashSaleItem, Order, OrderItem, Product, ProductVariant, User};
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

    private function flashItem(ProductVariant $v, ?int $quota, int $soldCount = 0): FlashSaleItem {
        $sale = FlashSale::create(['name' => 'FS', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'is_active' => true]);
        return FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $v->id,
            'sale_price' => 60000,
            'quota' => $quota,
            'sold_count' => $soldCount,
        ]);
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

    public function test_reserve_increments_sold_and_blocks_when_exhausted(): void {
        $v = $this->variant(100000);
        $item = $this->flashItem($v, quota: 1, soldCount: 0);
        $svc = app(FlashSaleService::class);

        $svc->reserve($item->id, 1);
        $this->assertSame(1, $item->fresh()->sold_count);

        $this->expectException(\Exception::class);
        $svc->reserve($item->id, 1); // habis
    }

    public function test_reserve_with_null_quota_still_increments_sold_count(): void {
        $v = $this->variant(100000);
        $item = $this->flashItem($v, quota: null, soldCount: 0);
        $svc = app(FlashSaleService::class);

        $svc->reserve($item->id, 3);

        $this->assertSame(3, $item->fresh()->sold_count);
    }

    public function test_release_decrements_sold_count_by_order_item_quantity(): void {
        $v = $this->variant(100000);
        $item = $this->flashItem($v, quota: 10, soldCount: 5);

        $user = User::create([
            'name' => 'Buyer',
            'email' => 'buyer-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FLASH-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'subtotal' => 120000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 130000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $v->id,
            'flash_sale_item_id' => $item->id,
            'product_name_snapshot' => 'P',
            'variant_name_snapshot' => 'V',
            'quantity' => 2,
            'unit_price' => 60000,
            'weight_gram' => 100,
            'subtotal' => 120000,
        ]);

        app(FlashSaleService::class)->release($order);

        $this->assertSame(3, $item->fresh()->sold_count); // 5 - 2
    }
}
