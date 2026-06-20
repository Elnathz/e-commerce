<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReturnRequest;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $query = ReturnRequest::with(['user', 'order'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // P1.1: KPI deep-links — scope the list to the dashboard period via created_at.
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($sub) use ($term) {
                $sub->where('return_number', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
            });
        }

        $returns = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Returns/Index', [
            'returns' => $returns,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'q']),
            'stats' => [
                'awaiting_refund' => ReturnRequest::where('status', 'inspected')->count(),
                'awaiting_inspection' => ReturnRequest::where('status', 'received')->count(),
                'awaiting_approval' => ReturnRequest::where('status', 'submitted')->count(),
            ],
        ]);
    }

    public function show(ReturnRequest $returnRequest)
    {
        $returnRequest->load(['user', 'order.items.productVariant.product', 'items.orderItem', 'histories' => function($q) {
            $q->latest();
        }]);

        // Calculate prorated refund suggestion
        $order = $returnRequest->order;
        $returnRequest->items->each(function ($item) use ($order) {
            $orderItem = $item->orderItem;
            $itemSubtotal = $orderItem ? ($item->quantity * $orderItem->unit_price) : 0;
            $item->suggested_refund = $this->calculateProratedRefund($order, $itemSubtotal);
        });

        return Inertia::render('Admin/Returns/Show', [
            'returnRequest' => $returnRequest,
        ]);
    }

    /**
     * Hitung refund prorata untuk satu item retur.
     * Scope: hanya discount_amount vs subtotal.
     * Shipping tidak direfund. Jika voucher adalah free_shipping
     * (discount_on_shipping = true), discount_amount tersebut mengurangi
     * ongkir, bukan item, sehingga ratio item = 0 (refund item tidak dipotong).
     */
    public function calculateProratedRefund(\App\Models\Order $order, float $itemSubtotal): float
    {
        $discountRatio = ($order->discount_on_shipping || $order->subtotal <= 0 || $order->discount_amount <= 0)
            ? 0.0
            : min($order->discount_amount / $order->subtotal, 1.0);
        return round($itemSubtotal * (1 - $discountRatio), 2);
    }

    public function approve(Request $request, ReturnRequest $returnRequest)
    {
        return DB::transaction(function() use ($returnRequest) {
            $returnRequest = ReturnRequest::lockForUpdate()->find($returnRequest->id);
            if (!$returnRequest->canTransitionTo('approved')) {
                return back()->with('error', 'Transisi status tidak diizinkan.');
            }

            $from_status = $returnRequest->status;

            $returnRequest->update([
                'status' => 'approved',
                'expires_at' => now()->addDays(3), // Admin SLA for approval is 3 days, customer has 3 days to ship
            ]);
            
            event(new \App\Events\ReturnRequestStatusChanged($returnRequest, $from_status, 'approved'));
            
            $returnRequest->histories()->create([
                'from_status' => 'submitted',
                'to_status' => 'approved',
                'actor_id' => Auth::id(),
                'actor_type' => 'App\Models\User',
                'notes' => 'Pengajuan retur disetujui. Menunggu pelanggan mengirim barang.',
            ]);

            return back()->with('success', 'Pengajuan retur disetujui.');
        });
    }

    public function reject(Request $request, ReturnRequest $returnRequest)
    {
        $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        return DB::transaction(function() use ($request, $returnRequest) {
            $returnRequest = ReturnRequest::lockForUpdate()->find($returnRequest->id);
            if (!$returnRequest->canTransitionTo('rejected')) {
                return back()->with('error', 'Transisi status tidak diizinkan.');
            }

            $from_status = $returnRequest->status;

            $returnRequest->update([
                'status' => 'rejected',
                'admin_notes' => $request->admin_notes,
            ]);

            event(new \App\Events\ReturnRequestStatusChanged($returnRequest, $from_status, 'rejected'));

            $returnRequest->histories()->create([
                'from_status' => $from_status,
                'to_status' => 'rejected',
                'actor_id' => Auth::id(),
                'actor_type' => 'App\Models\User',
                'notes' => 'Retur ditolak: ' . $request->admin_notes,
            ]);

            return back()->with('success', 'Pengajuan retur ditolak.');
        });
    }

    public function receive(ReturnRequest $returnRequest)
    {
        return DB::transaction(function() use ($returnRequest) {
            $returnRequest = ReturnRequest::lockForUpdate()->find($returnRequest->id);
            if (!$returnRequest->canTransitionTo('received')) {
                return back()->with('error', 'Transisi status tidak diizinkan.');
            }
            
            $from_status = $returnRequest->status;

            $returnRequest->update([
                'status' => 'received',
                'return_received_at' => now(),
            ]);

            event(new \App\Events\ReturnRequestStatusChanged($returnRequest, $from_status, 'received'));

            $returnRequest->histories()->create([
                'from_status' => $from_status,
                'to_status' => 'received',
                'actor_id' => Auth::id(),
                'actor_type' => 'App\Models\User',
                'notes' => 'Barang retur telah diterima di gudang.',
            ]);

            return back()->with('success', 'Barang retur telah diterima.');
        });
    }

    public function inspect(Request $request, ReturnRequest $returnRequest)
    {
        $request->validate([
            'inspection_result' => 'required|in:passed,failed',
            'items' => 'required_if:inspection_result,passed|array',
            'items.*.id' => 'required|exists:return_request_items,id',
            'items.*.refund_amount' => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($returnRequest) {
                    preg_match('/items\.(\d+)\.refund_amount/', $attribute, $matches);
                    if (isset($matches[1])) {
                        $index = $matches[1];
                        $itemId = request("items.$index.id");
                        $item = $returnRequest->items()->with('orderItem')->find($itemId);
                        if ($item && $item->orderItem) {
                            $maxRefund = $item->quantity * $item->orderItem->unit_price;
                            if ($value > $maxRefund) {
                                $fail("The refund amount cannot exceed the maximum value of Rp " . number_format($maxRefund, 0, ',', '.'));
                            }
                        }
                    }
                }
            ],
            'items.*.restock' => 'boolean',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function() use ($request, $returnRequest) {
            $returnRequest = ReturnRequest::lockForUpdate()->find($returnRequest->id);
            $newStatus = $request->inspection_result === 'failed' ? 'rejected' : 'inspected';

            if (!$returnRequest->canTransitionTo($newStatus)) {
                return back()->with('error', 'Transisi status tidak diizinkan.');
            }

            $from_status = $returnRequest->status;
            $total_refund = 0;

            if ($request->inspection_result === 'passed') {
                foreach ($request->items as $itemData) {
                    $item = $returnRequest->items()->where('id', $itemData['id'])->lockForUpdate()->first();
                    
                    if (!$item) continue;
                    
                    $item->update(['refund_amount' => $itemData['refund_amount']]);
                    $total_refund += $itemData['refund_amount'];

                    if (!empty($itemData['restock']) && !$item->is_restocked) {
                        $orderItem = $item->orderItem;
                        ProductVariant::where('id', $orderItem->product_variant_id)
                            ->increment('stock', $item->quantity);
                            
                        $item->update(['is_restocked' => true]);
                    }
                }
            }

            $updateData = [
                'status' => $newStatus,
                'inspection_result' => $request->inspection_result,
                'refund_amount' => $total_refund,
                'admin_notes' => $request->admin_notes ?? $returnRequest->admin_notes,
            ];

            if ($newStatus === 'inspected') {
                $updateData['inspected_at'] = now();
            }

            $returnRequest->update($updateData);

            event(new \App\Events\ReturnRequestStatusChanged($returnRequest, $from_status, $newStatus));

            $returnRequest->histories()->create([
                'from_status' => $from_status,
                'to_status' => $newStatus,
                'actor_id' => Auth::id(),
                'actor_type' => 'App\Models\User',
                'notes' => 'Inspeksi selesai dengan hasil: ' . strtoupper($request->inspection_result) . '. ' . $request->admin_notes,
            ]);
            
            return back()->with('success', 'Inspeksi barang berhasil disimpan.');
        });
    }

    public function processRefund(ReturnRequest $returnRequest)
    {
        return DB::transaction(function() use ($returnRequest) {
            $returnRequest = ReturnRequest::lockForUpdate()->find($returnRequest->id);
            if (!$returnRequest->canTransitionTo('refund_processed')) {
                return back()->with('error', 'Transisi status tidak diizinkan.');
            }

            $from_status = $returnRequest->status;

            // Normally, call payment gateway to process refund here...

            $returnRequest->update([
                'status' => 'refund_processed',
                'refund_processed_at' => now(),
            ]);

            event(new \App\Events\ReturnRequestStatusChanged($returnRequest, $from_status, 'refund_processed'));

            $order = $returnRequest->order;
            // Calculate if it's partial or full refund based on total vs refund_amount
            $is_full_refund = $returnRequest->refund_amount >= $order->total_amount;
            
            $order->update([
                'refund_status' => $is_full_refund ? 'full' : 'partial'
            ]);

            $returnRequest->histories()->create([
                'from_status' => $from_status,
                'to_status' => 'refund_processed',
                'actor_id' => Auth::id(),
                'actor_type' => 'App\Models\User',
                'notes' => 'Refund berhasil diproses sebesar Rp ' . number_format($returnRequest->refund_amount, 0, ',', '.'),
            ]);
            
            return back()->with('success', 'Pengembalian dana berhasil diproses.');
        });
    }
    
    public function complete(ReturnRequest $returnRequest)
    {
        return DB::transaction(function() use ($returnRequest) {
            $returnRequest = ReturnRequest::lockForUpdate()->find($returnRequest->id);
            if (!$returnRequest->canTransitionTo('completed')) {
                return back()->with('error', 'Transisi status tidak diizinkan.');
            }
            
            $from_status = $returnRequest->status;

            $returnRequest->update([
                'status' => 'completed',
            ]);

            event(new \App\Events\ReturnRequestStatusChanged($returnRequest, $from_status, 'completed'));

            $returnRequest->histories()->create([
                'from_status' => $from_status,
                'to_status' => 'completed',
                'actor_id' => Auth::id(),
                'actor_type' => 'App\Models\User',
                'notes' => 'Siklus retur selesai.',
            ]);

            return back()->with('success', 'Proses retur telah diselesaikan.');
        });
    }
}
