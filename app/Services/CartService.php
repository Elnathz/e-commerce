<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Maximum unique items allowed for guest carts.
     */
    public const GUEST_MAX_ITEMS = 10;

    /**
     * Get or create cart for the current user/session.
     */
    public function getOrCreateCart(Request $request): Cart
    {
        if (Auth::check()) {
            return Cart::query()
                ->firstOrCreate(
                    ['user_id' => Auth::id()],
                    ['session_id' => null]
                );
        }

        $sessionId = $request->session()->getId();

        return Cart::query()
            ->firstOrCreate(
                ['session_id' => $sessionId, 'user_id' => null],
            );
    }

    /**
     * Get the current cart (without creating one).
     */
    public function getCart(Request $request): ?Cart
    {
        if (Auth::check()) {
            return Cart::query()
                ->where('user_id', Auth::id())
                ->with(['items.variant.product.images', 'items.variant.images'])
                ->first();
        }

        $sessionId = $request->session()->getId();

        return Cart::query()
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->with(['items.variant.product.images', 'items.variant.images'])
            ->first();
    }

    /**
     * Add item to cart.
     *
     * @throws \Exception
     */
    public function addItem(Request $request, int $variantId, int $quantity): CartItem
    {
        $variant = ProductVariant::query()
            ->where('id', $variantId)
            ->where('is_active', true)
            ->firstOrFail();

        $availableStock = $variant->stock - $variant->reserved_stock;

        $cart = $this->getOrCreateCart($request);

        // Check guest limit
        if (!Auth::check()) {
            $existingItem = $cart->items()->where('product_variant_id', $variantId)->first();
            if (!$existingItem && $cart->items()->count() >= self::GUEST_MAX_ITEMS) {
                throw new \Exception('Keranjang tamu maksimal ' . self::GUEST_MAX_ITEMS . ' item. Silakan login untuk melanjutkan belanja.');
            }
        }

        // Check if item already exists in cart
        $existingItem = $cart->items()->where('product_variant_id', $variantId)->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;

            if ($newQuantity > $availableStock) {
                throw new \Exception("Stok tidak mencukupi. Tersedia: {$availableStock}, di keranjang: {$existingItem->quantity}.");
            }

            $existingItem->update(['quantity' => $newQuantity]);
            return $existingItem->fresh();
        }

        // New item
        if ($quantity > $availableStock) {
            throw new \Exception("Stok tidak mencukupi. Tersedia: {$availableStock}.");
        }

        return $cart->items()->create([
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price_snapshot' => $variant->effectivePrice(),
        ]);
    }

    /**
     * Update item quantity.
     *
     * @throws \Exception
     */
    public function updateItemQuantity(CartItem $item, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw new \Exception('Jumlah minimal 1.');
        }

        $variant = $item->variant;
        $availableStock = $variant->stock - $variant->reserved_stock;

        if ($quantity > $availableStock) {
            throw new \Exception("Stok tidak mencukupi. Tersedia: {$availableStock}.");
        }

        $item->update(['quantity' => $quantity]);
        return $item->fresh();
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Merge guest cart into user cart after login.
     */
    public function mergeCarts(string $sessionId, int $userId): void
    {
        $guestCart = Cart::query()
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->with('items')
            ->first();

        if (!$guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $userCart = Cart::query()->firstOrCreate(
            ['user_id' => $userId],
            ['session_id' => null]
        );

        DB::transaction(function () use ($guestCart, $userCart) {
            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->first();

                if ($existingItem) {
                    // Merge: add quantities
                    $variant = $guestItem->variant;
                    $availableStock = $variant->stock - $variant->reserved_stock;
                    $mergedQty = min(
                        $existingItem->quantity + $guestItem->quantity,
                        $availableStock
                    );

                    $existingItem->update(['quantity' => max($mergedQty, 1)]);
                } else {
                    // Move item to user cart
                    $guestItem->update(['cart_id' => $userCart->id]);
                }
            }

            // Delete guest cart
            $guestCart->delete();
        });
    }

    /**
     * Get cart item count for navbar badge.
     */
    public function getCartCount(Request $request): int
    {
        $cart = $this->getCart($request);
        return $cart ? $cart->items->sum('quantity') : 0;
    }
}
