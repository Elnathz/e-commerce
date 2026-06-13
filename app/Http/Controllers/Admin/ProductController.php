<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Category;
use App\Services\AnalyticsDashboardService;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request, AnalyticsDashboardService $analytics)
    {
        $threshold = (int) env('LOW_STOCK_THRESHOLD', 5);

        $query = Product::query()
            ->with(['category', 'images'])
            ->withSum('variants as stock_sum', 'stock')
            ->withSum('variants as reserved_sum', 'reserved_stock')
            ->withCount(['variants as low_stock_variants_count' => function ($q) use ($threshold) {
                $q->whereRaw('(stock - reserved_stock) <= ?', [$threshold]);
            }])
            ->withCount(['variants as out_of_stock_variants_count' => function ($q) {
                $q->whereRaw('(stock - reserved_stock) <= 0');
            }]);

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Hidupkan deep-link dashboard: produk punya >=1 varian kritis.
        if ($request->input('filter') === 'low_stock') {
            $query->whereHas('variants', fn ($v) => $v->whereRaw('(stock - reserved_stock) <= ?', [$threshold]));
        }

        $products = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => $request->only(['q', 'category', 'status', 'filter']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'lowStockThreshold' => $threshold,
            'stats' => [
                'total' => Product::count(),
                'active' => Product::where('is_active', true)->count(),
                'low_stock' => $analytics->countLowStockProducts(),
                // Habis: punya varian TAPI tak satupun available > 0. whereHas('variants')
                // mencegah produk tanpa varian ikut terhitung "habis" (vacuous truth).
                'out_of_stock' => Product::whereHas('variants')
                    ->whereDoesntHave('variants', fn ($v) => $v->whereRaw('(stock - reserved_stock) > 0'))
                    ->count(),
            ],
        ]);
    }

    public function create()
    {
        $categories = Category::all();
        return Inertia::render('Admin/Products/Form', [
            'categories' => $categories,
            'product' => null
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:500',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'weight_gram' => 'required|integer|min:0',
            'is_active' => 'boolean'
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();

        $product = Product::create($validated);

        return redirect()->route('admin.products.show', $product->id)->with('success', 'Product created successfully. Now add variants and images.');
    }

    public function show(Product $product)
    {
        $product->load(['category', 'variants', 'images']);
        return Inertia::render('Admin/Products/Show', [
            'product' => $product
        ]);
    }

    public function edit(Product $product)
    {
        $categories = Category::all();
        return Inertia::render('Admin/Products/Form', [
            'categories' => $categories,
            'product' => $product
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:500',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'weight_gram' => 'required|integer|min:0',
            'is_active' => 'boolean'
        ]);

        if ($product->name !== $validated['name']) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();
        }

        $product->update($validated);

        return redirect()->route('admin.products.show', $product->id)->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Product deleted successfully.');
    }
}
