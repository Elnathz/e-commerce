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
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Order::with('user')->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // P1.1: KPI deep-links — scope the list to the dashboard period via created_at.
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'currentStatus' => $status,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
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

        $fromStatus = $order->status;

        $order->update([
            'status' => 'processing',
            'processing_at' => now(),
        ]);

        event(new \App\Events\OrderStatusChanged($order, $fromStatus, 'processing'));

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

        $fromStatus = $order->status;

        $validated = $request->validate([
            'tracking_number' => 'required|string|max:200',
        ]);

        $order->update([
            'status' => 'shipped',
            'tracking_number' => $validated['tracking_number'],
            'shipped_at' => now(),
        ]);

        event(new \App\Events\OrderStatusChanged($order, $fromStatus, 'shipped'));

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
