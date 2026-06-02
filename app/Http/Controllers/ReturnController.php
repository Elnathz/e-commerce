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
            'is_partial' => 'boolean',
            'images' => 'required|array|min:1|max:3',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        
        $manager = new ImageManager(new Driver());
        $imagePaths = [];

        foreach ($request->file('images') as $index => $file) {
            $image = $manager->decode($file->getRealPath());
            $image->scaleDown(width: 1200);
            
            $filename = 'returns/' . Str::uuid() . '.jpg';
            Storage::disk('public')->put($filename, (string) $image->encode(new \Intervention\Image\Encoders\JpegEncoder(quality: 80)));
            $imagePaths['evidence_image_' . ($index + 1)] = $filename;
        }

        ReturnRequest::create(array_merge([
            'return_number' => $returnNumber,
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'status' => 'submitted',
            'reason' => $request->reason,
            'is_partial' => $request->boolean('is_partial'),
        ], $imagePaths));

        return redirect()->route('returns.show', $returnNumber)->with('success', 'Pengajuan pengembalian berhasil dikirim.');
    }

    public function submitTracking(Request $request, $return_number)
    {
        $returnRequest = ReturnRequest::query()->where('return_number', $return_number)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($returnRequest->status !== 'approved') {
            return back()->with('error', 'Anda hanya dapat memasukkan resi jika status retur disetujui.');
        }

        $request->validate([
            'return_courier' => 'required|string|max:255',
            'return_tracking_number' => 'required|string|max:255',
        ]);

        $returnRequest->update([
            'return_courier' => $request->return_courier,
            'return_tracking_number' => $request->return_tracking_number,
            'status' => 'returned',
        ]);

        return back()->with('success', 'Resi pengiriman berhasil disimpan.');
    }
}
