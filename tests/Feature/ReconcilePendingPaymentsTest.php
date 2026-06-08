<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

class ReconcilePendingPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_a_pending_to_paid()
    {
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-1', 'user_id' => 1, 'status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        
        Http::fake(['*' => Http::response(['data' => ['status' => 'PAID']], 200)]);
        Artisan::call('payment:reconcile');

        $this->assertEquals('paid', Order::find($orderId)->payment_status);
    }

    public function test_case_b_cancelled_stays_cancelled()
    {
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-2', 'user_id' => 1, 'status' => 'cancelled', 'payment_status' => 'pending', 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        
        Http::fake(['*' => Http::response(['data' => ['status' => 'PAID']], 200)]);
        Artisan::call('payment:reconcile');

        $this->assertEquals('cancelled', Order::find($orderId)->status);
    }

    public function test_case_c_refunded_stays_refunded()
    {
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-3', 'user_id' => 1, 'status' => 'refunded', 'payment_status' => 'paid', 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        
        Http::fake(['*' => Http::response(['data' => ['status' => 'PAID']], 200)]);
        Artisan::call('payment:reconcile');

        $this->assertEquals('refunded', Order::find($orderId)->status);
    }

    public function test_case_d_gateway_timeout_is_suppressed()
    {
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'ORD-4', 'user_id' => 1, 'status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        
        Http::fake(['*' => function () { throw new \Illuminate\Http\Client\ConnectionException('Timeout'); }]);
        $exitCode = Artisan::call('payment:reconcile');
        
        $this->assertEquals(0, $exitCode);
        $this->assertEquals('pending', Order::find($orderId)->payment_status);
    }
}
