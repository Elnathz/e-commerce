<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Daftar seluruh pesanan
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $query = Order::with('user')->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'currentStatus' => $status
        ]);
    }

    /**
     * Detail pesanan
     */
    public function show(Order $order)
    {
        $order->load(['user', 'items.productVariant', 'payments' => function($q) {
            $q->latest();
        }]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order
        ]);
    }

    /**
     * Proses pesanan (paid -> processing)
     */
    public function process(Order $order)
    {
        if ($order->status !== 'paid') {
            return back()->with('error', 'Hanya pesanan berstatus "Dibayar" yang bisa diproses.');
        }

        $order->update([
            'status' => 'processing'
        ]);

        return back()->with('success', 'Pesanan sedang diproses dan dikemas.');
    }

    /**
     * Kirim pesanan & input resi (processing -> shipped)
     */
    public function ship(Request $request, Order $order)
    {
        if ($order->status !== 'processing') {
            return back()->with('error', 'Pastikan pesanan sudah dalam status "Diproses" sebelum dikirim.');
        }

        $validated = $request->validate([
            'tracking_number' => 'required|string|max:200',
        ]);

        $order->update([
            'status' => 'shipped',
            'tracking_number' => $validated['tracking_number'],
            'shipped_at' => now(),
        ]);

        return back()->with('success', 'Pesanan berhasil dikirim dan nomor resi telah disimpan.');
    }

    /**
     * Cetak label resi / Invoice
     */
    public function print(Order $order)
    {
        $order->load(['user', 'items']);
        return Inertia::render('Admin/Orders/Print', [
            'order' => $order
        ]);
    }
}
