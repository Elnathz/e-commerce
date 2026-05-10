<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * GET /cart — Show cart page.
     */
    public function index(Request $request)
    {
        $cart = $this->cartService->getCart($request);

        $items = [];
        if ($cart) {
            $items = $cart->items->map(function ($item) {
                $variant = $item->variant;
                $product = $variant->product;
                $availableStock = $variant->stock - $variant->reserved_stock;

                // Get image: use variant's first image, fallback to product primary image
                $image = null;
                if ($variant->images && $variant->images->isNotEmpty()) {
                    $image = '/storage/' . $variant->images->first()->image_path;
                } elseif ($product->images && $product->images->isNotEmpty()) {
                    $primaryImage = $product->images->where('is_primary', true)->first()
                        ?? $product->images->first();
                    $image = '/storage/' . $primaryImage->image_path;
                }

                $priceChanged = (float) $item->unit_price_snapshot !== (float) $variant->price;

                return [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'unit_price_snapshot' => (float) $item->unit_price_snapshot,
                    'variant_id' => $variant->id,
                    'variant_name' => $variant->name,
                    'variant_type' => $variant->variant_type,
                    'current_price' => (float) $variant->price,
                    'available_stock' => $availableStock,
                    'is_active' => $variant->is_active && $product->is_active,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'image' => $image,
                    'price_changed' => $priceChanged,
                    'subtotal' => (float) $variant->price * $item->quantity,
                ];
            });
        }

        return Inertia::render('Storefront/Cart', [
            'cartItems' => $items,
        ]);
    }

    /**
     * POST /cart/items — Add item to cart.
     */
    public function addItem(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'direct_checkout' => 'nullable|boolean',
        ]);

        try {
            $item = $this->cartService->addItem(
                $request,
                $request->integer('product_variant_id'),
                $request->integer('quantity')
            );

            if ($request->boolean('direct_checkout')) {
                return redirect()->route('cart.index', ['direct_checkout_item' => $item->id]);
            }

            return back()->with('cart_success', 'Produk berhasil ditambahkan ke keranjang!');
        } catch (\Exception $e) {
            return back()->with('cart_error', $e->getMessage());
        }
    }

    /**
     * PATCH /cart/items/{cartItem} — Update quantity.
     */
    public function updateItem(Request $request, int $cartItemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $item = \App\Models\CartItem::findOrFail($cartItemId);

        // Verify ownership
        $cart = $this->cartService->getCart($request);
        if (!$cart || $item->cart_id !== $cart->id) {
            abort(403);
        }

        try {
            $this->cartService->updateItemQuantity($item, $request->integer('quantity'));
            return back()->with('cart_success', 'Jumlah berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('cart_error', $e->getMessage());
        }
    }

    /**
     * DELETE /cart/items/{cartItem} — Remove item.
     */
    public function removeItem(Request $request, int $cartItemId)
    {
        $item = \App\Models\CartItem::findOrFail($cartItemId);

        // Verify ownership
        $cart = $this->cartService->getCart($request);
        if (!$cart || $item->cart_id !== $cart->id) {
            abort(403);
        }

        $this->cartService->removeItem($item);
        return back()->with('cart_success', 'Item berhasil dihapus dari keranjang.');
    }
}
