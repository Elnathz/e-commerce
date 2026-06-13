<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Category;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query()->withCount(['products', 'children']);

        if ($request->filled('q')) {
            // Mode search: hasil FLAT (parent & child yang match), bukan hierarki.
            $query->where('name', 'like', '%' . $request->q . '%')->orderBy('name');
        } else {
            // Mode hierarki existing.
            $query->whereNull('parent_id')
                ->with(['children' => fn ($c) => $c->withCount('products')])
                ->orderBy('name');
        }

        $categories = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['q']),
            'stats' => [
                'total' => Category::count(),
                'active' => Category::where('is_active', true)->count(),
                'subcategories' => Category::whereNotNull('parent_id')->count(),
            ],
        ]);
    }

    public function create()
    {
        $parentCategories = Category::whereNull('parent_id')->get();
        return Inertia::render('Admin/Categories/Form', [
            'parentCategories' => $parentCategories,
            'category' => null
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean'
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('categories', 'public');
        }

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category)
    {
        $parentCategories = Category::whereNull('parent_id')->where('id', '!=', $category->id)->get();
        return Inertia::render('Admin/Categories/Form', [
            'parentCategories' => $parentCategories,
            'category' => $category
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean'
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if ($request->hasFile('image')) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        // GUARD (invariant data-loss; lapisan terakhir, non-rekursif): tolak bila punya produk
        // langsung ATAU subkategori langsung. Cegah cascade FK menghapus produk diam-diam.
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->with('error',
                'Tidak bisa menghapus "' . $category->name . '": masih ada produk atau subkategori. Pindahkan atau hapus dulu.');
        }

        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }
}
