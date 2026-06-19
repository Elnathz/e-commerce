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

    public function test_cannot_create_5th_hero_side_slide(): void
    {
        Storage::fake('public');
        for ($i = 1; $i <= 4; $i++) {
            HeroSlide::create(['placement' => 'hero_side', 'image_path' => "hero-slides/s{$i}.jpg", 'title' => "Side {$i}", 'is_active' => true]);
        }

        $this->actingAs($this->admin())
            ->post(route('admin.hero-slides.store'), [
                'placement' => 'hero_side', 'title' => 'Side 5', 'sort_order' => 5, 'is_active' => true,
                'image' => UploadedFile::fake()->image('banner.jpg', 1200, 440),
            ])
            ->assertSessionHasErrors('placement');

        $this->assertDatabaseMissing('hero_slides', ['title' => 'Side 5']);
        $this->assertSame(4, HeroSlide::where('placement', 'hero_side')->count());
    }

    public function test_cannot_create_11th_hero_main_slide(): void
    {
        Storage::fake('public');
        for ($i = 1; $i <= 10; $i++) {
            HeroSlide::create(['placement' => 'hero_main', 'image_path' => "hero-slides/m{$i}.jpg", 'title' => "Main {$i}", 'is_active' => true]);
        }

        $this->actingAs($this->admin())
            ->post(route('admin.hero-slides.store'), [
                'placement' => 'hero_main', 'title' => 'Main 11', 'sort_order' => 11, 'is_active' => true,
                'image' => UploadedFile::fake()->image('banner.jpg', 1200, 440),
            ])
            ->assertSessionHasErrors('placement');

        $this->assertSame(10, HeroSlide::where('placement', 'hero_main')->count());
    }

    public function test_editing_slide_within_full_placement_still_works(): void
    {
        Storage::fake('public');
        $slides = [];
        for ($i = 1; $i <= 4; $i++) {
            $slides[] = HeroSlide::create(['placement' => 'hero_side', 'image_path' => "hero-slides/s{$i}.jpg", 'title' => "Side {$i}", 'is_active' => true]);
        }

        $this->actingAs($this->admin())
            ->put(route('admin.hero-slides.update', $slides[0]->id), [
                'placement' => 'hero_side', 'title' => 'Side 1 Updated', 'sort_order' => 1, 'is_active' => true,
            ])
            ->assertRedirect(route('admin.hero-slides.index'));

        $this->assertDatabaseHas('hero_slides', ['id' => $slides[0]->id, 'title' => 'Side 1 Updated']);
    }

    public function test_editing_slide_still_works_when_placement_already_over_cap(): void
    {
        // Simulasikan data lama yang sudah melebihi cap baru (mis. dibuat sebelum guard ada).
        Storage::fake('public');
        $slides = [];
        for ($i = 1; $i <= 5; $i++) {
            $slides[] = HeroSlide::create(['placement' => 'hero_side', 'image_path' => "hero-slides/s{$i}.jpg", 'title' => "Side {$i}", 'is_active' => true]);
        }

        $this->actingAs($this->admin())
            ->put(route('admin.hero-slides.update', $slides[4]->id), [
                'placement' => 'hero_side', 'title' => 'Side 5 Updated', 'sort_order' => 5, 'is_active' => true,
            ])
            ->assertRedirect(route('admin.hero-slides.index'));

        $this->assertDatabaseHas('hero_slides', ['id' => $slides[4]->id, 'title' => 'Side 5 Updated']);
        $this->assertSame(5, HeroSlide::where('placement', 'hero_side')->count());
    }

    public function test_cannot_move_slide_into_full_placement(): void
    {
        Storage::fake('public');
        for ($i = 1; $i <= 4; $i++) {
            HeroSlide::create(['placement' => 'hero_side', 'image_path' => "hero-slides/s{$i}.jpg", 'title' => "Side {$i}", 'is_active' => true]);
        }
        $mainSlide = HeroSlide::create(['placement' => 'hero_main', 'image_path' => 'hero-slides/m1.jpg', 'title' => 'Main 1', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->put(route('admin.hero-slides.update', $mainSlide->id), [
                'placement' => 'hero_side', 'title' => 'Main 1', 'sort_order' => 1, 'is_active' => true,
            ])
            ->assertSessionHasErrors('placement');

        $this->assertDatabaseHas('hero_slides', ['id' => $mainSlide->id, 'placement' => 'hero_main']);
    }
}
