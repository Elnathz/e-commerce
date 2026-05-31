<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Tampilkan daftar pesanan pelanggan (dengan filter tab)
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $query = Order::where('user_id', auth()->id())
            ->with(['items.productVariant.product.images', 'items.productVariant.images'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->paginate(10)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'currentStatus' => $status
        ]);
    }

    /**
     * Tampilkan detail pesanan pelanggan
     */
    public function show($order_number)
    {
        $order = Order::where('user_id', auth()->id())
            ->where('order_number', $order_number)
            ->with(['items.productVariant.product', 'payments' => function($q) {
                $q->latest();
            }])
            ->firstOrFail();

        return Inertia::render('Orders/Show', [
            'order' => $order
        ]);
    }

    /**
     * Konfirmasi pesanan telah diterima (ubah status shipped -> completed)
     */
    public function confirm($order_number)
    {
        $order = Order::where('user_id', auth()->id())
            ->where('order_number', $order_number)
            ->firstOrFail();

        if ($order->status !== 'shipped') {
            return back()->with('error', 'Hanya pesanan yang sedang dikirim yang bisa dikonfirmasi.');
        }

        $order->update([
            'status' => 'completed',
            'delivered_at' => now(),
        ]);

        return back()->with('success', 'Terima kasih telah mengonfirmasi penerimaan pesanan!');
    }
}
