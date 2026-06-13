<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Category delete guard (.planning/admin_pages_ux_plan.md, Fase B, locked decision #6).
 *
 * `products.category_id` is `cascadeOnDelete` — deleting a category with products
 * silently cascade-deletes those products. The guard is the mandatory invariant
 * (last line of defense against bugs/direct route calls/UI bypass); the disabled
 * delete button in the UI is bonus UX, not a replacement.
 *
 * Definition of "empty" is NON-RECURSIVE: a category is deletable only if it has
 * no direct products AND no direct subcategories.
 */
class AdminCategoryDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_cannot_delete_category_with_products()
    {
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Laptop A',
            'slug' => 'laptop-a',
            'base_price' => 1000000,
            'weight_gram' => 1000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_cannot_delete_category_with_children()
    {
        $parent = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);
        $child = Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $parent->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
        $this->assertDatabaseHas('categories', ['id' => $child->id]);
    }

    public function test_can_delete_empty_category()
    {
        $category = Category::create(['name' => 'Kategori Kosong', 'slug' => 'kategori-kosong']);

        $response = $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category->id));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
