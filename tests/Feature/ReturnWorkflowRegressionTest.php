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
            'name' => 'Test', 'email' => 'a@a.com', 'password' => '123', 'role' => 'customer'
        ]);
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'O1', 'user_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid', 'subtotal' => 100, 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        $itemId = \Illuminate\Support\Facades\DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_variant_id' => null, 'product_name_snapshot' => 'Product A', 'quantity' => 5, 'unit_price' => 20, 'weight_gram' => 100, 'subtotal' => 100, 'created_at' => now(), 'updated_at' => now()
        ]);

        $returnReqId = \Illuminate\Support\Facades\DB::table('return_requests')->insertGetId([
            'return_number' => 'RET-1', 'order_id' => $orderId, 'user_id' => $userId, 'status' => 'received', 'reason' => 'Barang tidak sesuai', 'evidence_image_1' => 'evidence/1.jpg', 'created_at' => now(), 'updated_at' => now()
        ]);
        \Illuminate\Support\Facades\DB::table('return_request_items')->insert([
            'return_request_id' => $returnReqId, 'order_item_id' => $itemId, 'quantity' => 2, 'reason_code' => 'defective', 'condition' => 'damaged', 'created_at' => now(), 'updated_at' => now()
        ]);

        $order = Order::find($orderId);
        $remaining = $order->getRemainingReturnableItems();
        
        $this->assertEquals(3, $remaining[$itemId] ?? 0, 'Sisa retur maksimal harus 3');
    }

    public function test_failed_inspection_no_refund_no_restock()
    {
        $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Test', 'email' => 'b@b.com', 'password' => '123', 'role' => 'customer'
        ]);
        $categoryId = \Illuminate\Support\Facades\DB::table('categories')->insertGetId([
            'name' => 'Cat1', 'slug' => 'cat1-' . uniqid(), 'created_at' => now(), 'updated_at' => now()
        ]);
        $productId = \Illuminate\Support\Facades\DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'name' => 'P1', 'slug' => 'p1', 'description' => 'd', 'base_price' => 100, 'weight_gram' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()
        ]);
        $variantId = \Illuminate\Support\Facades\DB::table('product_variants')->insertGetId([
            'product_id' => $productId, 'sku' => 'V1', 'name' => 'V1', 'stock' => 10, 'price' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()
        ]);
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'O2', 'user_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid', 'subtotal' => 100, 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        $itemId = \Illuminate\Support\Facades\DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_variant_id' => $variantId, 'product_name_snapshot' => 'P1', 'quantity' => 1, 'unit_price' => 100, 'weight_gram' => 100, 'subtotal' => 100, 'created_at' => now(), 'updated_at' => now()
        ]);

        $returnReqId = \Illuminate\Support\Facades\DB::table('return_requests')->insertGetId([
            'return_number' => 'RET-2', 'order_id' => $orderId, 'user_id' => $userId, 'status' => 'inspection_failed', 'reason' => 'Barang tidak sesuai', 'evidence_image_1' => 'evidence/2.jpg', 'created_at' => now(), 'updated_at' => now()
        ]);
        \Illuminate\Support\Facades\DB::table('return_request_items')->insert([
            'return_request_id' => $returnReqId, 'order_item_id' => $itemId, 'quantity' => 1, 'reason_code' => 'defective', 'condition' => 'damaged', 'refund_amount' => 0, 'created_at' => now(), 'updated_at' => now()
        ]);

        $this->assertEquals(10, ProductVariant::find($variantId)->stock);

        $refundTotal = \Illuminate\Support\Facades\DB::table('return_request_items')
            ->where('return_request_id', $returnReqId)
            ->sum('refund_amount');
            
        $this->assertEquals(0, $refundTotal);
    }

    /**
     * Workflow-state angle (distinct from ReviewEligibilityMatrixTest, which is the
     * HTTP-based SSOT for review eligibility): asserts the state TRANSITION —
     * an item blocked by an active return becomes reviewable again once that
     * return is rejected, via OrderItem::hasActiveReturn().
     */
    public function test_rejected_return_allows_review()
    {
        $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Test', 'email' => 'c@c.com', 'password' => '123', 'role' => 'customer'
        ]);
        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
            'order_number' => 'O3', 'user_id' => $userId, 'status' => 'completed', 'payment_status' => 'paid', 'subtotal' => 100, 'total_amount' => 100, 'fulfillment_type' => 'delivery', 'created_at' => now(), 'updated_at' => now()
        ]);
        $itemId = \Illuminate\Support\Facades\DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_variant_id' => null, 'product_name_snapshot' => 'Product C', 'quantity' => 1, 'unit_price' => 100, 'weight_gram' => 100, 'subtotal' => 100, 'created_at' => now(), 'updated_at' => now()
        ]);

        $returnReqId = \Illuminate\Support\Facades\DB::table('return_requests')->insertGetId([
            'return_number' => 'RET-3', 'order_id' => $orderId, 'user_id' => $userId, 'status' => 'submitted', 'reason' => 'Barang tidak sesuai', 'evidence_image_1' => 'evidence/3.jpg', 'created_at' => now(), 'updated_at' => now()
        ]);
        \Illuminate\Support\Facades\DB::table('return_request_items')->insert([
            'return_request_id' => $returnReqId, 'order_item_id' => $itemId, 'quantity' => 1, 'reason_code' => 'defective', 'condition' => 'damaged', 'created_at' => now(), 'updated_at' => now()
        ]);

        $orderItem = OrderItem::find($itemId);

        // While return is active (submitted), review must be blocked
        $this->assertTrue($orderItem->hasActiveReturn(), 'Review harus terblokir selama retur aktif (submitted)');

        // Transition: admin rejects the return
        \Illuminate\Support\Facades\DB::table('return_requests')->where('id', $returnReqId)->update(['status' => 'rejected']);

        // After rejection, item is no longer "active return" → review allowed again
        $this->assertFalse($orderItem->hasActiveReturn(), 'Review harus aktif kembali setelah retur ditolak');
    }
}
