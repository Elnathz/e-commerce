<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;

class StorefrontController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'images' => function($q) {
            $q->where('is_primary', true)->orWhereNull('product_variant_id');
        }])
        ->where('is_active', true)
        ->latest()
        ->get();

        return Inertia::render('Storefront/Index', [
            'products' => $products
        ]);
    }

    public function show($slug)
    {
        // Placeholder for show
        return Inertia::render('Storefront/Show');
    }
}
