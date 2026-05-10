<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    /**
     * Calculate shipping cost based on destination and weight
     */
    public function calculateShipping(Request $request)
    {
        $request->validate([
            'destination_city' => 'required',
            'weight' => 'required|numeric|min:1',
            'courier' => 'required|in:jne,pos,tiki,internal'
        ]);

        $originCityId = 399; // ID Kota Semarang (default store location)
        $destinationCityId = $request->destination_city;

        // If destination is Semarang, offer internal courier
        if ($destinationCityId == $originCityId) {
            return response()->json([
                'success' => true,
                'results' => [
                    [
                        'code' => 'internal',
                        'name' => 'Kurir Internal MegaMart',
                        'costs' => [
                            [
                                'service' => 'Same Day',
                                'description' => 'Pengiriman Langsung Area Semarang',
                                'cost' => [
                                    [
                                        'value' => 15000,
                                        'etd' => 'Hari ini',
                                        'note' => ''
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
        }

        $apiKey = env('RAJAONGKIR_API_KEY');
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API Key RajaOngkir belum dikonfigurasi'
            ], 500);
        }
        
        $response = Http::timeout(15)->withHeaders(['key' => $apiKey])
            ->post('https://api.rajaongkir.com/starter/cost', [
                'origin' => $originCityId,
                'destination' => $destinationCityId,
                'weight' => $request->weight,
                'courier' => $request->courier === 'internal' ? 'jne' : $request->courier
            ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'results' => $response->json('rajaongkir.results')
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data ongkos kirim. Pastikan tujuan dan berat valid.'
        ], 500);
    }
}
