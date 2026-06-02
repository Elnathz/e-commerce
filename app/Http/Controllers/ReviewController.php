<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ReviewController extends Controller
{
    public function store(Request $request, $order_number)
    {
        $order = Order::where('order_number', $order_number)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($order->status !== 'completed') {
            return back()->with('error', 'Hanya pesanan yang sudah selesai yang dapat diberi ulasan.');
        }

        if ($order->returnRequest && $order->returnRequest->status !== 'rejected') {
            return back()->with('error', 'Pesanan yang diretur atau direfund tidak dapat diberi ulasan.');
        }

        $request->validate([
            'order_item_id' => 'required|exists:order_items,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120', // Max 5MB raw
        ]);

        $orderItem = OrderItem::where('id', $request->order_item_id)
            ->where('order_id', $order->id)
            ->firstOrFail();

        if ($orderItem->review()->exists()) {
            return back()->with('error', 'Anda sudah memberikan ulasan untuk produk ini.');
        }

        $review = Review::create([
            'order_item_id' => $orderItem->id,
            'user_id' => Auth::id(),
            'product_id' => $orderItem->productVariant->product_id, // ensure relation is loaded or directly accessible
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_published' => true,
        ]);

        if ($request->hasFile('images')) {
            $manager = new ImageManager(new Driver());
            
            foreach ($request->file('images') as $file) {
                // Read image from file system
                $image = $manager->decode($file->getRealPath());
                
                // Compress and resize
                $image->scaleDown(width: 1200);
                
                $filename = 'reviews/' . Str::uuid() . '.jpg';
                Storage::disk('public')->put($filename, (string) $image->encode(new \Intervention\Image\Encoders\JpegEncoder(quality: 80)));

                $review->images()->create([
                    'image_path' => $filename,
                ]);
            }
        }

        return back()->with('success', 'Ulasan berhasil disimpan. Terima kasih!');
    }
}
