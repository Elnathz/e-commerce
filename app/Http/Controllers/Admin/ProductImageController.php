<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'images.*' => 'required|image|max:2048'
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                
                // Set as primary if it's the first image for this product and it's not tied to a variant
                $isPrimary = false;
                if (!$request->product_variant_id) {
                    $isPrimary = ProductImage::where('product_id', $request->product_id)
                                             ->whereNull('product_variant_id')
                                             ->count() === 0;
                }

                ProductImage::create([
                    'product_id' => $request->product_id,
                    'product_variant_id' => $request->product_variant_id,
                    'image_path' => $path,
                    'is_primary' => $isPrimary,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Images uploaded successfully.');
    }

    public function update(Request $request, ProductImage $image)
    {
        // This is mainly used to set an image as primary
        $request->validate([
            'is_primary' => 'required|boolean'
        ]);

        if ($request->is_primary) {
            // Remove primary status from other images of this product
            ProductImage::where('product_id', $image->product_id)
                ->update(['is_primary' => false]);
            
            $image->update(['is_primary' => true]);
        }

        return redirect()->back()->with('success', 'Image updated successfully.');
    }

    public function destroy(ProductImage $image)
    {
        Storage::disk('public')->delete($image->image_path);
        
        $productId = $image->product_id;
        $wasPrimary = $image->is_primary;
        
        $image->delete();

        // If we deleted the primary image, randomly assign a new one if any exist
        if ($wasPrimary) {
            $newPrimary = ProductImage::where('product_id', $productId)->first();
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        return redirect()->back()->with('success', 'Image deleted successfully.');
    }
}
