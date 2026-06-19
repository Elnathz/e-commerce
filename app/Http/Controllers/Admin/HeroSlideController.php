<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class HeroSlideController extends Controller
{
    // Hero Utama = carousel (boleh banyak slide, dibatasi wajar untuk cegah bloat admin).
    // Banner Samping = grid statis 2 kiri + 2 kanan (lihat StorefrontController::take(4)) — lebih dari 4 tidak akan pernah tampil.
    private const MAX_PER_PLACEMENT = ['hero_main' => 10, 'hero_side' => 4];

    public function index()
    {
        $slides = HeroSlide::orderBy('sort_order')->get();

        $categoryIds = $slides->where('link_type', 'category')->pluck('link_id')->filter()->unique();
        $productIds = $slides->where('link_type', 'product')->pluck('link_id')->filter()->unique();
        $categoryNames = Category::whereIn('id', $categoryIds)->pluck('name', 'id');
        $productNames = Product::whereIn('id', $productIds)->pluck('name', 'id');

        $slides->each(function ($slide) use ($categoryNames, $productNames) {
            $slide->link_label = match ($slide->link_type) {
                'category' => $categoryNames[$slide->link_id] ?? null,
                'product' => $productNames[$slide->link_id] ?? null,
                'custom' => $slide->cta_url,
                default => null,
            };
        });

        return Inertia::render('Admin/HeroSlides/Index', [
            'slides' => $slides,
            'stats' => [
                'total' => HeroSlide::count(),
                'active' => HeroSlide::where('is_active', true)->count(),
            ],
            'limits' => self::MAX_PER_PLACEMENT,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/HeroSlides/Form', [
            'slide' => null,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'counts' => $this->placementCounts(),
            'limits' => self::MAX_PER_PLACEMENT,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, true);
        $this->guardPlacementLimit($data['placement'], null);
        $data['image_path'] = $request->file('image')->store('hero-slides', 'public');
        HeroSlide::create($data);
        return redirect()->route('admin.hero-slides.index')->with('success', 'Slide ditambahkan.');
    }

    public function edit(HeroSlide $heroSlide)
    {
        return Inertia::render('Admin/HeroSlides/Form', [
            'slide' => $heroSlide,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'counts' => $this->placementCounts($heroSlide),
            'limits' => self::MAX_PER_PLACEMENT,
        ]);
    }

    public function update(Request $request, HeroSlide $heroSlide)
    {
        $data = $this->validateData($request, false);
        $this->guardPlacementLimit($data['placement'], $heroSlide);
        if ($request->hasFile('image')) {
            // hanya hapus file lama bila bukan aset publik bawaan (yang prefix "images/")
            if ($heroSlide->image_path && ! str_starts_with($heroSlide->image_path, 'images/')) {
                Storage::disk('public')->delete($heroSlide->image_path);
            }
            $data['image_path'] = $request->file('image')->store('hero-slides', 'public');
        }
        $heroSlide->update($data);
        return redirect()->route('admin.hero-slides.index')->with('success', 'Slide diperbarui.');
    }

    public function destroy(HeroSlide $heroSlide)
    {
        if ($heroSlide->image_path && ! str_starts_with($heroSlide->image_path, 'images/')) {
            Storage::disk('public')->delete($heroSlide->image_path);
        }
        $heroSlide->delete();
        return redirect()->route('admin.hero-slides.index')->with('success', 'Slide dihapus.');
    }

    /** Hitung jumlah slide per placement, opsional kecualikan slide yang sedang diedit. */
    private function placementCounts(?HeroSlide $excluding = null): array
    {
        $counts = array_fill_keys(array_keys(self::MAX_PER_PLACEMENT), 0);
        $query = HeroSlide::query();
        if ($excluding) {
            $query->where('id', '!=', $excluding->id);
        }
        foreach ($query->select('placement')->get()->countBy('placement') as $placement => $count) {
            $counts[$placement] = $count;
        }
        return $counts;
    }

    private function guardPlacementLimit(string $placement, ?HeroSlide $excluding): void
    {
        // Slide yang tetap di placement-nya sendiri tidak menambah jumlah slot terpakai —
        // jangan blokir edit no-op meski data lama sudah kebetulan melebihi cap saat ini.
        if ($excluding && $excluding->placement === $placement) {
            return;
        }
        $max = self::MAX_PER_PLACEMENT[$placement] ?? null;
        if ($max === null) {
            return;
        }
        if (HeroSlide::where('placement', $placement)->count() >= $max) {
            throw ValidationException::withMessages([
                'placement' => "Maksimal {$max} slide untuk posisi ini. Hapus slide lain dahulu sebelum menambah yang baru.",
            ]);
        }
    }

    private function validateData(Request $request, bool $imageRequired): array
    {
        return $request->validate([
            'placement' => 'required|in:hero_main,hero_side',
            'title' => 'required|string|max:255',
            'link_type' => 'nullable|in:category,product,custom',
            'link_id' => 'nullable|integer|required_if:link_type,category,product',
            'cta_url' => 'nullable|string|max:500|required_if:link_type,custom',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'image' => ($imageRequired ? 'required' : 'nullable') . '|image|max:2048',
        ]);
    }
}
