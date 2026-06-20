<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\FlashSaleItem;

class StorefrontController extends Controller
{
    public function index()
    {
        $heroMainBanners = HeroSlide::where('is_active', true)->where('placement', 'hero_main')
            ->orderBy('sort_order')->get(['id', 'image_path', 'title', 'cta_url', 'link_type', 'link_id'])
            ->map(fn ($s) => ['id' => $s->id, 'image_path' => $s->image_path, 'title' => $s->title, 'cta_url' => $s->destination_url]);

        $heroSideBanners = HeroSlide::where('is_active', true)->where('placement', 'hero_side')
            ->orderBy('sort_order')->take(4)->get(['id', 'image_path', 'title', 'cta_url', 'link_type', 'link_id'])
            ->map(fn ($s) => ['id' => $s->id, 'image_path' => $s->image_path, 'title' => $s->title, 'cta_url' => $s->destination_url]);

        // Kategori Populer = parent (hitung produk self+children) + gambar produk perwakilan.
        $popularCategories = \App\Models\Category::with('children:id,parent_id')
            ->where('is_active', true)->whereNull('parent_id')->get(['id', 'name'])
            ->map(function ($parent) {
                $catIds = $parent->children->pluck('id')->push($parent->id)->all();
                $count = \App\Models\Product::where('is_active', true)->whereIn('category_id', $catIds)->count();
                return ['id' => $parent->id, 'name' => $parent->name,
                        'product_count' => $count, 'image' => $this->representativeImage($catIds)];
            })->filter(fn ($c) => $c['product_count'] > 0)
              ->sortByDesc('product_count')->take(10)->values();

        // Lagi Banyak Dicari = subkategori + jumlah produk + gambar produk perwakilan.
        $banyakDicari = \App\Models\Category::where('is_active', true)->whereNotNull('parent_id')
            ->get(['id', 'name'])
            ->map(function ($child) {
                $count = \App\Models\Product::where('is_active', true)->where('category_id', $child->id)->count();
                return ['id' => $child->id, 'name' => $child->name,
                        'product_count' => $count, 'image' => $this->representativeImage([$child->id])];
            })->filter(fn ($c) => $c['product_count'] > 0)
              ->sortByDesc('product_count')->take(10)->values();

        $products = \App\Models\Product::with(['category', 'variants' => fn ($q) => $q->where('is_active', true),
            'images' => fn ($q) => $q->orderBy('is_primary', 'desc')->orderBy('sort_order')])
            ->where('is_active', true)->inRandomOrder()->take(12)->get();
        $products->each->setAppends(['price_display']);

        return Inertia::render('Storefront/Index', [
            'heroMainBanners' => $heroMainBanners,
            'heroSideBanners' => $heroSideBanners,
            'popularCategories' => $popularCategories,
            'banyakDicari' => $banyakDicari,
            'products' => $products,
            'flashSale' => $this->activeFlashSaleProducts(12),
        ]);
    }

    /**
     * Dedicated Flash Sale discovery page — all active flash-sale products.
     */
    public function flashSale()
    {
        return Inertia::render('Storefront/FlashSale', $this->activeFlashSaleProducts());
    }

    /**
     * Active flash-sale products for the storefront showcase + dedicated page.
     * One (cheapest) flash item per product, sold-out and out-of-window items
     * excluded. Each product carries `price_display` (resolves to the flash
     * price) and `flash_quota` ({sold, quota}) for the urgency progress bar.
     * `ends_at` is the SOONEST-ending active flash window (most urgent countdown).
     *
     * @return array{ends_at: ?string, products: \Illuminate\Support\Collection}
     */
    private function activeFlashSaleProducts(?int $limit = null): array
    {
        $now = now();

        $items = FlashSaleItem::query()
            ->whereHas('flashSale', fn ($q) => $q->activeAt($now))
            ->where(fn ($q) => $q->whereNull('quota')->orWhereColumn('sold_count', '<', 'quota'))
            ->with(['flashSale', 'variant'])
            ->orderBy('sale_price')
            ->get()
            ->filter(fn ($it) => $it->variant && $it->variant->is_active);

        // Cheapest flash item per product (already ordered by sale_price) + soonest end.
        $endsAt = null;
        $byProduct = [];
        foreach ($items as $it) {
            $productId = $it->variant->product_id;
            if (isset($byProduct[$productId])) {
                continue;
            }
            $byProduct[$productId] = $it;
            $end = $it->flashSale?->ends_at;
            if ($end && ($endsAt === null || $end->lt($endsAt))) {
                $endsAt = $end;
            }
        }

        if (empty($byProduct)) {
            return ['ends_at' => null, 'products' => collect()];
        }

        $products = Product::with([
                'category',
                'variants' => fn ($q) => $q->where('is_active', true),
                'images' => fn ($q) => $q->orderBy('is_primary', 'desc')->orderBy('sort_order'),
            ])
            ->whereIn('id', array_keys($byProduct))
            ->where('is_active', true)
            ->get()
            ->map(function ($p) use ($byProduct) {
                $p->setAppends(['price_display']);
                $it = $byProduct[$p->id];
                $p->flash_quota = [
                    'sold' => (int) $it->sold_count,
                    'quota' => $it->quota !== null ? (int) $it->quota : null,
                ];
                return $p;
            })
            ->sortBy(fn ($p) => (float) $byProduct[$p->id]->sale_price)
            ->values();

        if ($limit) {
            $products = $products->take($limit)->values();
        }

        return [
            'ends_at' => $endsAt?->toISOString(),
            'products' => $products,
        ];
    }

    /** Gambar (image_path relatif) dari produk aktif pertama yang punya gambar di kategori-kategori ini. */
    private function representativeImage(array $categoryIds): ?string
    {
        $product = \App\Models\Product::with(['images' => fn ($q) => $q->orderBy('is_primary', 'desc')->orderBy('sort_order')])
            ->where('is_active', true)->whereIn('category_id', $categoryIds)
            ->whereHas('images')->first();

        return $product?->images->first()?->image_path;
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

        // Task 8: tiap varian aktif membawa price_info (no fake discount).
        $product->setAppends(['price_display']);
        $product->variants->each->append('price_info');

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
        $relatedProducts->each->setAppends(['price_display']);

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

        // Category filter — sertakan produk dari subkategori bila parent dipilih (hierarki 2-level).
        // Produk ditugaskan ke kategori child (mis. Smartphone), jadi memilih parent (Elektronik)
        // harus ikut menyertakan id child-nya, kalau tidak hasilnya kosong.
        if ($request->filled('categories')) {
            $selected = (array) $request->categories;
            $childIds = \App\Models\Category::whereIn('parent_id', $selected)->pluck('id')->all();
            $query->whereIn('category_id', array_values(array_unique(array_merge($selected, $childIds))));
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
        $products->getCollection()->each->setAppends(['price_display']);

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
