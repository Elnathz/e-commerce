<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Activity Log full-page (Sprint 11 P2 #10 closeout).
 * Event-history view over `activity_logs` (order.* & return.* only) — not a
 * field-level audit trail. See `.planning/activity_log_fullpage_plan.md`.
 */
class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeLog(array $overrides = []): ActivityLog
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $log = ActivityLog::create(array_merge([
            'type' => 'order.shipped',
            'actor_type' => 'admin',
            'actor_id' => $this->admin->id,
            'actor_name' => $this->admin->name,
            'subject_type' => 'order',
            'subject_id' => 1,
            'subject_label' => 'ORD-001',
            'description' => 'Order ORD-001 berubah dari processing ke shipped',
            'metadata' => ['amount' => 100000],
        ], $overrides));

        if ($createdAt !== null) {
            $log->created_at = $createdAt;
            $log->save();
        }

        return $log;
    }

    public function test_index_renders_paginated_logs()
    {
        for ($i = 1; $i <= 55; $i++) {
            $this->makeLog([
                'subject_id' => $i,
                'subject_label' => "ORD-{$i}",
                'description' => "Order ORD-{$i} berubah dari processing ke shipped",
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.activity-log.index'));

        $response->assertOk();
        $logs = $response->viewData('page')['props']['logs'];

        $this->assertCount(50, $logs['data']);
        $this->assertSame(55, $logs['total']);
    }

    public function test_filter_by_type_prefix_returns_only_matching()
    {
        $this->makeLog(['type' => 'order.paid', 'subject_label' => 'ORD-PAID']);
        $this->makeLog([
            'type' => 'return.submitted',
            'subject_type' => 'return_request',
            'subject_label' => 'RET-001',
            'description' => 'Retur RET-001 berubah dari - ke submitted',
            'metadata' => [],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-log.index', ['type' => 'order']));

        $response->assertOk();
        $logs = $response->viewData('page')['props']['logs']['data'];

        $this->assertCount(1, $logs);
        $this->assertSame('order.paid', $logs[0]['type']);
    }

    public function test_filter_by_actor_type()
    {
        $this->makeLog(['actor_type' => 'admin', 'subject_label' => 'ORD-ADMIN']);
        $this->makeLog([
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_name' => 'System',
            'subject_label' => 'ORD-SYSTEM',
            'description' => 'Order ORD-SYSTEM berubah dari pending ke paid',
            'type' => 'order.paid',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-log.index', ['actor' => 'system']));

        $response->assertOk();
        $logs = $response->viewData('page')['props']['logs']['data'];

        $this->assertCount(1, $logs);
        $this->assertSame('system', $logs[0]['actor_type']);
    }

    public function test_search_matches_description_subject_label_and_actor_name()
    {
        $this->makeLog([
            'subject_label' => 'ORD-001',
            'description' => 'Order ORD-001 berubah dari processing ke shipped',
            'actor_name' => 'Budi Admin',
        ]);
        $this->makeLog([
            'subject_id' => 2,
            'subject_label' => 'ORD-UNRELATED',
            'description' => 'Order ORD-UNRELATED berubah dari pending ke paid',
            'actor_name' => 'Siti Admin',
            'type' => 'order.paid',
        ]);

        // Matches via description
        $byDescription = $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index', ['q' => 'processing ke shipped']))
            ->viewData('page')['props']['logs']['data'];
        $this->assertCount(1, $byDescription);
        $this->assertSame('ORD-001', $byDescription[0]['subject_label']);

        // Matches via subject_label
        $bySubjectLabel = $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index', ['q' => 'ORD-UNRELATED']))
            ->viewData('page')['props']['logs']['data'];
        $this->assertCount(1, $bySubjectLabel);
        $this->assertSame('ORD-UNRELATED', $bySubjectLabel[0]['subject_label']);

        // Matches via actor_name
        $byActorName = $this->actingAs($this->admin)
            ->get(route('admin.activity-log.index', ['q' => 'Siti']))
            ->viewData('page')['props']['logs']['data'];
        $this->assertCount(1, $byActorName);
        $this->assertSame('Siti Admin', $byActorName[0]['actor_name']);
    }

    public function test_date_range_filter_bounds_results()
    {
        $this->makeLog(['subject_label' => 'ORD-OLD', 'created_at' => now()->subDays(10)]);
        $this->makeLog(['subject_id' => 2, 'subject_label' => 'ORD-IN-RANGE', 'created_at' => now()->subDays(2)]);
        $this->makeLog(['subject_id' => 3, 'subject_label' => 'ORD-FUTURE', 'created_at' => now()->addDays(5)]);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-log.index', [
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk();
        $logs = $response->viewData('page')['props']['logs']['data'];

        $this->assertCount(1, $logs);
        $this->assertSame('ORD-IN-RANGE', $logs[0]['subject_label']);
    }

    public function test_logs_are_ordered_latest_first()
    {
        $this->makeLog(['subject_label' => 'ORD-OLDEST', 'created_at' => now()->subDays(3)]);
        $this->makeLog(['subject_id' => 2, 'subject_label' => 'ORD-MIDDLE', 'created_at' => now()->subDays(2)]);
        $this->makeLog(['subject_id' => 3, 'subject_label' => 'ORD-NEWEST', 'created_at' => now()->subDay()]);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-log.index'));

        $response->assertOk();
        $logs = $response->viewData('page')['props']['logs']['data'];

        $this->assertSame('ORD-NEWEST', $logs[0]['subject_label']);
        $this->assertSame('ORD-OLDEST', $logs[2]['subject_label']);
    }

    public function test_filters_can_be_combined()
    {
        $matching = $this->makeLog([
            'type' => 'order.paid',
            'actor_type' => 'admin',
            'subject_label' => 'ORD-MATCH',
            'description' => 'Order ORD-MATCH berubah dari pending ke paid',
            'created_at' => now()->subDay(),
        ]);

        // Wrong type (return instead of order)
        $this->makeLog([
            'type' => 'return.submitted',
            'actor_type' => 'admin',
            'subject_type' => 'return_request',
            'subject_id' => 2,
            'subject_label' => 'RET-WRONGTYPE',
            'description' => 'Retur RET-WRONGTYPE berubah dari - ke submitted',
            'metadata' => [],
            'created_at' => now()->subDay(),
        ]);

        // Wrong actor (system instead of admin)
        $this->makeLog([
            'type' => 'order.paid',
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_name' => 'System',
            'subject_id' => 3,
            'subject_label' => 'ORD-WRONGACTOR',
            'description' => 'Order ORD-WRONGACTOR berubah dari pending ke paid',
            'created_at' => now()->subDay(),
        ]);

        // Wrong date (outside range)
        $this->makeLog([
            'type' => 'order.paid',
            'actor_type' => 'admin',
            'subject_id' => 4,
            'subject_label' => 'ORD-WRONGDATE',
            'description' => 'Order ORD-WRONGDATE berubah dari pending ke paid',
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.activity-log.index', [
            'type' => 'order',
            'actor' => 'admin',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk();
        $logs = $response->viewData('page')['props']['logs']['data'];

        $this->assertCount(1, $logs);
        $this->assertSame($matching->id, $logs[0]['id']);
        $this->assertSame('ORD-MATCH', $logs[0]['subject_label']);
    }

    public function test_non_admin_cannot_access_activity_log()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('admin.activity-log.index'));

        $response->assertForbidden();
    }
}
