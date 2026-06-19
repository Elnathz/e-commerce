<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroSlideAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_guest_cannot_access(): void
    {
        $this->get(route('admin.hero-slides.index'))->assertRedirect();
    }

    public function test_non_admin_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user)->get(route('admin.hero-slides.index'))->assertForbidden();
    }

    public function test_admin_can_create_slide_with_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())
            ->post(route('admin.hero-slides.store'), [
                'placement' => 'hero_main',
                'title' => 'Promo', 'sort_order' => 1, 'is_active' => true,
                'image' => UploadedFile::fake()->image('banner.jpg', 1200, 440),
            ])
            ->assertRedirect(route('admin.hero-slides.index'));

        $this->assertDatabaseHas('hero_slides', ['title' => 'Promo', 'is_active' => true]);
        $slide = HeroSlide::first();
        Storage::disk('public')->assertExists($slide->image_path);
    }

    public function test_admin_can_create_slide_with_category_link(): void
    {
        Storage::fake('public');
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('admin.hero-slides.store'), [
                'placement' => 'hero_main', 'title' => 'Promo Elektronik', 'sort_order' => 1, 'is_active' => true,
                'link_type' => 'category', 'link_id' => $category->id,
                'image' => UploadedFile::fake()->image('banner.jpg', 1200, 440),
            ])
            ->assertRedirect(route('admin.hero-slides.index'));

        $slide = HeroSlide::where('title', 'Promo Elektronik')->first();
        $this->assertSame('category', $slide->link_type);
        $this->assertSame($category->id, $slide->link_id);
        $this->assertSame(route('search', ['categories' => [$category->id]]), $slide->destination_url);
    }

    public function test_admin_can_create_slide_with_product_link(): void
    {
        Storage::fake('public');
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'HP A', 'slug' => 'hp-a',
            'base_price' => 1000, 'weight_gram' => 100, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.hero-slides.store'), [
                'placement' => 'hero_main', 'title' => 'Promo HP A', 'sort_order' => 1, 'is_active' => true,
                'link_type' => 'product', 'link_id' => $product->id,
                'image' => UploadedFile::fake()->image('banner.jpg', 1200, 440),
            ])
            ->assertRedirect(route('admin.hero-slides.index'));

        $slide = HeroSlide::where('title', 'Promo HP A')->first();
        $this->assertSame(route('products.show', 'hp-a'), $slide->destination_url);
    }

    public function test_link_id_required_when_link_type_is_category_or_product(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())
            ->post(route('admin.hero-slides.store'), [
                'placement' => 'hero_main', 'title' => 'Tanpa Target', 'sort_order' => 1, 'is_active' => true,
                'link_type' => 'category',
                'image' => UploadedFile::fake()->image('banner.jpg', 1200, 440),
            ])
            ->assertSessionHasErrors('link_id');
    }

    public function test_legacy_cta_url_used_when_no_link_type_set(): void
    {
        $slide = HeroSlide::create([
            'image_path' => 'hero-slides/x.jpg', 'title' => 'Legacy', 'cta_url' => '/search', 'is_active' => true,
        ]);
        $this->assertSame('/search', $slide->destination_url);
    }

    public function test_admin_can_delete_slide(): void
    {
        Storage::fake('public');
        $slide = HeroSlide::create(['image_path' => 'hero-slides/x.jpg', 'title' => 'X', 'is_active' => true]);
        $this->actingAs($this->admin())
            ->delete(route('admin.hero-slides.destroy', $slide->id))
            ->assertRedirect(route('admin.hero-slides.index'));
        $this->assertDatabaseMissing('hero_slides', ['id' => $slide->id]);
    }
}
