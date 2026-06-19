<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HeroSlideController extends Controller
{
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
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/HeroSlides/Form', [
            'slide' => null,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, true);
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
        ]);
    }

    public function update(Request $request, HeroSlide $heroSlide)
    {
        $data = $this->validateData($request, false);
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
