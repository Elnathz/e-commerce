<?php
namespace Tests\Feature;

use App\Models\HeroSlide;
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
                'title' => 'Promo', 'subtitle' => 'Sub', 'badge_label' => 'Hemat',
                'cta_label' => 'Cek', 'cta_url' => '/search', 'sort_order' => 1, 'is_active' => true,
                'image' => UploadedFile::fake()->image('banner.jpg', 1200, 440),
            ])
            ->assertRedirect(route('admin.hero-slides.index'));

        $this->assertDatabaseHas('hero_slides', ['title' => 'Promo', 'is_active' => true]);
        $slide = HeroSlide::first();
        Storage::disk('public')->assertExists($slide->image_path);
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
