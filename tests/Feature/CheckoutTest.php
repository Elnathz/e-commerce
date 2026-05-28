<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $category;
    private $product;
    private $variant;
    private $cart;
    private $cartItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
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
    }

    public function test_checkout_index_requires_authentication(): void
    {
        $response = $this->get(route('checkout.index'));
        $response->assertRedirect('/login');
    }

    public function test_checkout_index_redirects_to_cart_if_cart_is_empty(): void
    {
        $response = $this->actingAs($this->user)->get(route('checkout.index'));
        $response->assertRedirect('/cart');
        $response->assertSessionHas('error', 'Keranjang belanja Anda kosong.');
    }

    public function test_checkout_index_loads_successfully_with_items(): void
    {
        // Create cart for user
        $this->cart = Cart::create([
            'user_id' => $this->user->id,
        ]);

        $this->cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'unit_price_snapshot' => 90000,
        ]);

        $response = $this->actingAs($this->user)->get(route('checkout.index', [
            'method' => 'pickup',
            'items' => $this->cartItem->id,
        ]));

        $response->assertOk();
    }

    public function test_checkout_store_creates_order_successfully(): void
    {
        $this->cart = Cart::create([
            'user_id' => $this->user->id,
        ]);

        $this->cartItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'unit_price_snapshot' => 90000,
        ]);

        $response = $this->actingAs($this->user)->post(route('checkout.store'), [
            'method' => 'pickup',
            'notes' => 'Tolong dipack bubble wrap',
            'item_ids' => (string) $this->cartItem->id,
        ]);

        // Check redirect to success page
        $order = Order::first();
        $this->assertNotNull($order);
        
        $response->assertRedirect(route('checkout.success', $order->order_number));

        // Check stock was reserved
        $this->variant->refresh();
        $this->assertEquals(2, $this->variant->reserved_stock);

        // Check order details
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('pickup', $order->fulfillment_type);
        $this->assertEquals(180000, $order->subtotal);
        $this->assertEquals(180000, $order->total_amount);

        // Check order items
        $orderItem = OrderItem::first();
        $this->assertNotNull($orderItem);
        $this->assertEquals($this->variant->id, $orderItem->product_variant_id);
        $this->assertEquals(2, $orderItem->quantity);

        // Check cart item was removed
        $this->assertNull(CartItem::find($this->cartItem->id));
    }
}
