<?php
namespace Tests\Feature;
use App\Models\{Category, Product, ProductVariant};
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase {
    use RefreshDatabase;
    private function variant(array $p = [], array $v = []): ProductVariant {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-'.uniqid()]);
        $product = Product::create(array_merge([
            'category_id' => $cat->id, 'name' => 'P', 'slug' => 'p-'.uniqid(),
            'description' => 'd', 'base_price' => 100000, 'weight_gram' => 100, 'is_active' => true,
        ], $p));
        return ProductVariant::create(array_merge([
            'product_id' => $product->id, 'sku' => 's-'.uniqid(), 'name' => 'V',
            'price' => 100000, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true,
        ], $v));
    }

    public function test_no_discount_returns_original(): void {
        $info = app(PricingService::class)->priceInfo($this->variant());
        $this->assertSame('none', $info['source']);
        $this->assertEqualsWithDelta(100000, $info['effective'], 0.01);
        $this->assertSame(0, $info['discount_percent']);
    }

    public function test_product_percent_discount(): void {
        $info = app(PricingService::class)->priceInfo($this->variant(['discount_percent' => 25]));
        $this->assertSame('manual', $info['source']);
        $this->assertEqualsWithDelta(75000, $info['effective'], 0.01);
        $this->assertSame(25, $info['discount_percent']);
    }

    public function test_variant_discount_price_overrides_product_percent(): void {
        $info = app(PricingService::class)->priceInfo(
            $this->variant(['discount_percent' => 25], ['discount_price' => 60000])
        );
        $this->assertSame('manual', $info['source']);
        $this->assertEqualsWithDelta(60000, $info['effective'], 0.01);
    }

    public function test_discount_not_below_price_is_ignored(): void {
        $info = app(PricingService::class)->priceInfo($this->variant([], ['discount_price' => 120000]));
        $this->assertSame('none', $info['source']);
        $this->assertEqualsWithDelta(100000, $info['effective'], 0.01);
    }

    public function test_cart_snapshot_uses_effective_price(): void {
        $variant = $this->variant(['discount_percent' => 20]); // 100000 → 80000
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        $request = request();
        $item = app(\App\Services\CartService::class)->addItem($request, $variant->id, 1);
        $this->assertEqualsWithDelta(80000, $item->unit_price_snapshot, 0.01);
    }

    public function test_cart_prop_uses_effective_price(): void {
        $variant = $this->variant(['discount_percent' => 20]); // 100000 -> 80000
        $user = \App\Models\User::factory()->create();
        $cart = \App\Models\Cart::create(['user_id' => $user->id]);
        \App\Models\CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price_snapshot' => 80000]);
        $this->actingAs($user)->get('/cart')->assertInertia(fn ($p) =>
            $p->where('cartItems.0.current_price', 80000)->where('cartItems.0.subtotal', 160000)
        );
    }

    public function test_product_price_display_picks_cheapest_effective_variant(): void {
        $cat = \App\Models\Category::create(['name' => 'C', 'slug' => 'c-'.uniqid()]);
        $product = \App\Models\Product::create([
            'category_id' => $cat->id, 'name' => 'P', 'slug' => 'p-'.uniqid(),
            'description' => 'd', 'base_price' => 100000, 'weight_gram' => 100, 'is_active' => true,
        ]);
        \App\Models\ProductVariant::create(['product_id' => $product->id, 'sku' => 'a'.uniqid(), 'name' => 'Large', 'price' => 100000, 'stock' => 5, 'reserved_stock' => 0, 'is_active' => true]);
        \App\Models\ProductVariant::create(['product_id' => $product->id, 'sku' => 'b'.uniqid(), 'name' => 'Small', 'price' => 80000, 'stock' => 5, 'reserved_stock' => 0, 'is_active' => true]);
        $display = $product->fresh()->price_display;
        $this->assertEqualsWithDelta(80000, $display['effective'], 0.01);
        $this->assertSame('none', $display['source']); // varian murah BUKAN diskon
    }
}
