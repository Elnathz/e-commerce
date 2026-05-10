<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
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

        $query = $cart->items()->with(['product.images', 'productVariant']);
        
        if ($itemIds) {
            $idsArray = explode(',', $itemIds);
            $query->whereIn('id', $idsArray);
        }

        $cartItems = $query->get();

        if ($cartItems->count() === 0) {
            return redirect('/cart')->with('error', 'Tidak ada item yang dipilih untuk checkout.');
        }

        // Get addresses for delivery
        $addresses = $method === 'delivery' ? $user->addresses()->get() : [];

        // Load cart items with product variants
        $cartItems = $cart->items()->with(['product.images', 'productVariant'])->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'product_slug' => $item->product->slug,
                'variant_id' => $item->product_variant_id,
                'variant_name' => $item->productVariant ? $item->productVariant->name : null,
                'image' => $item->product->images->where('is_primary', true)->first()?->image_url 
                        ?? $item->product->images->first()?->image_url,
                'quantity' => $item->quantity,
                'current_price' => $item->productVariant ? $item->productVariant->price : $item->product->base_price,
                'weight' => $item->product->weight,
            ];
        });

        // Calculate subtotal and total weight
        $subtotal = collect($cartItems)->sum(function ($item) {
            return $item['current_price'] * $item['quantity'];
        });

        $totalWeight = collect($cartItems)->sum(function ($item) {
            return $item['weight'] * $item['quantity'];
        });

        return Inertia::render('Storefront/Checkout', [
            'method' => $method,
            'addresses' => $addresses,
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'totalWeight' => $totalWeight,
            'rajaongkirKeyExists' => !empty(env('RAJAONGKIR_API_KEY'))
        ]);
    }

    /**
     * Process checkout and create order
     */
    public function store(Request $request)
    {
        $request->validate([
            'method' => 'required|in:delivery,pickup',
            'address_id' => 'required_if:method,delivery|exists:user_addresses,id',
            'courier' => 'required_if:method,delivery',
            'shipping_service' => 'required_if:method,delivery',
            'shipping_cost' => 'required_if:method,delivery|numeric|min:0',
            'notes' => 'nullable|string',
            'item_ids' => 'nullable|string', // Comma-separated cart_item IDs
        ]);

        $user = Auth::user();
        $cart = $user->cart;

        if (!$cart || $cart->items()->count() === 0) {
            return redirect('/cart')->with('error', 'Keranjang belanja Anda kosong.');
        }

        $query = $cart->items()->with(['product.images', 'productVariant']);
        
        if ($request->item_ids) {
            $idsArray = explode(',', $request->item_ids);
            $query->whereIn('id', $idsArray);
        }

        $cartItems = $query->get();

        if ($cartItems->count() === 0) {
            return redirect('/cart')->with('error', 'Tidak ada item yang dipilih untuk diproses.');
        }

        // Calculate Subtotal
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $price = $item->productVariant ? $item->productVariant->price : $item->product->base_price;
            $subtotal += $price * $item->quantity;
        }

        $shippingCost = $request->method === 'delivery' ? $request->shipping_cost : 0;
        $totalAmount = $subtotal + $shippingCost;

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

        // Create Order
        $order = \App\Models\Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
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
        ]);

        // Create Order Items and Collect Cart Item IDs to Delete
        $cartItemIdsToDelete = [];
        foreach ($cartItems as $item) {
            $price = $item->productVariant ? $item->productVariant->price : $item->product->base_price;
            \App\Models\OrderItem::create([
                'order_id' => $order->id,
                'product_variant_id' => $item->product_variant_id,
                'product_name_snapshot' => $item->product->name,
                'variant_name_snapshot' => $item->productVariant ? $item->productVariant->name : null,
                'quantity' => $item->quantity,
                'unit_price' => $price,
                'weight_gram' => $item->product->weight,
                'subtotal' => $price * $item->quantity,
            ]);
            $cartItemIdsToDelete[] = $item->id;
        }

        // Clear only checked out items
        $cart->items()->whereIn('id', $cartItemIdsToDelete)->delete();
        
        // If cart is now empty, we can optionally delete the cart
        if ($cart->items()->count() === 0) {
            $cart->delete();
            session()->flash('cart_count', 0);
        } else {
            session()->flash('cart_count', $cart->items()->count());
        }

        return redirect()->route('checkout.success', $order->order_number)->with('success', 'Pesanan berhasil dibuat!');
    }

    /**
     * Show success page
     */
    public function success($order_number)
    {
        $order = \App\Models\Order::with('items')->where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->firstOrFail();
            
        return Inertia::render('Storefront/CheckoutSuccess', [
            'order' => $order
        ]);
    }

    /**
     * Calculate shipping cost based on destination and weight
     */
    public function calculateShipping(Request $request)
    {
        $request->validate([
            'destination_city' => 'required',
            'weight' => 'required|numeric|min:1',
            'courier' => 'required|in:jne,pos,tiki,internal'
        ]);

        $originCityId = 399; // ID Kota Semarang (default store location)
        $destinationCityId = $request->destination_city;

        // If destination is Semarang, offer internal courier
        if ($destinationCityId == $originCityId) {
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
        }

        $apiKey = env('RAJAONGKIR_API_KEY');
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API Key RajaOngkir belum dikonfigurasi'
            ], 500);
        }
        
        $response = Http::timeout(15)->withHeaders(['key' => $apiKey])
            ->post('https://api.rajaongkir.com/starter/cost', [
                'origin' => $originCityId,
                'destination' => $destinationCityId,
                'weight' => $request->weight,
                'courier' => $request->courier === 'internal' ? 'jne' : $request->courier
            ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'results' => $response->json('rajaongkir.results')
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data ongkos kirim. Pastikan tujuan dan berat valid.'
        ], 500);
    }
}
