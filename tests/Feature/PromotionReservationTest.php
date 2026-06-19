<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Promotion;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Support\Facades\Auth;

class PromotionReservationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reserve -> release lifecycle against the current PromotionService API
     * (code/userId/orderId/subtotal/shippingCost). The previous version of this
     * test called reserve($promoId, $userId): bool / release($promoId, $userId),
     * a signature that no longer exists on PromotionService.
     */
    public function test_reserve_confirm_release_logic()
    {
        $user = User::factory()->create();

        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-PROMO-1',
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'total_amount' => 90000,
            'fulfillment_type' => 'delivery',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $promoId = \Illuminate\Support\Facades\DB::table('promotions')->insertGetId([
            'code' => 'TEST_PROMO',
            'name' => 'Promo',
            'type' => 'fixed_amount',
            'value' => 10000,
            'max_usage' => 2,
            'used_count' => 0,
            'is_active' => true,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // reserve() is called during an authenticated checkout request; the
        // PromotionObserver logs Auth::id() as admin_id on the resulting
        // promotion_histories row (FK to users), so simulate that context.
        Auth::login($user);

        $service = new PromotionService();

        // Phase 1: Reserve at checkout — increments used_count, creates promotion_usages row
        $usage = $service->reserve('TEST_PROMO', $user->id, $orderId, 100000, 15000);
        $this->assertEquals('reserved', $usage->status);
        $this->assertEquals(10000, $usage->discount_applied);

        $promo = Promotion::find($promoId);
        $this->assertEquals(1, $promo->used_count);

        // Phase 2B: Release (order expired/cancelled) — restores quota
        $service->release($orderId);

        $promo->refresh();
        $this->assertEquals(0, $promo->used_count);

        $usage->refresh();
        $this->assertEquals('released', $usage->status);
        $this->assertNotNull($usage->released_at);
    }

    /**
     * FR030 gate: a voucher whose `applies_to_flash_sale` flag is off must be
     * rejected by validate() when the cart contains Flash Sale items
     * (containsFlash = true).
     */
    public function test_voucher_rejected_when_cart_has_flash_and_flag_off(): void
    {
        // PromotionObserver::created() logs Auth::id() as admin_id (FK to users, NOT NULL).
        Auth::login(User::factory()->create());

        Promotion::create([
            'code' => 'HEMAT10',
            'name' => 'Hemat 10',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'max_usage' => null,
            'used_count' => 0,
            'applicable_shipping_type' => 'all',
            'is_active' => true,
            'applies_to_flash_sale' => false,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
        ]);

        $res = app(PromotionService::class)->validate('HEMAT10', 100000, 0, null, true);

        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('Flash Sale', $res['message']);
    }

    /**
     * FR030 gate: when `applies_to_flash_sale` is true, the voucher remains
     * valid even when the cart contains Flash Sale items.
     */
    public function test_voucher_allowed_when_flag_on(): void
    {
        // PromotionObserver::created() logs Auth::id() as admin_id (FK to users, NOT NULL).
        Auth::login(User::factory()->create());

        Promotion::create([
            'code' => 'FLASHOK',
            'name' => 'Flash OK',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'max_usage' => null,
            'used_count' => 0,
            'applicable_shipping_type' => 'all',
            'is_active' => true,
            'applies_to_flash_sale' => true,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
        ]);

        $res = app(PromotionService::class)->validate('FLASHOK', 100000, 0, null, true);

        $this->assertTrue($res['valid']);
    }
}
