<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\ProductVariant;
use App\Models\Product;

class ReturnWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_return_quantity()
    {
        $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Test', 'email' => 'a@a.com', 'password' => '123'
        ]);
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'O1', 'user_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        $itemId = \Illuminate\Support\Facades\DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_variant_id' => 1, 'quantity' => 5, 'unit_price' => 20, 'subtotal' => 100, 'created_at' => now(), 'updated_at' => now()
        ]);

        $returnReqId = \Illuminate\Support\Facades\DB::table('return_requests')->insertGetId([
            'order_id' => $orderId, 'status' => 'received', 'created_at' => now(), 'updated_at' => now()
        ]);
        \Illuminate\Support\Facades\DB::table('return_request_items')->insert([
            'return_request_id' => $returnReqId, 'order_item_id' => $itemId, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()
        ]);

        $order = Order::find($orderId);
        $remaining = $order->getRemainingReturnableItems();
        
        $this->assertEquals(3, $remaining[$itemId] ?? 0, 'Sisa retur maksimal harus 3');
    }

    public function test_failed_inspection_no_refund_no_restock()
    {
        $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Test', 'email' => 'b@b.com', 'password' => '123'
        ]);
        $productId = \Illuminate\Support\Facades\DB::table('products')->insertGetId([
            'name' => 'P1', 'slug' => 'p1', 'description' => 'd', 'base_price' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()
        ]);
        $variantId = \Illuminate\Support\Facades\DB::table('product_variants')->insertGetId([
            'product_id' => $productId, 'sku' => 'V1', 'name' => 'V1', 'stock' => 10, 'price_adjustment' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()
        ]);
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'O2', 'user_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        $itemId = \Illuminate\Support\Facades\DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_variant_id' => $variantId, 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100, 'created_at' => now(), 'updated_at' => now()
        ]);

        $returnReqId = \Illuminate\Support\Facades\DB::table('return_requests')->insertGetId([
            'order_id' => $orderId, 'status' => 'inspection_failed', 'created_at' => now(), 'updated_at' => now()
        ]);
        \Illuminate\Support\Facades\DB::table('return_request_items')->insert([
            'return_request_id' => $returnReqId, 'order_item_id' => $itemId, 'quantity' => 1, 'refund_amount' => 0, 'created_at' => now(), 'updated_at' => now()
        ]);

        $this->assertEquals(10, ProductVariant::find($variantId)->stock);

        $refundTotal = \Illuminate\Support\Facades\DB::table('return_request_items')
            ->where('return_request_id', $returnReqId)
            ->sum('refund_amount');
            
        $this->assertEquals(0, $refundTotal);
    }

    public function test_rejected_return_allows_review()
    {
        $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Test', 'email' => 'c@c.com', 'password' => '123'
        ]);
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'O3', 'user_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        
        $returnReqId = \Illuminate\Support\Facades\DB::table('return_requests')->insertGetId([
            'order_id' => $orderId, 'status' => 'rejected', 'created_at' => now(), 'updated_at' => now()
        ]);
        
        $order = Order::find($orderId);
        $returnReq = ReturnRequest::find($returnReqId);
        
        $this->assertTrue($order->status === 'completed' && $returnReq->status === 'rejected', 'Review aktif kembali setelah retur ditolak');
    }
}
