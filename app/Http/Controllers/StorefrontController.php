<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;

class StorefrontController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'variants' => function($q) {
            $q->where('is_active', true);
        }, 'images' => function($q) {
            $q->orderBy('is_primary', 'desc')->orderBy('sort_order');
        }])
        ->where('is_active', true)
        ->inRandomOrder()
        ->take(12)
        ->get();

        $categories = \App\Models\Category::with('children')
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Storefront/Index', [
            'products' => $products,
            'categories' => $categories
        ]);
    }

    public function show(string $slug)
    {
        $product = Product::with([
            'category.parent',
            'variants' => function($q) {
                $q->where('is_active', true)->orderBy('price', 'asc');
            },
            'variants.images',
            'images' => function($q) {
                $q->orderBy('is_primary', 'desc')->orderBy('sort_order');
            },
            'reviews' => function($q) {
                $q->where('is_published', true)->latest()->with(['user', 'images', 'orderItem.returnRequestItems.returnRequest']);
            }
        ])->where('slug', $slug)->where('is_active', true)->firstOrFail();

        // §1c / #44: review return-badge — "Sudah Direfund" / "Pernah Ajukan Retur".
        $product->reviews->each(function ($review) {
            $review->return_badge = $review->orderItem?->reviewReturnBadge()['outcome'] ?? null;
        });

        // Related products from the same category
        $relatedProducts = Product::with(['variants' => function($q) {
            $q->where('is_active', true);
        }, 'images' => function($q) {
            $q->orderBy('is_primary', 'desc')->orderBy('sort_order');
        }])
        ->where('category_id', $product->category_id)
        ->where('id', '!=', $product->id)
        ->where('is_active', true)
        ->take(6)
        ->get();

        return Inertia::render('Storefront/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }

    public function search(Request $request)
    {
        $query = Product::with(['category', 'variants' => function($q) {
            $q->where('is_active', true);
        }, 'images' => function($q) {
            $q->orderBy('is_primary', 'desc')->orderBy('sort_order');
        }])->where('is_active', true);

        // Keyword search
        if ($request->filled('q')) {
            $keyword = $request->q;
            $query->where(function($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // Stock filter
        if ($request->boolean('in_stock')) {
            $query->whereHas('variants', function($q) {
                $q->where('is_active', true)->where('stock', '>', 0);
            });
        }

        // Price range
        if ($request->filled('price_min')) {
            $query->where('base_price', '>=', $request->price_min);
        }
        if ($request->filled('price_max')) {
            $query->where('base_price', '<=', $request->price_max);
        }

        // Category filter
        if ($request->filled('categories')) {
            $query->whereIn('category_id', $request->categories);
        }

        // Color filter (variant_type = Warna)
        if ($request->filled('colors')) {
            $query->whereHas('variants', function($q) use ($request) {
                $q->where('variant_type', 'Warna')
                  ->whereIn('name', $request->colors);
            });
        }

        // Sorting
        switch ($request->input('sort', 'best_match')) {
            case 'az':
                $query->orderBy('name', 'asc');
                break;
            case 'za':
                $query->orderBy('name', 'desc');
                break;
            case 'price_low':
                $query->orderBy('base_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('base_price', 'desc');
                break;
            case 'newest':
                $query->latest();
                break;
            case 'oldest':
                $query->oldest();
                break;
            case 'recommended':
                $query->inRandomOrder();
                break;
            default: // best_match
                if ($request->filled('q')) {
                    // Relevance: exact name match first
                    $query->orderByRaw("CASE WHEN name LIKE ? THEN 0 ELSE 1 END", [$request->q . '%']);
                }
                $query->latest();
                break;
        }

        $products = $query->paginate(12)->withQueryString();

        // Available filters data
        $availableCategories = \App\Models\Category::query()->where('is_active', true)
            ->whereNotNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $availableColors = \App\Models\ProductVariant::query()->where('variant_type', 'Warna')
            ->where('is_active', true)
            ->distinct()
            ->pluck('name')
            ->sort()
            ->values();

        return Inertia::render('Storefront/Search', [
            'products' => $products,
            'filters' => $request->only(['q', 'in_stock', 'price_min', 'price_max', 'categories', 'colors', 'sort']),
            'availableCategories' => $availableCategories,
            'availableColors' => $availableColors,
            'totalResults' => $products->total(),
        ]);
    }
}
