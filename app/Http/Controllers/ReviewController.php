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

        // Cek eligibility item terhadap retur
        if ($orderItem->hasActiveReturn()) {
            return back()->with('error', 'Produk ini sedang dalam proses retur dan belum dapat diberi ulasan.');
        }

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

    public function update(Request $request, Review $review)
    {
        if ($review->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($review->is_edited) {
            return back()->with('error', 'Ulasan hanya dapat diedit satu kali.');
        }

        if ($review->created_at->diffInDays(now()) > 30) {
            return back()->with('error', 'Ulasan hanya dapat diedit dalam waktu 30 hari setelah dibuat.');
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'kept_images' => 'nullable|array',
            'kept_images.*' => 'exists:review_images,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $keptImagesCount = is_array($request->kept_images) ? count($request->kept_images) : 0;
        $newImagesCount = $request->hasFile('images') ? count($request->file('images')) : 0;

        if (($keptImagesCount + $newImagesCount) > 5) {
            return back()->with('error', 'Total maksimal foto adalah 5.');
        }

        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_edited' => true,
        ]);

        // Delete images not in kept_images
        $imagesToDelete = $review->images();
        if ($request->has('kept_images') && is_array($request->kept_images)) {
            $imagesToDelete = $imagesToDelete->whereNotIn('id', $request->kept_images);
        }
        
        $imagesToDelete = $imagesToDelete->get();
        foreach ($imagesToDelete as $imageToDelete) {
            if (Storage::disk('public')->exists($imageToDelete->image_path)) {
                Storage::disk('public')->delete($imageToDelete->image_path);
            }
            $imageToDelete->delete();
        }

        // Upload new images
        if ($request->hasFile('images')) {
            $manager = new ImageManager(new Driver());
            
            foreach ($request->file('images') as $file) {
                $image = $manager->decode($file->getRealPath());
                $image->scaleDown(width: 1200);
                
                $filename = 'reviews/' . Str::uuid() . '.jpg';
                Storage::disk('public')->put($filename, (string) $image->encode(new \Intervention\Image\Encoders\JpegEncoder(quality: 80)));

                $review->images()->create([
                    'image_path' => $filename,
                ]);
            }
        }

        return back()->with('success', 'Ulasan berhasil diperbarui.');
    }
}
