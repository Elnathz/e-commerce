<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $admin;
    private $order;
    private $orderItem;
    private $category;
    private $product;
    private $variant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'description' => 'Test Description',
            'base_price' => 100000,
            'weight_gram' => 500,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'TEST-SKU',
            'name' => 'Standard',
            'price' => 90000,
            'stock' => 10,
            'reserved_stock' => 0,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-TEST-123',
            'user_id' => $this->user->id,
            'status' => 'completed',
            'completed_at' => now(), // Needed for 7 day eligibility
            'fulfillment_type' => 'delivery',
            'subtotal' => 90000,
            'shipping_cost' => 10000,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => $this->product->name,
            'variant_name_snapshot' => $this->variant->name,
            'quantity' => 1,
            'unit_price' => 90000,
            'weight_gram' => 500,
            'subtotal' => 90000,
        ]);
    }

    public function test_customer_can_submit_return_request(): void
    {
        $response = $this->actingAs($this->user)->post(route('returns.store', $this->order->order_number), [
            'reason' => 'Defective product',
            'items' => [
                [
                    'order_item_id' => $this->orderItem->id,
                    'quantity' => 1,
                    'reason_code' => 'defective',
                    'condition' => 'opened',
                ]
            ],
            'images' => [
                UploadedFile::fake()->image('evidence.jpg'),
            ],
        ]);

        $returnRequest = ReturnRequest::first();
        $this->assertNotNull($returnRequest);
        $this->assertEquals('submitted', $returnRequest->status);
        $this->assertEquals('Defective product', $returnRequest->reason);
        $this->assertCount(1, $returnRequest->items);
        $response->assertRedirect(route('returns.show', $returnRequest->return_number));
    }

    public function test_admin_can_approve_return_request_within_limits(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-TEST-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'received',
            'reason' => 'Defective product',
            'evidence_image_1' => 'returns/test.jpg',
        ]);

        $returnItem = ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        // Admin inspects and sets refund amount
        $response = $this->actingAs($this->admin)->post(route('admin.returns.inspect', $returnRequest->id), [
            'inspection_result' => 'passed',
            'items' => [
                [
                    'id' => $returnItem->id,
                    'refund_amount' => 50000,
                    'restock' => true,
                ]
            ],
            'admin_notes' => 'Passed inspection',
        ]);

        $response->assertSessionHasNoErrors();
        $returnRequest->refresh();
        $this->assertEquals('inspected', $returnRequest->status);
        $this->assertEquals('passed', $returnRequest->inspection_result);
        
        $returnItem->refresh();
        $this->assertEquals(50000, $returnItem->refund_amount);
        $this->assertTrue((bool) $returnItem->is_restocked);
    }

    public function test_admin_cannot_approve_return_request_exceeding_limits(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-TEST-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'received',
            'reason' => 'Defective product',
            'evidence_image_1' => 'returns/test.jpg',
        ]);

        $returnItem = ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        // Attempt to inspect with refund amount exceeding the order subtotal/total_amount (90000 for item)
        $response = $this->actingAs($this->admin)->post(route('admin.returns.inspect', $returnRequest->id), [
            'inspection_result' => 'passed',
            'items' => [
                [
                    'id' => $returnItem->id,
                    'refund_amount' => 150000,
                    'restock' => true,
                ]
            ],
            'admin_notes' => 'Passed inspection',
        ]);

        $response->assertSessionHasErrors(['items.0.refund_amount']);
        $returnRequest->refresh();
        $this->assertEquals('received', $returnRequest->status); // Status remains unchanged
    }

    public function test_full_return_workflow(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-TEST-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'submitted',
            'reason' => 'Defective product',
            'evidence_image_1' => 'returns/test.jpg',
        ]);

        $returnItem = ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        // 1. Admin Approve
        $this->actingAs($this->admin)->post(route('admin.returns.approve', $returnRequest->id));
        $returnRequest->refresh();
        $this->assertEquals('approved', $returnRequest->status);

        // 2. Customer inputs tracking info
        $response = $this->actingAs($this->user)->post(route('returns.tracking', $returnRequest->return_number), [
            'return_courier' => 'JNE',
            'return_tracking_number' => 'JNETEST123',
        ]);
        if ($returnRequest->fresh()->status !== 'customer_shipped') {
            dump($response->getContent());
            $response->dumpSession();
        }
        $response->assertSessionHasNoErrors();
        $returnRequest->refresh();
        $this->assertEquals('customer_shipped', $returnRequest->status);

        // 3. Admin receives the goods
        $this->actingAs($this->admin)->post(route('admin.returns.receive', $returnRequest->id));
        $returnRequest->refresh();
        $this->assertEquals('received', $returnRequest->status);

        // 4. Admin inspects
        $this->actingAs($this->admin)->post(route('admin.returns.inspect', $returnRequest->id), [
            'inspection_result' => 'passed',
            'items' => [
                [
                    'id' => $returnItem->id,
                    'refund_amount' => 90000,
                    'restock' => true,
                ]
            ]
        ]);
        $returnRequest->refresh();
        $this->assertEquals('inspected', $returnRequest->status);

        // 5. Admin processes the refund
        $this->actingAs($this->admin)->post(route('admin.returns.refund', $returnRequest->id));
        $returnRequest->refresh();
        $this->assertEquals('refund_processed', $returnRequest->status);

        // 6. Admin completes
        $this->actingAs($this->admin)->post(route('admin.returns.complete', $returnRequest->id));
        $returnRequest->refresh();
        $this->assertEquals('completed', $returnRequest->status);
    }

    public function test_active_return_is_hidden_from_completed_tab(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-TEST-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'submitted',
            'reason' => 'Defective product',
            'evidence_image_1' => 'returns/test.jpg',
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        // When requesting completed tab
        $response = $this->actingAs($this->user)->get(route('orders.index', ['status' => 'completed']));
        $response->assertStatus(200);
        
        // Assert the order is NOT in the completed list because it has an active return
        $orders = $response->viewData('page')['props']['orders']['data'];
        $this->assertCount(0, $orders);

        // When requesting returned tab
        $response2 = $this->actingAs($this->user)->get(route('orders.index', ['status' => 'returned']));
        $response2->assertStatus(200);
        
        $returnedOrders = $response2->viewData('page')['props']['orders']['data'];
        $this->assertCount(1, $returnedOrders);
        $this->assertEquals($this->order->id, $returnedOrders[0]['id']);
    }

    public function test_free_shipping_voucher_does_not_reduce_item_refund(): void
    {
        // Order used a free_shipping voucher: discount_amount (15000) reduced SHIPPING, not items.
        $order = Order::create([
            'order_number' => 'ORD-TEST-FREESHIP',
            'user_id' => $this->user->id,
            'status' => 'completed',
            'completed_at' => now(),
            'fulfillment_type' => 'delivery',
            'subtotal' => 200000,
            'shipping_cost' => 15000,
            'discount_amount' => 15000,
            'discount_on_shipping' => true,
            'total_amount' => 200000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => $this->product->name,
            'variant_name_snapshot' => $this->variant->name,
            'quantity' => 1,
            'unit_price' => 100000,
            'weight_gram' => 500,
            'subtotal' => 100000,
        ]);

        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-TEST-FREESHIP',
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'status' => 'received',
            'reason' => 'Defective product',
            'evidence_image_1' => 'returns/test.jpg',
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.returns.show', $returnRequest->id));
        $response->assertStatus(200);

        $items = $response->viewData('page')['props']['returnRequest']['items'];
        $this->assertCount(1, $items);
        // Ratio must be 0 because the voucher's discount_amount only reduced shipping.
        $this->assertEquals(100000, $items[0]['suggested_refund']);
    }

    public function test_percentage_voucher_still_prorates_item_refund(): void
    {
        // Counter-case: a NON-free-shipping voucher must still reduce item refund proportionally.
        $order = Order::create([
            'order_number' => 'ORD-TEST-PCTVOUCHER',
            'user_id' => $this->user->id,
            'status' => 'completed',
            'completed_at' => now(),
            'fulfillment_type' => 'delivery',
            'subtotal' => 200000,
            'shipping_cost' => 15000,
            'discount_amount' => 20000,
            'discount_on_shipping' => false,
            'total_amount' => 195000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => $this->product->name,
            'variant_name_snapshot' => $this->variant->name,
            'quantity' => 1,
            'unit_price' => 100000,
            'weight_gram' => 500,
            'subtotal' => 100000,
        ]);

        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-TEST-PCTVOUCHER',
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'status' => 'received',
            'reason' => 'Defective product',
            'evidence_image_1' => 'returns/test.jpg',
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.returns.show', $returnRequest->id));
        $response->assertStatus(200);

        $items = $response->viewData('page')['props']['returnRequest']['items'];
        $this->assertCount(1, $items);
        // discount_ratio = 20000 / 200000 = 0.1 -> refund = 100000 * (1 - 0.1) = 90000
        $this->assertEquals(90000, $items[0]['suggested_refund']);
    }
}
