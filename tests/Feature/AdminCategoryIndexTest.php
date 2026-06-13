<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Categories page revamp (.planning/admin_pages_ux_plan.md, Fase B).
 * Adds an additive `q` search filter (flat results across hierarchy),
 * exposes `products_count`/`children_count` per category, and a `stats`
 * summary block (total/active/subcategories).
 */
class AdminCategoryIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_search_returns_flat_matching_categories()
    {
        $parent = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);
        $child = Category::create(['name' => 'Laptop Gaming', 'slug' => 'laptop-gaming', 'parent_id' => $parent->id]);
        Category::create(['name' => 'Fashion Wanita', 'slug' => 'fashion-wanita']);

        $response = $this->actingAs($this->admin)->get(route('admin.categories.index', ['q' => 'lapt']));

        $response->assertOk();
        $names = array_column($response->viewData('page')['props']['categories']['data'], 'name');

        $this->assertContains('Laptop Gaming', $names);
        $this->assertNotContains('Fashion Wanita', $names);
        $this->assertNotContains('Elektronik', $names);
    }

    public function test_products_count_is_exposed()
    {
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Laptop A',
            'slug' => 'laptop-a',
            'base_price' => 1000000,
            'weight_gram' => 1000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.categories.index'));

        $response->assertOk();
        $data = $response->viewData('page')['props']['categories']['data'];
        $found = collect($data)->firstWhere('name', 'Elektronik');

        $this->assertNotNull($found);
        $this->assertArrayHasKey('products_count', $found);
        $this->assertSame(1, $found['products_count']);
    }

    public function test_stats_shape()
    {
        $parent = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'is_active' => true]);
        Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'parent_id' => $parent->id, 'is_active' => true]);
        Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.categories.index'));

        $response->assertOk();
        $stats = $response->viewData('page')['props']['stats'];

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertSame(1, $stats['subcategories']);
    }

    public function test_non_admin_cannot_access()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('admin.categories.index'));

        $response->assertForbidden();
    }
}
