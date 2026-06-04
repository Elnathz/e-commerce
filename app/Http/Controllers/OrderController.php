<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $query = Order::where('user_id', Auth::id())
            ->with(['items.productVariant.product.images', 'items.productVariant.images', 'returnRequests'])
            ->withCount([
                'items',
                'items as reviewed_items_count' => function ($q) {
                    $q->has('review');
                }
            ])
            ->withAvg('reviews', 'rating')
            ->latest();

        // Helper closure untuk mengecualikan retur aktif
        $excludeActiveReturns = function ($q) {
            $q->whereDoesntHave('returnRequests', function ($q) {
                $q->whereNotIn('status', ['rejected', 'cancelled']);
            });
        };

        if ($status === 'pending') {
            $query->whereIn('status', ['pending', 'waiting']);
        } elseif ($status === 'processing') {
            $query->whereIn('status', ['paid', 'processing']);
        } elseif ($status === 'shipped') {
            $query->where('status', 'shipped')->where($excludeActiveReturns);
        } elseif ($status === 'completed') {
            $query->where('status', 'completed')->where($excludeActiveReturns);
        } elseif ($status === 'returned') {
            $query->whereHas('returnRequests', function ($q) {
                $q->whereNotIn('status', ['rejected', 'cancelled', 'refund_processed']);
            });
        } elseif ($status === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'refunded']);
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
        $order = Order::where('user_id', Auth::id())
            ->where('order_number', $order_number)
            ->with(['items.productVariant.product', 'items.review.images', 'returnRequests.items', 'payments' => function($q) {
                $q->latest();
            }])
            ->firstOrFail();

        // We also append a custom attribute to pass to frontend
        $order->can_be_returned_flag = $order->canBeReturned();
        $order->append('return_request');

        return Inertia::render('Orders/Show', [
            'order' => $order
        ]);
    }

    /**
     * Konfirmasi pesanan telah diterima (ubah status shipped -> completed)
     */
    public function confirm($order_number)
    {
        $order = Order::where('user_id', Auth::id())
            ->where('order_number', $order_number)
            ->firstOrFail();

        if ($order->status !== 'shipped') {
            return back()->with('error', 'Hanya pesanan yang sedang dikirim yang bisa dikonfirmasi.');
        }

        $order->update([
            'status' => 'completed',
            'delivered_at' => now(),
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Terima kasih telah mengonfirmasi penerimaan pesanan!');
    }
}
