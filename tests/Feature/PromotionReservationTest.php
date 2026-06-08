<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Promotion;
use App\Services\PromotionService;

class PromotionReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_confirm_release_logic()
    {
        $promoId = \Illuminate\Support\Facades\DB::table('promotions')->insertGetId([
            'code' => 'TEST_PROMO',
            'name' => 'Promo',
            'type' => 'fixed_amount',
            'value' => 10000,
            'max_usage' => 2,
            'used_count' => 0,
            'is_active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new PromotionService();
        
        $reservation = $service->reserve($promoId, 1);
        $this->assertTrue($reservation);
        
        $promo = Promotion::find($promoId);
        $this->assertEquals(1, $promo->used_count);
        
        $service->release($promoId, 1);
        
        $promo = Promotion::find($promoId);
        $this->assertEquals(0, $promo->used_count);
    }
}
