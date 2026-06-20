<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
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
}
