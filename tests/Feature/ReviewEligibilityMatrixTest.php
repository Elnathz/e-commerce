<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewEligibilityMatrixTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $order;
    private $orderItem;
    private $orderItem2; // Untuk tes item dalam order sama yang tidak diretur

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();

        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category'
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'base_price' => 50000,
            'weight_gram' => 100,
            'description' => 'Test description'
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-01',
            'name' => 'Default',
            'variant_type' => 'default',
            'price' => 50000,
            'stock' => 10,
            'reserved_stock' => 2,
        ]);

        $this->order = Order::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-REV-123',
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 100000,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'total_amount' => 100000,
        ]);

        $this->orderItem = $this->order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'variant_name_snapshot' => $variant->name,
            'quantity' => 1,
            'unit_price' => 50000,
            'weight_gram' => 100,
            'subtotal' => 50000,
        ]);

        $this->orderItem2 = $this->order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name . ' 2',
            'variant_name_snapshot' => $variant->name,
            'quantity' => 1,
            'unit_price' => 50000,
            'weight_gram' => 100,
            'subtotal' => 50000,
        ]);
    }

    private function postReviewRequest(int $orderItemId)
    {
        return $this->actingAs($this->user)->post(route('reviews.store', $this->order->order_number), [
            'order_item_id' => $orderItemId,
            'rating' => 5,
            'comment' => 'Test review',
        ]);
    }

    public function test_completed_order_can_be_reviewed()
    {
        $response = $this->postReviewRequest($this->orderItem->id);
        
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('reviews', [
            'order_item_id' => $this->orderItem->id,
        ]);
    }

    public function test_refunded_order_cannot_be_reviewed()
    {
        $this->order->update(['status' => 'refunded']);
        
        $response = $this->postReviewRequest($this->orderItem->id);
        
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('reviews', [
            'order_item_id' => $this->orderItem->id,
        ]);
    }

    public function test_cancelled_order_cannot_be_reviewed()
    {
        $this->order->update(['status' => 'cancelled']);
        
        $response = $this->postReviewRequest($this->orderItem->id);
        
        $response->assertSessionHas('error');
    }

    public function test_active_return_item_cannot_be_reviewed_but_other_item_can()
    {
        // Buat return request untuk orderItem1
        $return = ReturnRequest::create([
            'return_number' => 'RET-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'submitted',
            'reason' => 'Defective',
            'evidence_image_1' => 'test.jpg',
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $return->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        // Coba review item yang diretur (Gagal)
        $response1 = $this->postReviewRequest($this->orderItem->id);
        $response1->assertSessionHas('error', 'Produk ini sedang dalam proses retur dan belum dapat diberi ulasan.');

        // Coba review item lain di order yang sama yang TIDAK diretur (Berhasil)
        $response2 = $this->postReviewRequest($this->orderItem2->id);
        $response2->assertSessionHas('success');
    }

    public function test_rejected_return_item_can_be_reviewed()
    {
        $return = ReturnRequest::create([
            'return_number' => 'RET-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'rejected', // Return ditolak
            'reason' => 'Defective',
            'evidence_image_1' => 'test.jpg',
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $return->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        $response = $this->postReviewRequest($this->orderItem->id);
        $response->assertSessionHas('success');
    }

    public function test_refund_processed_item_can_be_reviewed()
    {
        $return = ReturnRequest::create([
            'return_number' => 'RET-123',
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'refund_processed', // Return selesai
            'reason' => 'Defective',
            'evidence_image_1' => 'test.jpg',
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $return->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        $response = $this->postReviewRequest($this->orderItem->id);
        $response->assertSessionHas('success');
    }
}
