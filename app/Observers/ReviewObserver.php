<?php

namespace App\Observers;

use App\Models\Review;
use App\Models\Product;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        $this->updateProductRating($review->product_id);
    }

    /**
     * Handle the Review "updated" event.
     */
    public function updated(Review $review): void
    {
        // Only update if rating or is_published changed
        if ($review->wasChanged(['rating', 'is_published'])) {
            $this->updateProductRating($review->product_id);
        }
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        $this->updateProductRating($review->product_id);
    }

    /**
     * Handle the Review "restored" event.
     */
    public function restored(Review $review): void
    {
        $this->updateProductRating($review->product_id);
    }

    /**
     * Handle the Review "force deleted" event.
     */
    public function forceDeleted(Review $review): void
    {
        $this->updateProductRating($review->product_id);
    }

    private function updateProductRating($productId)
    {
        $stats = Review::where('product_id', $productId)
            ->where('is_published', true)
            ->selectRaw('COUNT(*) as count, AVG(rating) as average')
            ->first();

        Product::where('id', $productId)->update([
            'review_count' => $stats->count ?? 0,
            'average_rating' => round($stats->average ?? 0, 2),
        ]);
    }
}
