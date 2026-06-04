<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\ReturnRequest;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ReturnController extends Controller
{
    public function show($return_number)
    {
        $returnRequest = ReturnRequest::query()->where('return_number', $return_number)
            ->where('user_id', Auth::id())
            ->with(['order.items.productVariant.product'])
            ->firstOrFail();

        return Inertia::render('Returns/Show', [
            'returnRequest' => $returnRequest,
        ]);
    }

    public function store(Request $request, $order_number)
    {
        $order = Order::query()->where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!$order->canBeReturned()) {
            return back()->with('error', 'Pesanan ini tidak dapat dikembalikan.');
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.reason_code' => 'required|in:defective,wrong_item,missing_part,damaged_shipping,not_as_described,other',
            'items.*.reason_notes' => 'nullable|string|max:1000',
            'items.*.condition' => 'required|in:opened,damaged,wrong_item,defective,other',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'file|mimes:jpeg,png,jpg,webp,mp4|max:10240',
        ]);

        $remainingItems = $order->getRemainingReturnableItems();
        foreach ($request->items as $itemData) {
            $itemId = $itemData['order_item_id'];
            if (!isset($remainingItems[$itemId]) || $itemData['quantity'] > $remainingItems[$itemId]) {
                return back()->with('error', 'Kuantitas retur melebihi batas yang diperbolehkan atau barang tidak dapat diretur lagi.');
            }
        }

        $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        
        $manager = new ImageManager(new Driver());
        $imagePaths = [];

        foreach ($request->file('images') as $index => $file) {
            if ($index >= 5) break;

            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension === 'mp4') {
                $filename = 'returns/' . Str::uuid() . '.mp4';
                Storage::disk('public')->put($filename, file_get_contents($file));
                $imagePaths['evidence_image_' . ($index + 1)] = $filename;
            } else {
                $image = $manager->decode($file->getRealPath());
                $image->scaleDown(width: 1200);
                
                $filename = 'returns/' . Str::uuid() . '.jpg';
                Storage::disk('public')->put($filename, (string) $image->encode(new \Intervention\Image\Encoders\JpegEncoder(quality: 80)));
                $imagePaths['evidence_image_' . ($index + 1)] = $filename;
            }
        }

        $returnRequest = ReturnRequest::create(array_merge([
            'return_number' => $returnNumber,
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'status' => 'submitted',
            'reason' => $request->reason,
        ], $imagePaths));

        foreach ($request->items as $itemData) {
            $returnRequest->items()->create([
                'order_item_id' => $itemData['order_item_id'],
                'quantity' => $itemData['quantity'],
                'reason_code' => $itemData['reason_code'],
                'reason_notes' => $itemData['reason_notes'] ?? null,
                'condition' => $itemData['condition'],
            ]);
        }

        $returnRequest->histories()->create([
            'from_status' => null,
            'to_status' => 'submitted',
            'actor_id' => Auth::id(),
            'actor_type' => 'App\Models\User',
            'notes' => 'Pengajuan retur dibuat oleh pelanggan.',
        ]);

        return redirect()->route('returns.show', $returnNumber)->with('success', 'Pengajuan pengembalian berhasil dikirim.');
    }

    public function submitTracking(Request $request, $return_number)
    {
        $request->validate([
            'return_courier' => 'required|string|max:255',
            'return_tracking_number' => 'required|string|max:255',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function() use ($request, $return_number) {
            $returnRequest = ReturnRequest::lockForUpdate()
                ->where('return_number', $return_number)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            if ($returnRequest->status !== 'approved' && $returnRequest->status !== 'waiting_customer_shipment') {
                return back()->with('error', 'Anda hanya dapat memasukkan resi jika status retur disetujui.');
            }

            $from_status = $returnRequest->status;

            $returnRequest->update([
                'return_courier' => $request->return_courier,
                'return_tracking_number' => $request->return_tracking_number,
                'status' => 'customer_shipped',
            ]);

            $returnRequest->histories()->create([
                'from_status' => $from_status,
                'to_status' => 'customer_shipped',
                'actor_id' => Auth::id(),
                'actor_type' => 'App\Models\User',
                'notes' => 'Pelanggan telah mengirim barang dengan resi: ' . $request->return_tracking_number,
            ]);

            return back()->with('success', 'Resi pengiriman berhasil disimpan.');
        });
    }
}
