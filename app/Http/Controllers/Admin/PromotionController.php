<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::withCount(['usages' => function ($query) {
            $query->whereIn('status', ['reserved', 'confirmed']);
        }])->latest()->paginate(20);

        return Inertia::render('Admin/Promotions/Index', [
            'promotions' => $promotions,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Promotions/Form', [
            'promotion' => new Promotion(['type' => 'percentage', 'applicable_shipping_type' => 'all']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'                     => 'required|string|unique:promotions,code|max:50',
            'name'                     => 'required|string|max:255',
            'type'                     => 'required|in:percentage,fixed_amount,free_shipping',
            'value'                    => 'required_unless:type,free_shipping|numeric|min:0',
            'min_purchase'             => 'required|numeric|min:0',
            'max_usage'                => 'nullable|integer|min:1',
            'max_usage_per_user'       => 'nullable|integer|min:1',
            'max_shipping_discount'    => 'nullable|numeric|min:0',
            'applicable_shipping_type' => 'required|in:all,internal,external',
            'valid_from'               => 'nullable|date',
            'valid_until'              => 'nullable|date|after_or_equal:valid_from',
            'description'              => 'nullable|string',
            'is_active'                => 'boolean',
        ]);

        $data['code'] = Str::upper($data['code']);
        if ($data['type'] === 'free_shipping' && empty($data['value'])) {
            $data['value'] = 0;
        }

        Promotion::create($data);

        return redirect()->route('admin.promotions.index')->with('success', 'Voucher berhasil dibuat.');
    }

    public function edit(Promotion $promotion)
    {
        return Inertia::render('Admin/Promotions/Form', [
            'promotion' => $promotion,
        ]);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $data = $request->validate([
            'name'                     => 'required|string|max:255',
            'type'                     => 'required|in:percentage,fixed_amount,free_shipping',
            'value'                    => 'required_unless:type,free_shipping|numeric|min:0',
            'min_purchase'             => 'required|numeric|min:0',
            'max_usage'                => 'nullable|integer|min:1',
            'max_usage_per_user'       => 'nullable|integer|min:1',
            'max_shipping_discount'    => 'nullable|numeric|min:0',
            'applicable_shipping_type' => 'required|in:all,internal,external',
            'valid_from'               => 'nullable|date',
            'valid_until'              => 'nullable|date|after_or_equal:valid_from',
            'description'              => 'nullable|string',
            'is_active'                => 'boolean',
        ]);

        if ($data['type'] === 'free_shipping' && empty($data['value'])) {
            $data['value'] = 0;
        }

        $promotion->update($data);

        return redirect()->route('admin.promotions.index')->with('success', 'Voucher berhasil diperbarui.');
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();
        return redirect()->route('admin.promotions.index')->with('success', 'Voucher berhasil dihapus.');
    }

    public function history(Promotion $promotion)
    {
        $histories = $promotion->histories()->with('admin')->latest('created_at')->get();
        
        return Inertia::render('Admin/Promotions/History', [
            'promotion' => $promotion,
            'histories' => $histories,
        ]);
    }
}
