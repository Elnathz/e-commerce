<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product', 'images'])->latest();

        if ($request->filled('q')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('product', fn($q) => $q->where('name', 'like', "%{$request->q}%"))
                  ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$request->q}%"))
                  ->orWhere('comment', 'like', "%{$request->q}%");
            });
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $request->only(['q', 'rating']),
        ]);
    }

    public function togglePublish(Review $review)
    {
        $review->update([
            'is_published' => !$review->is_published,
        ]);

        return back()->with('success', 'Status ulasan berhasil diperbarui.');
    }
}
