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
        $query = Review::with(['user', 'product', 'images', 'orderItem.order'])->orderBy('rating', 'asc')->orderBy('created_at', 'desc');

        // Stats & Health Summary
        $distribution = Review::selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $stats = [
            'total_reviews' => Review::count(),
            'average_rating' => round(Review::avg('rating') ?? 0, 1),
            'unreplied_reviews' => Review::whereNull('admin_reply')->count(),
            'distribution' => [
                5 => $distribution[5] ?? 0,
                4 => $distribution[4] ?? 0,
                3 => $distribution[3] ?? 0,
                2 => $distribution[2] ?? 0,
                1 => $distribution[1] ?? 0,
            ]
        ];

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
            'stats' => $stats,
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

    public function reply(Request $request, Review $review)
    {
        $request->validate([
            'admin_reply' => 'required|string|max:1000'
        ]);

        $review->update([
            'admin_reply' => $request->admin_reply,
            'replied_at' => now(),
        ]);

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    public function bulkModerate(Request $request)
    {
        $request->validate([
            'review_ids' => 'required|array',
            'review_ids.*' => 'exists:reviews,id',
            'action' => 'required|in:hide,publish',
        ]);

        $isPublished = $request->action === 'publish';
        Review::whereIn('id', $request->review_ids)->update(['is_published' => $isPublished]);

        return back()->with('success', count($request->review_ids) . ' ulasan berhasil diperbarui.');
    }
}
