<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin profile "Aktivitas Saya" feed (Sprint 11 ad-hoc, follow-up to tracker #61).
 * Admin\ProfileController::edit() now exposes recentActivity sourced from
 * activity_logs, scoped to the authenticated admin's own actions only.
 */
class AdminProfileActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->otherAdmin = User::factory()->create(['role' => 'admin']);
    }

    public function test_profile_page_includes_only_the_authenticated_admins_own_activity()
    {
        $own = ActivityLog::create([
            'type' => 'order.shipped',
            'actor_type' => 'admin',
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'subject_type' => 'Order',
            'subject_id' => 1,
            'subject_label' => 'ORD-001',
            'description' => 'Mengirim order ORD-001',
        ]);

        ActivityLog::create([
            'type' => 'order.shipped',
            'actor_type' => 'admin',
            'actor_id' => $this->otherAdmin->id,
            'actor_name' => $this->otherAdmin->name,
            'subject_type' => 'Order',
            'subject_id' => 2,
            'subject_label' => 'ORD-002',
            'description' => 'Mengirim order ORD-002',
        ]);

        ActivityLog::create([
            'type' => 'order.paid',
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_name' => 'System',
            'subject_type' => 'Order',
            'subject_id' => 3,
            'subject_label' => 'ORD-003',
            'description' => 'Order ORD-003 dibayar',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.profile.edit'));

        $response->assertOk();
        $activity = $response->viewData('page')['props']['recentActivity'];
        $activityIds = array_column($activity, 'id');

        $this->assertContains($own->id, $activityIds);
        $this->assertCount(1, $activityIds);
    }

    public function test_recent_activity_is_limited_to_twenty_and_ordered_by_latest_first()
    {
        for ($i = 1; $i <= 25; $i++) {
            $log = ActivityLog::create([
                'type' => 'order.shipped',
                'actor_type' => 'admin',
                'actor_id' => $this->admin->id,
                'actor_name' => $this->admin->name,
                'subject_type' => 'Order',
                'subject_id' => $i,
                'subject_label' => "ORD-{$i}",
                'description' => "Mengirim order ORD-{$i}",
            ]);

            // created_at is not mass-assignable; set it explicitly so ordering
            // can be verified deterministically.
            $log->created_at = now()->subMinutes(30 - $i);
            $log->save();
        }

        $response = $this->actingAs($this->admin)->get(route('admin.profile.edit'));

        $response->assertOk();
        $activity = $response->viewData('page')['props']['recentActivity'];

        $this->assertCount(20, $activity);
        $this->assertSame('ORD-25', $activity[0]['subject_label']);
        $this->assertSame('ORD-6', $activity[19]['subject_label']);
    }
}
