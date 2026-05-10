<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $cartService = app(\App\Services\CartService::class);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'globalCategories' => fn () => \App\Models\Category::with('children')
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'image_path']),
            'cartCount' => fn () => $cartService->getCartCount($request),
            'cartItems' => fn () => $this->getCartItems($cartService, $request),
            'flash' => [
                'cart_success' => fn () => $request->session()->get('cart_success'),
                'cart_error' => fn () => $request->session()->get('cart_error'),
            ],
        ];
    }

    /**
     * Build cart items array for shared props (used by CartDrawer).
     */
    private function getCartItems(\App\Services\CartService $cartService, Request $request): array
    {
        $cart = $cartService->getCart($request);
        if (!$cart) return [];

        return $cart->items->map(function ($item) {
            $variant = $item->variant;
            $product = $variant->product;
            $availableStock = $variant->stock - $variant->reserved_stock;

            $image = null;
            if ($variant->images && $variant->images->isNotEmpty()) {
                $image = '/storage/' . $variant->images->first()->image_path;
            } elseif ($product->images && $product->images->isNotEmpty()) {
                $primaryImage = $product->images->where('is_primary', true)->first()
                    ?? $product->images->first();
                $image = '/storage/' . $primaryImage->image_path;
            }

            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price_snapshot' => (float) $item->unit_price_snapshot,
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'current_price' => (float) $variant->price,
                'available_stock' => $availableStock,
                'is_active' => $variant->is_active && $product->is_active,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'image' => $image,
                'price_changed' => (float) $item->unit_price_snapshot !== (float) $variant->price,
                'subtotal' => (float) $variant->price * $item->quantity,
            ];
        })->toArray();
    }
}
