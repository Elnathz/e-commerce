<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReturnRequest;
use Inertia\Inertia;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $query = ReturnRequest::with(['user', 'order'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $returns = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Returns/Index', [
            'returns' => $returns,
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(ReturnRequest $returnRequest)
    {
        $returnRequest->load(['user', 'order.items.productVariant.product']);

        return Inertia::render('Admin/Returns/Show', [
            'returnRequest' => $returnRequest,
        ]);
    }

    public function approve(Request $request, ReturnRequest $returnRequest)
    {
        if (!$returnRequest->canTransitionTo('approved')) {
            return back()->with('error', 'Transisi status tidak diizinkan.');
        }

        $request->validate([
            'refund_amount' => 'required|numeric|min:0|max:' . $returnRequest->order->total_amount,
        ]);

        $returnRequest->update([
            'status' => 'approved',
            'refund_amount' => $request->refund_amount,
            'expires_at' => now()->addDays(7), // Customer has 7 days to return the item
        ]);

        return back()->with('success', 'Pengajuan retur disetujui.');
    }

    public function reject(Request $request, ReturnRequest $returnRequest)
    {
        if (!$returnRequest->canTransitionTo('rejected')) {
            return back()->with('error', 'Transisi status tidak diizinkan.');
        }

        $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        $returnRequest->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        return back()->with('success', 'Pengajuan retur ditolak.');
    }

    public function receive(ReturnRequest $returnRequest)
    {
        if (!$returnRequest->canTransitionTo('received')) {
            return back()->with('error', 'Transisi status tidak diizinkan.');
        }

        $returnRequest->update([
            'status' => 'received',
            'return_received_at' => now(),
        ]);

        return back()->with('success', 'Barang retur telah diterima.');
    }

    public function processRefund(ReturnRequest $returnRequest)
    {
        if (!$returnRequest->canTransitionTo('refund_processed')) {
            return back()->with('error', 'Transisi status tidak diizinkan.');
        }

        // Normally, call payment gateway to process refund here...

        $returnRequest->update([
            'status' => 'refund_processed',
            'refund_processed_at' => now(),
        ]);

        $returnRequest->order->update([
            'status' => 'refunded'
        ]);

        return back()->with('success', 'Pengembalian dana berhasil diproses.');
    }
}
