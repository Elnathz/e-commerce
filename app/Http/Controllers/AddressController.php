<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use App\Models\Province;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function getProvinces()
    {
        return response()->json(Province::orderBy('name')->get());
    }

    public function getCities($province_id)
    {
        return response()->json(City::where('province_id', $province_id)->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'city_id' => 'required|string|max:20',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'address_detail' => 'required|string',
            'is_default' => 'boolean',
        ]);

        $user = Auth::user();

        // If it's the first address or set as default, update others
        if ($validated['is_default'] ?? false || $user->addresses()->count() === 0) {
            $validated['is_default'] = true;
            $user->addresses()->update(['is_default' => false]);
        } else {
            $validated['is_default'] = false;
        }

        $user->addresses()->create($validated);

        return redirect()->back()->with('success', 'Alamat berhasil ditambahkan.');
    }

    public function update(Request $request, UserAddress $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'city_id' => 'required|string|max:20',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'address_detail' => 'required|string',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Auth::user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return redirect()->back()->with('success', 'Alamat berhasil diperbarui.');
    }

    public function destroy(UserAddress $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $newDefault = Auth::user()->addresses()->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return redirect()->back()->with('success', 'Alamat berhasil dihapus.');
    }

    public function setDefault(UserAddress $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        Auth::user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return redirect()->back()->with('success', 'Alamat utama berhasil diubah.');
    }
}
