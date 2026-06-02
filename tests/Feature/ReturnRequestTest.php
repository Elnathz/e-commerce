<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
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
            'is_partial' => false,
            'images' => [
                UploadedFile::fake()->image('evidence.jpg'),
            ],
        ]);

        $returnRequest = ReturnRequest::first();
        $this->assertNotNull($returnRequest);
        $this->assertEquals('submitted', $returnRequest->status);
        $this->assertEquals('Defective product', $returnRequest->reason);
        $response->assertRedirect(route('returns.show', $returnRequest->return_number));
    }

    public function test_admin_can_approve_return_request_within_limits(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-TEST-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'submitted',
            'reason' => 'Defective product',
            'evidence_image_1' => 'returns/test.jpg',
        ]);

        // Approve with valid refund amount (less than or equal to total_amount)
        $response = $this->actingAs($this->admin)->post(route('admin.returns.approve', $returnRequest->id), [
            'refund_amount' => 50000,
        ]);

        $response->assertSessionHasNoErrors();
        $returnRequest->refresh();
        $this->assertEquals('approved', $returnRequest->status);
        $this->assertEquals(50000, $returnRequest->refund_amount);
    }

    public function test_admin_cannot_approve_return_request_exceeding_limits(): void
    {
        $returnRequest = ReturnRequest::create([
            'return_number' => 'RET-TEST-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'submitted',
            'reason' => 'Defective product',
            'evidence_image_1' => 'returns/test.jpg',
        ]);

        // Attempt to approve with refund amount exceeding the order total_amount (100000)
        $response = $this->actingAs($this->admin)->post(route('admin.returns.approve', $returnRequest->id), [
            'refund_amount' => 150000,
        ]);

        $response->assertSessionHasErrors(['refund_amount']);
        $returnRequest->refresh();
        $this->assertEquals('submitted', $returnRequest->status); // Status remains unchanged
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

        // 1. Admin Approve
        $this->actingAs($this->admin)->post(route('admin.returns.approve', $returnRequest->id), [
            'refund_amount' => 90000,
        ]);
        $returnRequest->refresh();
        $this->assertEquals('approved', $returnRequest->status);

        // 2. Customer inputs tracking info
        $this->actingAs($this->user)->post(route('returns.tracking', $returnRequest->return_number), [
            'return_courier' => 'JNE',
            'return_tracking_number' => 'JNETEST123',
        ]);
        $returnRequest->refresh();
        $this->assertEquals('returned', $returnRequest->status);

        // 3. Admin receives the goods
        $this->actingAs($this->admin)->post(route('admin.returns.receive', $returnRequest->id));
        $returnRequest->refresh();
        $this->assertEquals('received', $returnRequest->status);

        // 4. Admin processes the refund
        $this->actingAs($this->admin)->post(route('admin.returns.refund', $returnRequest->id));
        $returnRequest->refresh();
        $this->assertEquals('refund_processed', $returnRequest->status);

        // Order status should be refunded
        $this->order->refresh();
        $this->assertEquals('refunded', $this->order->status);
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
}
