<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|string|max:100|unique:product_variants',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|lt:price',
            'stock' => 'required|integer|min:0',
            'weight_gram' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);

        ProductVariant::create($validated);

        return redirect()->back()->with('success', 'Variant added successfully.');
    }

    public function update(Request $request, ProductVariant $variant)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:100|unique:product_variants,sku,' . $variant->id,
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|lt:price',
            'stock' => 'required|integer|min:0',
            'weight_gram' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);

        $variant->update($validated);

        return redirect()->back()->with('success', 'Variant updated successfully.');
    }

    public function destroy(ProductVariant $variant)
    {
        $variant->delete();
        return redirect()->back()->with('success', 'Variant deleted successfully.');
    }
}
