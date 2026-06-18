<?php
namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_announcement_not_shared(): void
    {
        SiteSetting::set('announcement_active', '0');
        SiteSetting::set('announcement_text', 'Promo!');
        $this->get('/')->assertInertia(fn ($p) => $p->where('announcement', null));
    }

    public function test_active_announcement_shared(): void
    {
        SiteSetting::set('announcement_active', '1');
        SiteSetting::set('announcement_text', 'Gratis ongkir');
        $this->get('/')->assertInertia(fn ($p) =>
            $p->where('announcement.text', 'Gratis ongkir')
        );
    }

    public function test_admin_can_update_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'announcement_active' => true,
            'announcement_text' => 'Diskon akhir pekan',
            'announcement_link_url' => '/search',
            'announcement_link_label' => 'Lihat',
        ])->assertRedirect();
        $this->assertSame('1', SiteSetting::get('announcement_active'));
        $this->assertSame('Diskon akhir pekan', SiteSetting::get('announcement_text'));
    }
}
