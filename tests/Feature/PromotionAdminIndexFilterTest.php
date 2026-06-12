<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin Promotions/Voucher UI revamp (.planning/promotions_admin_ui_plan.md).
 * Adds additive `q`/`status`/`type` query filters and a `stats` summary block
 * to the existing `admin.promotions.index` endpoint. Pure read-only list
 * filters/aggregates on the existing `promotions` table — no change to
 * PromotionService, promotion_usages, or any quota/discount calculation.
 */
class PromotionAdminIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function createPromotion(array $overrides = []): Promotion
    {
        // PromotionObserver::created() requires an authenticated admin for promotion_histories.admin_id.
        $this->actingAs($this->admin);

        return Promotion::create(array_merge([
            'code' => 'CODE' . Str::random(6),
            'name' => 'Promo',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'applicable_shipping_type' => 'all',
            'is_active' => true,
        ], $overrides));
    }

    public function test_index_filters_by_search_query_on_code_and_name()
    {
        $this->createPromotion(['code' => 'HEMAT10', 'name' => 'Diskon Hemat']);
        $this->createPromotion(['code' => 'ONGKIRGRATIS', 'name' => 'Gratis Ongkir', 'type' => 'free_shipping']);

        $response = $this->actingAs($this->admin)->get(route('admin.promotions.index', ['q' => 'hemat']));

        $response->assertOk();
        $codes = array_column($response->viewData('page')['props']['promotions']['data'], 'code');

        $this->assertContains('HEMAT10', $codes);
        $this->assertNotContains('ONGKIRGRATIS', $codes);
    }

    public function test_index_filters_by_status()
    {
        $this->createPromotion(['code' => 'ACTIVE1', 'is_active' => true]);
        $this->createPromotion(['code' => 'INACTIVE1', 'is_active' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.promotions.index', ['status' => 'inactive']));

        $response->assertOk();
        $codes = array_column($response->viewData('page')['props']['promotions']['data'], 'code');

        $this->assertContains('INACTIVE1', $codes);
        $this->assertNotContains('ACTIVE1', $codes);
    }

    public function test_index_filters_by_type()
    {
        $this->createPromotion(['code' => 'PERCENT1', 'type' => 'percentage', 'value' => 10]);
        $this->createPromotion(['code' => 'FIXED1', 'type' => 'fixed_amount', 'value' => 5000]);
        $this->createPromotion(['code' => 'SHIP1', 'type' => 'free_shipping', 'value' => 0]);

        $response = $this->actingAs($this->admin)->get(route('admin.promotions.index', ['type' => 'free_shipping']));

        $response->assertOk();
        $codes = array_column($response->viewData('page')['props']['promotions']['data'], 'code');

        $this->assertContains('SHIP1', $codes);
        $this->assertNotContains('PERCENT1', $codes);
        $this->assertNotContains('FIXED1', $codes);
    }

    public function test_index_without_filters_returns_all_promotions()
    {
        $this->createPromotion(['code' => 'ALPHA']);
        $this->createPromotion(['code' => 'BETA']);

        $response = $this->actingAs($this->admin)->get(route('admin.promotions.index'));

        $response->assertOk();
        $codes = array_column($response->viewData('page')['props']['promotions']['data'], 'code');

        $this->assertContains('ALPHA', $codes);
        $this->assertContains('BETA', $codes);
    }

    public function test_index_stats_shape_counts_active_expiring_and_exhausted()
    {
        // Active, no expiry
        $this->createPromotion(['code' => 'ACTIVE-PLAIN', 'is_active' => true]);

        // Inactive
        $this->createPromotion(['code' => 'INACTIVE-PLAIN', 'is_active' => false]);

        // Active, expiring within 7 days
        $this->createPromotion([
            'code' => 'EXPIRING-SOON',
            'is_active' => true,
            'valid_until' => now()->addDays(3),
        ]);

        // Active, expiring far in the future (not "soon")
        $this->createPromotion([
            'code' => 'EXPIRING-LATER',
            'is_active' => true,
            'valid_until' => now()->addDays(30),
        ]);

        // Quota exhausted
        $this->createPromotion([
            'code' => 'EXHAUSTED',
            'is_active' => true,
            'max_usage' => 10,
            'used_count' => 10,
        ]);

        // Quota not exhausted
        $this->createPromotion([
            'code' => 'NOT-EXHAUSTED',
            'is_active' => true,
            'max_usage' => 10,
            'used_count' => 5,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.promotions.index'));

        $response->assertOk();
        $stats = $response->viewData('page')['props']['stats'];

        $this->assertSame(6, $stats['total']);
        $this->assertSame(5, $stats['active']);
        $this->assertSame(1, $stats['expiring_soon']);
        $this->assertSame(1, $stats['exhausted']);
    }

    public function test_index_returns_usages_count_for_quota_progress()
    {
        $promotion = $this->createPromotion(['code' => 'WITHUSAGE', 'max_usage' => 100]);

        $response = $this->actingAs($this->admin)->get(route('admin.promotions.index'));

        $response->assertOk();
        $data = $response->viewData('page')['props']['promotions']['data'][0];

        $this->assertSame('WITHUSAGE', $data['code']);
        $this->assertArrayHasKey('usages_count', $data);
        $this->assertSame(0, $data['usages_count']);
    }
}
