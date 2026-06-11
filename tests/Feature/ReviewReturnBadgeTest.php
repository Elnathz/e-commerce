<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * §1c / tracker #44 — review return-badge. `OrderItem::reviewReturnBadge()`
 * resolves the most recently *resolved* return outcome for an item
 * (`rejected` -> 'requested', `refund_processed`/`completed` -> 'refunded'),
 * with `cancelled` and still-active returns producing no badge at all.
 *
 * Storefront (Admin/Reviews/Index counterpart covered too) consumes the same
 * helper but renders different labels per surface — only the raw outcome and
 * the return id are asserted here, per CLAUDE.md ("outcome testing over
 * response testing").
 */
class ReviewReturnBadgeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private Product $product;
    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);

        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-' . uniqid(),
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-' . uniqid(),
            'base_price' => 50000,
            'weight_gram' => 100,
            'description' => 'Test description',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'TEST-BADGE-01',
            'name' => 'Default',
            'variant_type' => 'default',
            'price' => 50000,
            'stock' => 10,
            'reserved_stock' => 0,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-BADGE-' . uniqid(),
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_type' => 'delivery',
            'subtotal' => 50000,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'total_amount' => 50000,
        ]);

        $this->orderItem = $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $this->product->name,
            'variant_name_snapshot' => $variant->name,
            'quantity' => 1,
            'unit_price' => 50000,
            'weight_gram' => 100,
            'subtotal' => 50000,
        ]);
    }

    private function createReturn(string $returnNumber, string $status, ?string $updatedAt = null): ReturnRequest
    {
        $return = ReturnRequest::create([
            'return_number' => $returnNumber,
            'order_id' => $this->orderItem->order_id,
            'user_id' => $this->user->id,
            'status' => $status,
            'reason' => 'Test reason',
            'evidence_image_1' => 'test.jpg',
        ]);

        ReturnRequestItem::create([
            'return_request_id' => $return->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'reason_code' => 'defective',
            'condition' => 'opened',
        ]);

        if ($updatedAt) {
            DB::table('return_requests')->where('id', $return->id)->update(['updated_at' => $updatedAt]);
        }

        return $return->fresh();
    }

    private function createReview(): Review
    {
        return Review::create([
            'order_item_id' => $this->orderItem->id,
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Test review',
            'is_published' => true,
        ]);
    }

    private function loadBadge(): ?array
    {
        $orderItem = OrderItem::with('returnRequestItems.returnRequest')->find($this->orderItem->id);

        return $orderItem->reviewReturnBadge();
    }

    public function test_no_return_yields_no_badge()
    {
        $this->assertNull($this->loadBadge());
    }

    public function test_cancelled_return_yields_no_badge()
    {
        $this->createReturn('RET-CANCELLED', 'cancelled');

        $this->assertNull($this->loadBadge());
    }

    public function test_active_return_yields_no_badge()
    {
        $this->createReturn('RET-ACTIVE', 'submitted');

        $this->assertNull($this->loadBadge());
    }

    public function test_rejected_return_yields_requested_badge()
    {
        $return = $this->createReturn('RET-REJECTED', 'rejected');

        $badge = $this->loadBadge();

        $this->assertSame('requested', $badge['outcome']);
        $this->assertSame($return->id, $badge['return_request_id']);
    }

    public function test_refund_processed_return_yields_refunded_badge()
    {
        $this->createReturn('RET-REFUNDED', 'refund_processed');

        $badge = $this->loadBadge();

        $this->assertSame('refunded', $badge['outcome']);
    }

    public function test_completed_return_yields_refunded_badge()
    {
        $this->createReturn('RET-COMPLETED', 'completed');

        $badge = $this->loadBadge();

        $this->assertSame('refunded', $badge['outcome']);
    }

    public function test_multiple_returns_use_the_most_recently_resolved_outcome()
    {
        // Older rejected return, then a newer refund_processed return for the same item.
        $this->createReturn('RET-OLD-REJECTED', 'rejected', '2026-01-01 10:00:00');
        $this->createReturn('RET-NEW-REFUNDED', 'refund_processed', '2026-06-01 10:00:00');

        $badge = $this->loadBadge();

        $this->assertSame('refunded', $badge['outcome']);
    }

    public function test_a_later_cancelled_return_does_not_mask_an_earlier_resolved_return()
    {
        $this->createReturn('RET-REJECTED', 'rejected', '2026-01-01 10:00:00');
        $this->createReturn('RET-CANCELLED', 'cancelled', '2026-06-01 10:00:00');

        $badge = $this->loadBadge();

        $this->assertSame('requested', $badge['outcome']);
    }

    public function test_storefront_product_page_exposes_return_badge_for_published_review()
    {
        $this->createReturn('RET-REJECTED', 'rejected');
        $this->createReview();

        $response = $this->get(route('products.show', $this->product->slug));

        $response->assertOk();
        $reviews = $response->viewData('page')['props']['product']['reviews'];

        $this->assertCount(1, $reviews);
        $this->assertSame('requested', $reviews[0]['return_badge']);
    }

    public function test_storefront_product_page_omits_badge_for_cancelled_return()
    {
        $this->createReturn('RET-CANCELLED', 'cancelled');
        $this->createReview();

        $response = $this->get(route('products.show', $this->product->slug));

        $response->assertOk();
        $reviews = $response->viewData('page')['props']['product']['reviews'];

        $this->assertCount(1, $reviews);
        $this->assertNull($reviews[0]['return_badge']);
    }

    public function test_admin_reviews_index_exposes_differentiated_badge_and_return_link()
    {
        $return = $this->createReturn('RET-REJECTED', 'rejected');
        $this->createReview();

        $response = $this->actingAs($this->admin)->get(route('admin.reviews.index'));

        $response->assertOk();
        $reviews = $response->viewData('page')['props']['reviews']['data'];

        $this->assertCount(1, $reviews);
        $this->assertSame('requested', $reviews[0]['return_badge']);
        $this->assertSame($return->id, $reviews[0]['return_request_id']);
    }

    public function test_admin_reviews_index_marks_refund_processed_as_refunded()
    {
        $this->createReturn('RET-REFUNDED', 'refund_processed');
        $this->createReview();

        $response = $this->actingAs($this->admin)->get(route('admin.reviews.index'));

        $response->assertOk();
        $reviews = $response->viewData('page')['props']['reviews']['data'];

        $this->assertSame('refunded', $reviews[0]['return_badge']);
    }
}
