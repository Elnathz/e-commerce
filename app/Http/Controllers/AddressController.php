<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use App\Models\Province;
use App\Models\City;
use App\Models\District;
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

    public function getDistricts($city_id)
    {
        // All districts are seeded locally from the master dataset.
        // No JIT API fetching needed.
        $districts = District::where('city_id', $city_id)->orderBy('name')->get();
        return response()->json($districts);
    }

    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:100',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'city_id' => 'required|exists:cities,id',
            'district_id' => 'required|exists:districts,id',
            'postal_code' => 'nullable|string|max:10',
            'address_detail' => 'required|string',
            'is_default' => 'boolean',
        ]);

        $cityModel = City::with('province')->findOrFail($request->city_id);
        $districtModel = District::findOrFail($request->district_id);

        $validated = [
            'label' => $request->label,
            'recipient_name' => $request->recipient_name,
            'phone' => $request->phone,
            'province' => $cityModel->province->name,
            'city' => $cityModel->type . ' ' . $cityModel->name,
            'city_id' => $cityModel->id,
            'district' => $districtModel->name,
            'district_id' => $districtModel->id,
            'postal_code' => $cityModel->postal_code ?: $request->postal_code,
            'address_detail' => $request->address_detail,
            'is_default' => $request->is_default ?? false,
        ];

        $user = Auth::user();

        // If it's the first address or set as default, update others
        if ($validated['is_default'] || $user->addresses()->count() === 0) {
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

        $request->validate([
            'label' => 'required|string|max:100',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'city_id' => 'required|exists:cities,id',
            'district_id' => 'required|exists:districts,id',
            'postal_code' => 'nullable|string|max:10',
            'address_detail' => 'required|string',
            'is_default' => 'boolean',
        ]);

        $cityModel = City::with('province')->findOrFail($request->city_id);
        $districtModel = District::findOrFail($request->district_id);

        $validated = [
            'label' => $request->label,
            'recipient_name' => $request->recipient_name,
            'phone' => $request->phone,
            'province' => $cityModel->province->name,
            'city' => $cityModel->type . ' ' . $cityModel->name,
            'city_id' => $cityModel->id,
            'district' => $districtModel->name,
            'district_id' => $districtModel->id,
            'postal_code' => $cityModel->postal_code ?: $request->postal_code,
            'address_detail' => $request->address_detail,
            'is_default' => $request->is_default ?? false,
        ];

        if ($validated['is_default']) {
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
