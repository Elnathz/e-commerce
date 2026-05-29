<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    /**
     * Render checkout page
     */
    public function index(Request $request)
    {
        $method = $request->query('method', 'delivery');
        $itemIds = $request->query('items'); // comma-separated

        if (!in_array($method, ['delivery', 'pickup'])) {
            return redirect('/cart')->with('error', 'Metode pengiriman tidak valid.');
        }

        $user = Auth::user();

        // Ensure user has items in cart
        $cart = $user->cart;
        if (!$cart || $cart->items()->count() === 0) {
            return redirect('/cart')->with('error', 'Keranjang belanja Anda kosong.');
        }

        // Build query with correct relations: CartItem -> variant -> product
        $query = $cart->items()->with(['variant.product.images', 'variant.images']);

        if ($itemIds) {
            $idsArray = array_map('intval', explode(',', $itemIds));
            $query->whereIn('id', $idsArray);
        }

        $cartItems = $query->get();

        if ($cartItems->count() === 0) {
            return redirect('/cart')->with('error', 'Tidak ada item yang dipilih untuk checkout.');
        }

        // Get addresses for delivery
        $addresses = $method === 'delivery' ? $user->addresses()->get() : [];

        // Map cart items using correct relations
        $mappedItems = $cartItems->map(function ($item) {
            $variant = $item->variant;
            $product = $variant->product;

            // Get image: variant images first, fallback to product primary image
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
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'image' => $image,
                'quantity' => $item->quantity,
                'current_price' => $variant->price,
                'weight' => $variant->weight_gram ?? $product->weight_gram,
                'available_stock' => $variant->stock - $variant->reserved_stock,
            ];
        });

        // Calculate subtotal and total weight
        $subtotal = $mappedItems->sum(fn($item) => $item['current_price'] * $item['quantity']);
        $totalWeight = $mappedItems->sum(fn($item) => $item['weight'] * $item['quantity']);

        return Inertia::render('Storefront/Checkout', [
            'method' => $method,
            'addresses' => $addresses,
            'cartItems' => $mappedItems,
            'subtotal' => $subtotal,
            'totalWeight' => $totalWeight,
            'rajaongkirKeyExists' => !empty(config('services.rajaongkir.key')),
            'itemIds' => $itemIds
        ]);
    }

    /**
     * Process checkout and create order (FR007 + FR009)
     * - Validates stock availability
     * - Reserves stock atomically
     * - Creates order with pending status
     */
    public function store(Request $request)
    {
        $request->validate([
            'method' => 'required|in:delivery,pickup',
            'address_id' => 'required_if:method,delivery|nullable|exists:user_addresses,id',
            'courier' => 'required_if:method,delivery|nullable',
            'shipping_service' => 'required_if:method,delivery|nullable',
            'shipping_cost' => 'required_if:method,delivery|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'item_ids' => 'nullable|string', // Comma-separated cart_item IDs
        ]);

        $user = Auth::user();
        $cart = $user->cart;

        if (!$cart || $cart->items()->count() === 0) {
            return redirect('/cart')->with('error', 'Keranjang belanja Anda kosong.');
        }

        // Build query with correct relations
        $query = $cart->items()->with(['variant.product.images', 'variant.images']);

        if ($request->item_ids) {
            $idsArray = array_map('intval', explode(',', $request->item_ids));
            $query->whereIn('id', $idsArray);
        }

        $cartItems = $query->get();

        if ($cartItems->count() === 0) {
            return redirect('/cart')->with('error', 'Tidak ada item yang dipilih untuk diproses.');
        }

        // === WRAP EVERYTHING IN A TRANSACTION ===
        try {
            $order = DB::transaction(function () use ($request, $user, $cart, $cartItems) {

                // --- FR007: Validate stock availability ---
                $outOfStockItems = [];
                foreach ($cartItems as $item) {
                    $variant = $item->variant;
                    $availableStock = $variant->stock - $variant->reserved_stock;

                    if ($availableStock < $item->quantity) {
                        $outOfStockItems[] = $variant->product->name . ' (' . $variant->name . ')'
                            . ' — tersedia: ' . $availableStock . ', diminta: ' . $item->quantity;
                    }
                }

                if (!empty($outOfStockItems)) {
                    throw new \Exception('Stok tidak mencukupi untuk: ' . implode(', ', $outOfStockItems));
                }

                // --- FR009: Reserve stock atomically ---
                foreach ($cartItems as $item) {
                    $affected = ProductVariant::where('id', $item->variant->id)
                        ->whereRaw('(stock - reserved_stock) >= ?', [$item->quantity])
                        ->update([
                            'reserved_stock' => DB::raw('reserved_stock + ' . (int) $item->quantity)
                        ]);

                    if ($affected === 0) {
                        // Race condition: stock was taken between validation and reservation
                        throw new \Exception(
                            'Stok untuk "' . $item->variant->product->name . ' (' . $item->variant->name . ')" baru saja habis. Silakan coba lagi.'
                        );
                    }
                }

                // --- Calculate totals ---
                $subtotal = 0;
                foreach ($cartItems as $item) {
                    $subtotal += $item->variant->price * $item->quantity;
                }

                $shippingCost = $request->method === 'delivery' ? (float) $request->shipping_cost : 0;
                $totalAmount = $subtotal + $shippingCost;

                // --- Build address snapshot ---
                $addressSnapshot = null;
                if ($request->method === 'delivery') {
                    $address = $user->addresses()->findOrFail($request->address_id);
                    $addressSnapshot = [
                        'recipient_name' => $address->recipient_name,
                        'phone' => $address->phone,
                        'address_detail' => $address->address_detail,
                        'province' => $address->province,
                        'city' => $address->city,
                        'district' => $address->district,
                        'postal_code' => $address->postal_code,
                    ];
                }

                // --- Create Order (proper number format) ---
                $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'fulfillment_type' => $request->method,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'discount_amount' => 0,
                    'total_amount' => $totalAmount,
                    'shipping_address_snapshot' => $addressSnapshot,
                    'notes' => $request->notes,
                    'courier' => $request->method === 'delivery' ? $request->courier : null,
                    'expired_at' => now()->addHours(24), // FR008: 24-hour payment window
                ]);

                // --- Create Order Items ---
                $cartItemIdsToDelete = [];
                foreach ($cartItems as $item) {
                    $variant = $item->variant;
                    $product = $variant->product;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_variant_id' => $variant->id,
                        'product_name_snapshot' => $product->name,
                        'variant_name_snapshot' => $variant->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $variant->price,
                        'weight_gram' => $variant->weight_gram ?? $product->weight_gram,
                        'subtotal' => $variant->price * $item->quantity,
                    ]);
                    $cartItemIdsToDelete[] = $item->id;
                }

                // --- Clear checked-out items from cart ---
                $cart->items()->whereIn('id', $cartItemIdsToDelete)->delete();

                if ($cart->items()->count() === 0) {
                    $cart->delete();
                    session()->flash('cart_count', 0);
                } else {
                    session()->flash('cart_count', $cart->items()->count());
                }

                return $order;
            });

            return redirect()
                ->route('checkout.success', $order->order_number)
                ->with('success', 'Pesanan berhasil dibuat!');

        } catch (\Exception $e) {
            return redirect('/cart')->with('error', $e->getMessage());
        }
    }

    /**
     * Show success page
     */
    public function success($order_number)
    {
        $order = Order::with('items')->where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return Inertia::render('Storefront/CheckoutSuccess', [
            'order' => $order
        ]);
    }

    /**
     * Calculate shipping cost based on destination and weight
     */
    public function calculateShipping(Request $request, \App\Services\Shipping\ShippingService $shippingService)
    {
        $request->validate([
            'destination_city' => 'required|exists:cities,id',
            'weight' => 'required|numeric|min:1',
            'courier' => 'required|in:jne,pos,tiki,internal'
        ]);

        $destinationCity = \App\Models\City::findOrFail($request->destination_city);
        
        // Find internal Semarang City ID dynamically
        $originCity = \App\Models\City::where('name', 'like', '%semarang%')->first();
        
        // If courier is internal, return the flat rate for Semarang
        if ($request->courier === 'internal') {
            if ($originCity && $destinationCity->id === $originCity->id) {
                return response()->json([
                    'success' => true,
                    'results' => [
                        [
                            'code' => 'internal',
                            'name' => 'Kurir Internal MegaMart',
                            'costs' => [
                                [
                                    'service' => 'Same Day',
                                    'description' => 'Pengiriman Langsung Area Semarang',
                                    'cost' => [
                                        [
                                            'value' => 15000,
                                            'etd' => 'Hari ini',
                                            'note' => ''
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurir internal hanya tersedia untuk pengiriman dalam kota Semarang.'
                ], 400);
            }
        }

        $baseUrl = config('services.rajaongkir.base_url', 'https://api.rajaongkir.com/starter');
        $isKomerce = str_contains($baseUrl, 'komerce.id');

        // Resolve provider-specific origin and destination IDs
        // Use provider-specific ID first, fallback to rajaongkir_city_id
        if ($originCity) {
            $providerOriginId = ($isKomerce && $originCity->komerce_city_id) 
                ? $originCity->komerce_city_id 
                : $originCity->rajaongkir_city_id;
        } else {
            $providerOriginId = $isKomerce ? 560 : 399; // Semarang fallback
        }

        $providerDestinationId = ($isKomerce && $destinationCity->komerce_city_id)
            ? $destinationCity->komerce_city_id
            : $destinationCity->rajaongkir_city_id;

        if (!$providerOriginId || !$providerDestinationId) {
            return response()->json([
                'success' => false,
                'message' => 'Wilayah asal atau tujuan tidak memiliki ID yang valid. Hubungi admin.'
            ], 400);
        }

        $courier = $request->courier === 'internal' ? 'jne' : $request->courier;

        // Use ShippingService (with internal caching)
        $results = $shippingService->getShippingCosts(
            (int)$providerOriginId,
            (int)$providerDestinationId,
            (int)$request->weight,
            $courier
        );

        if (!empty($results)) {
            return response()->json([
                'success' => true,
                'results' => $results
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data ongkos kirim. Pastikan tujuan dan berat valid.'
        ], 500);
    }
}

