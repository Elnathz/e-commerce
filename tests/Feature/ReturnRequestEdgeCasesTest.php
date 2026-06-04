<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use Illuminate\Support\Facades\Artisan;

class ReturnRequestEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $order;
    protected $orderItem;
    protected $category;
    protected $product;
    protected $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'customer']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->category = \App\Models\Category::create([
            'name' => 'Edge Case Category',
            'slug' => 'edge-case-category'
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Edge Case Product',
            'slug' => 'edge-case-product',
            'description' => 'Test',
            'base_price' => 100000,
            'price' => 100000,
            'stock' => 100,
            'weight_gram' => 500,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Edge Case Variant',
            'sku' => 'TEST-EDGE-01',
            'price' => 100000,
            'stock' => 10,
        ]);

        $this->order = Order::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-EDGE-123',
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 500000,
            'shipping_fee' => 0,
            'total_amount' => 500000, // 5 items * 100000
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'Edge Case Product',
            'quantity' => 5, // Qty 5
            'unit_price' => 100000,
            'weight_gram' => 500,
            'subtotal' => 500000,
        ]);
    }

    public function test_edge_case_a_multiple_active_returns()
    {
        // First return for 2 items
        $this->actingAs($this->user)
            ->post(route('returns.store', $this->order->order_number), [
                'reason' => 'First return',
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'quantity' => 2,
                        'reason_code' => 'defective',
                        'condition' => 'opened'
                    ]
                ],
                'images' => [] // Assume mocked validation or no images required in this stub if we mock storage, wait images are required
            ]);

        // Create the first return manually to bypass image upload requirement for this test
        $return1 = ReturnRequest::create([
            'return_number' => 'RET-1',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'submitted',
            'reason' => 'First Return',
            'evidence_image_1' => 'test.jpg'
        ]);
        ReturnRequestItem::create([
            'return_request_id' => $return1->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 2,
            'reason_code' => 'defective',
            'condition' => 'opened'
        ]);

        // Try to create second return with 4 items using the controller
        // We will mock the image to pass validation
        $file = \Illuminate\Http\UploadedFile::fake()->image('test.jpg');
        
        $response = $this->actingAs($this->user)
            ->post(route('returns.store', $this->order->order_number), [
                'reason' => 'Second return',
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'quantity' => 4, // 2 already returned, remaining is 3. Trying to return 4 should fail.
                        'reason_code' => 'defective',
                        'condition' => 'opened'
                    ]
                ],
                'images' => [$file]
            ]);

        $response->assertSessionHas('error', 'Kuantitas retur melebihi batas yang diperbolehkan atau barang tidak dapat diretur lagi.');
        $this->assertEquals(1, ReturnRequest::count()); // Only the manual one exists
    }

    public function test_edge_case_b_reject_setelah_customer_shipped()
    {
        $return = ReturnRequest::create([
            'return_number' => 'RET-2',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'customer_shipped',
            'reason' => 'Test',
            'evidence_image_1' => 'test.jpg'
        ]);
        $item = ReturnRequestItem::create([
            'return_request_id' => $return->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened'
        ]);

        // Receive the item
        $this->actingAs($this->admin)->post(route('admin.returns.receive', $return->id));
        
        // Inspect and FAIL
        $response = $this->actingAs($this->admin)->post(route('admin.returns.inspect', $return->id), [
            'inspection_result' => 'failed',
            'admin_notes' => 'Barang rusak parah karena kesalahan user',
            'items' => []
        ]);

        $return->refresh();
        $this->assertEquals('rejected', $return->status);
        $this->assertNull($return->refund_status); // Refund status should not be set
        
        $this->variant->refresh();
        $this->assertEquals(10, $this->variant->stock); // Stock remains unchanged
    }

    public function test_edge_case_c_double_click_admin()
    {
        $return = ReturnRequest::create([
            'return_number' => 'RET-3',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'inspected',
            'inspection_result' => 'passed',
            'reason' => 'Test',
            'evidence_image_1' => 'test.jpg'
        ]);
        $item = ReturnRequestItem::create([
            'return_request_id' => $return->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
            'refund_amount' => 100000,
            'is_restocked' => false
        ]);

        // First click
        $response1 = $this->actingAs($this->admin)->post(route('admin.returns.refund', $return->id));
        $response1->assertRedirect();
        
        // Second click
        $response2 = $this->actingAs($this->admin)->post(route('admin.returns.refund', $return->id));
        $response2->assertSessionHas('error'); // Should fail because status is no longer 'inspected'

        $return->refresh();
        $this->assertEquals('refund_processed', $return->status);
    }

    public function test_edge_case_d_backfill_idempotency()
    {
        // Add old format order with is_returned = true but no ReturnRequest
        $oldOrder = Order::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-OLD-123',
            'status' => 'refunded',
            'payment_status' => 'paid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 100000,
            'shipping_fee' => 0,
            'total_amount' => 100000,
            'is_returned' => true,
            'return_reason' => 'Old format return'
        ]);
        OrderItem::create([
            'order_id' => $oldOrder->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'Old Product',
            'quantity' => 1,
            'unit_price' => 100000,
            'weight_gram' => 500,
            'subtotal' => 100000,
        ]);

        // Add old format ReturnRequest with NO items
        $oldReturn = ReturnRequest::create([
            'return_number' => 'RET-OLD-123',
            'order_id' => $oldOrder->id,
            'user_id' => $this->user->id,
            'status' => 'refund_processed',
            'reason' => 'Old format return',
            'evidence_image_1' => 'test.jpg',
        ]);

        // Run backfill first time
        Artisan::call('app:backfill-returns');
        
        $itemCountAfterFirst = ReturnRequestItem::where('return_request_id', $oldReturn->id)->count();
        $historyCountAfterFirst = \App\Models\ReturnHistory::where('return_request_id', $oldReturn->id)->count();
        $this->assertEquals(1, $itemCountAfterFirst);
        $this->assertEquals(1, $historyCountAfterFirst);

        // Run backfill second time
        Artisan::call('app:backfill-returns');
        
        $itemCountAfterSecond = ReturnRequestItem::where('return_request_id', $oldReturn->id)->count();
        $historyCountAfterSecond = \App\Models\ReturnHistory::where('return_request_id', $oldReturn->id)->count();
        $this->assertEquals(1, $itemCountAfterSecond); // Should still be 1
        $this->assertEquals(1, $historyCountAfterSecond); // Should still be 1
    }
}
