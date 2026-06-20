<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashSaleAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function variant(float $price = 100000): ProductVariant
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-'.uniqid()]);
        $p = Product::create([
            'category_id' => $cat->id, 'name' => 'P', 'slug' => 'p-'.uniqid(),
            'description' => 'd', 'base_price' => $price, 'weight_gram' => 100, 'is_active' => true,
        ]);
        return ProductVariant::create([
            'product_id' => $p->id, 'sku' => 's-'.uniqid(), 'name' => 'V',
            'price' => $price, 'stock' => 10, 'reserved_stock' => 0, 'is_active' => true,
        ]);
    }

    public function test_admin_can_create_flash_sale_with_items(): void
    {
        $variant = $this->variant(100000);

        $this->actingAs($this->admin())
            ->post(route('admin.flash-sales.store'), [
                'name' => 'Flash Sale Akhir Pekan',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'is_active' => true,
                'items' => [
                    ['product_variant_id' => $variant->id, 'sale_price' => 60000, 'quota' => 10],
                ],
            ])
            ->assertRedirect(route('admin.flash-sales.index'));

        $this->assertDatabaseHas('flash_sales', ['name' => 'Flash Sale Akhir Pekan', 'is_active' => true]);
        $sale = FlashSale::where('name', 'Flash Sale Akhir Pekan')->first();
        $this->assertDatabaseHas('flash_sale_items', [
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'sale_price' => 60000,
            'quota' => 10,
        ]);
    }

    public function test_inversion_guard_rejected(): void
    {
        $variant = $this->variant(100000);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.flash-sales.store'), [
                'name' => 'Flash Sale Invalid',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'is_active' => true,
                'items' => [
                    // sale_price >= price (100000) -> must be rejected
                    ['product_variant_id' => $variant->id, 'sale_price' => 100000, 'quota' => 10],
                ],
            ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('flash_sales', ['name' => 'Flash Sale Invalid']);
        $this->assertDatabaseMissing('flash_sale_items', ['product_variant_id' => $variant->id]);
    }

    public function test_overlap_same_variant_active_rejected(): void
    {
        $variant = $this->variant(100000);

        $existingSale = FlashSale::create([
            'name' => 'Existing Sale',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
            'is_active' => true,
        ]);
        FlashSaleItem::create([
            'flash_sale_id' => $existingSale->id,
            'product_variant_id' => $variant->id,
            'sale_price' => 70000,
            'quota' => 5,
            'sold_count' => 0,
        ]);

        // New sale window overlaps existingSale's window (addDay..addDays(3)) for the same variant.
        $response = $this->actingAs($this->admin())
            ->post(route('admin.flash-sales.store'), [
                'name' => 'Overlapping Sale',
                'starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'is_active' => true,
                'items' => [
                    ['product_variant_id' => $variant->id, 'sale_price' => 60000, 'quota' => 10],
                ],
            ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('flash_sales', ['name' => 'Overlapping Sale']);
    }

    public function test_cannot_raise_sale_price_while_active(): void
    {
        $variant = $this->variant(100000);

        $sale = FlashSale::create([
            'name' => 'Active Sale',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'is_active' => true,
        ]);
        $item = FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'sale_price' => 50000,
            'quota' => 10,
            'sold_count' => 2,
        ]);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.flash-sales.update', $sale->id), [
                'name' => 'Active Sale',
                'starts_at' => $sale->starts_at->format('Y-m-d H:i:s'),
                'ends_at' => $sale->ends_at->format('Y-m-d H:i:s'),
                'is_active' => true,
                'items' => [
                    // raising sale_price from 50000 -> 80000 while active must be rejected
                    ['id' => $item->id, 'product_variant_id' => $variant->id, 'sale_price' => 80000, 'quota' => 10],
                ],
            ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('flash_sale_items', ['id' => $item->id, 'sale_price' => 50000]);
    }

    public function test_non_admin_cannot_access_flash_sales(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)->get(route('admin.flash-sales.index'))->assertForbidden();

        $variant = $this->variant(100000);
        $this->actingAs($user)
            ->post(route('admin.flash-sales.store'), [
                'name' => 'Sneaky Sale',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'is_active' => true,
                'items' => [
                    ['product_variant_id' => $variant->id, 'sale_price' => 60000, 'quota' => 10],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('flash_sales', ['name' => 'Sneaky Sale']);
    }

    public function test_duplicate_variant_in_same_sale_rejected(): void
    {
        $variant = $this->variant(100000);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.flash-sales.store'), [
                'name' => 'Flash Sale Duplikat',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'is_active' => true,
                'items' => [
                    ['product_variant_id' => $variant->id, 'sale_price' => 60000, 'quota' => 10],
                    // Same variant submitted twice in one request -> must be a clean validation
                    // error (distinct rule), never a raw QueryException/500 from the DB unique constraint.
                    ['product_variant_id' => $variant->id, 'sale_price' => 70000, 'quota' => 5],
                ],
            ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('flash_sales', ['name' => 'Flash Sale Duplikat']);
        $this->assertDatabaseMissing('flash_sale_items', ['product_variant_id' => $variant->id]);
    }

    public function test_cannot_remove_order_referenced_item_on_update(): void
    {
        $variant = $this->variant(100000);
        $otherVariant = $this->variant(50000);

        $sale = FlashSale::create([
            'name' => 'Sale With Order',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addDays(2),
            'is_active' => true,
        ]);
        $item = FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'sale_price' => 60000,
            'quota' => 10,
            'sold_count' => 1,
        ]);
        $otherItem = FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $otherVariant->id,
            'sale_price' => 30000,
            'quota' => 10,
            'sold_count' => 0,
        ]);

        $buyer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'order_number' => 'ORD-FLASH-REF',
            'user_id' => $buyer->id,
            'status' => 'paid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 60000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 70000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'flash_sale_item_id' => $item->id,
            'product_name_snapshot' => 'P',
            'variant_name_snapshot' => 'V',
            'quantity' => 1,
            'unit_price' => 60000,
            'weight_gram' => 100,
            'subtotal' => 60000,
        ]);

        // Submit an update that drops the order-referenced item from the payload, keeping
        // only the unreferenced one.
        $response = $this->actingAs($this->admin())
            ->put(route('admin.flash-sales.update', $sale->id), [
                'name' => 'Sale With Order',
                'starts_at' => $sale->starts_at->format('Y-m-d H:i:s'),
                'ends_at' => $sale->ends_at->format('Y-m-d H:i:s'),
                'is_active' => true,
                'items' => [
                    ['id' => $otherItem->id, 'product_variant_id' => $otherVariant->id, 'sale_price' => 30000, 'quota' => 10],
                ],
            ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseHas('flash_sale_items', ['id' => $item->id, 'product_variant_id' => $variant->id]);
    }
}
