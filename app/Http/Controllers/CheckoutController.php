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
use App\Traits\DispatchesAtomicNotification;
use App\Notifications\OrderStatusNotification;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    use DispatchesAtomicNotification;
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
        // variant.product is eager-loaded so priceInfo()/effectivePrice() (which read
        // $variant->product->discount_percent) don't trigger N+1 queries per item.
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
                // Effective price (manual discount aware) — must match what store()'s
                // placement guard resolves via PricingService::effectivePrice(), since
                // this value is round-tripped back as consented_prices on submit.
                'current_price' => $variant->effectivePrice(),
                'price_info' => $variant->priceInfo(),
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
            'consented_prices' => 'nullable|array', // cart_item_id => price customer saw (placement guard)
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

                // --- Resolve effective price per item + placement guard (price_changed) ---
                // Backward-compatible: guard only runs when caller sends consented_prices.
                $pricing = app(\App\Services\PricingService::class);
                $priceChanged = [];
                $effectiveById = [];
                $consentMap = $request->input('consented_prices', []);
                foreach ($cartItems as $item) {
                    $eff = $pricing->effectivePrice($item->variant);
                    $effectiveById[$item->id] = $eff;
                    if ($request->filled('consented_prices')) {
                        if (!array_key_exists((int) $item->id, $consentMap) || (float) $consentMap[(int) $item->id] !== (float) $eff) {
                            $priceChanged[] = $item->variant->product->name . ' (' . $item->variant->name . ')';
                        }
                    }
                }
                if (!empty($priceChanged)) {
                    throw new \Exception('Harga berubah untuk: ' . implode(', ', $priceChanged) . '. Silakan tinjau ulang keranjang.');
                }

                // --- Calculate totals ---
                $subtotal = 0;
                foreach ($cartItems as $item) {
                    $subtotal += $effectiveById[$item->id] * $item->quantity;
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
                    'shipping_service' => $request->method === 'delivery' ? $request->shipping_service : null,
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
                        'unit_price' => $effectiveById[$item->id],
                        'weight_gram' => $variant->weight_gram ?? $product->weight_gram,
                        'subtotal' => $effectiveById[$item->id] * $item->quantity,
                    ]);
                    
                    // Decrement stock
                    $variant->decrement('stock', $item->quantity);

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

            // Sprint 9: Dispatch Notification atomically (OrderCreated)
            $eventKey = "order_created_notification_{$order->id}";
            $this->dispatchAtomicNotification($eventKey, function () use ($order) {
                $order->user->notify(new OrderStatusNotification(
                    $order,
                    'order_created',
                    'Pesanan Baru Dibuat',
                    'Terima kasih! Pesanan Anda dengan nomor ' . $order->order_number . ' telah berhasil dibuat. Silakan lakukan pembayaran sebelum batas waktu berakhir.'
                ));
            });

            return redirect()
                ->route('checkout.success', $order->order_number)
                ->with('success', 'Pesanan berhasil dibuat!');

        } catch (\Exception $e) {
            return redirect('/cart')->with('error', $e->getMessage());
        }
    }

    /**
     * Show success/payment page — serves as payment hub
     */
    public function success($order_number)
    {
        $order = Order::with('items')->where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Get latest payment (pending or final)
        $activePayment = $order->payments()->latest()->first();

        return Inertia::render('Storefront/CheckoutSuccess', [
            'order' => $order,
            'activePayment' => $activePayment,
            'paymentChannels' => \App\Services\Payment\IPaymuService::getAvailableChannels(),
        ]);
    }

    /**
     * FR018: Cancel order — releases reserved stock
     */
    public function cancel(Request $request, $order_number)
    {
        $order = Order::with('items')->where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!$order->canBeCancelled()) {
            return back()->with('error', 'Pesanan ini tidak dapat dibatalkan.');
        }

        try {
            DB::transaction(function () use ($order) {
                // Lock the order row
                $order = Order::where('id', $order->id)->lockForUpdate()->first();

                // Release reserved stock
                foreach ($order->items as $item) {
                    ProductVariant::where('id', $item->product_variant_id)
                        ->where('reserved_stock', '>=', $item->quantity)
                        ->update([
                            'reserved_stock' => DB::raw('reserved_stock - ' . (int) $item->quantity),
                        ]);
                }

                // Cancel any pending payments
                $order->payments()->where('status', 'pending')->update([
                    'status' => 'failed',
                ]);

                // Update order, store procedure 
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'failed',
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Dibatalkan oleh customer',
                ]);
            });

            return redirect()->route('home')->with('success', 'Pesanan berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membatalkan pesanan: ' . $e->getMessage());
        }
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

    /**
     * FR030 — Validate a voucher code against the authenticated user's cart.
     * Subtotal is computed SERVER-SIDE from PricingService effective prices —
     * client-submitted amounts are never trusted for discount calculation.
     */
    public function validateVoucher(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'item_ids' => 'nullable|string',
            'method' => 'nullable|in:delivery,pickup',
            'courier' => 'nullable',
            'shipping_cost' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        $cart = $user->cart;

        if (!$cart) {
            return response()->json(['valid' => false, 'message' => 'Keranjang kosong.']);
        }

        $query = $cart->items()->with('variant.product');
        if ($request->item_ids) {
            $query->whereIn('id', array_map('intval', explode(',', $request->item_ids)));
        }
        $items = $query->get();

        $pricing = app(\App\Services\PricingService::class);
        $subtotal = $items->sum(fn ($i) => $pricing->effectivePrice($i->variant) * $i->quantity);
        // Fase 1: no Flash Sale items exist yet, so this always evaluates false.
        // Kept here so the gate activates automatically once Flash Sale (Fase 2)
        // starts tagging priceInfo()['source'] === 'flash'.
        $containsFlash = $items->contains(fn ($i) => $pricing->priceInfo($i->variant)['source'] === 'flash');

        $courierType = $this->resolveCourierType($request);

        return response()->json(
            app(\App\Services\PromotionService::class)->validate(
                $request->code,
                (float) $subtotal,
                (float) $request->input('shipping_cost', 0),
                $courierType,
                $containsFlash
            )
        );
    }

    /**
     * Map a checkout request's shipping method/courier to the courier-type
     * string PromotionService expects for free_shipping voucher scoping
     * (Promotion::applicable_shipping_type: 'all' | 'internal' | 'external').
     *
     * - pickup => null (no shipping, scope check is skipped by PromotionService)
     * - courier === 'internal' => 'internal' (Kurir Internal MegaMart, Semarang-only)
     * - any other courier (jne/pos/tiki/...) => 'external' (RajaOngkir)
     *
     * Single source of truth — reused by the checkout submit flow (Task 4) so
     * voucher validation and voucher reservation never disagree on courier type.
     */
    private function resolveCourierType(Request $request): ?string
    {
        if ($request->input('method') === 'pickup') {
            return null;
        }

        return $request->input('courier') === 'internal' ? 'internal' : 'external';
    }
}

