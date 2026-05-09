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

    public function show($slug)
    {
        // Placeholder for show
        return Inertia::render('Storefront/Show');
    }
}
