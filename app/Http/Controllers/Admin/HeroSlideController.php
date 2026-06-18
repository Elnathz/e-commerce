<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HeroSlideController extends Controller
{
    public function index()
    {
        $slides = HeroSlide::orderBy('sort_order')->get();
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
        return Inertia::render('Admin/HeroSlides/Form', ['slide' => null]);
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
        return Inertia::render('Admin/HeroSlides/Form', ['slide' => $heroSlide]);
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
            'subtitle' => 'nullable|string|max:255',
            'badge_label' => 'nullable|string|max:100',
            'cta_label' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'image' => ($imageRequired ? 'required' : 'nullable') . '|image|max:2048',
        ]);
    }
}
