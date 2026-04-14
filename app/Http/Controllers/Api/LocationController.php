<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class LocationController extends Controller
{
    private const COUNTRIES_NOW_API = 'https://countriesnow.space/api/v0.1/countries';
    private const CITIES_NOW_API = 'https://countriesnow.space/api/v0.1/countries/cities';

    public function getCountries(): JsonResponse
    {
        try {
            $response = Http::get(self::COUNTRIES_NOW_API);
            
            if (!$response->successful()) {
                return response()->json(['error' => 'Failed to fetch countries'], 500);
            }

            $data = $response->json();
            
            if (!isset($data['data']) || !is_array($data['data'])) {
                return response()->json(['error' => 'Invalid response format'], 500);
            }

            $countries = [];
            foreach ($data['data'] as $country) {
                if (isset($country['country']) && isset($country['iso2'])) {
                    $countries[] = [
                        'name' => $country['country'],
                        'code' => $country['iso2'],
                    ];
                }
            }

            return response()->json(['data' => $countries]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch countries'], 500);
        }
    }

    public function getCities(\Illuminate\Http\Request $request): JsonResponse
    {
        $countryName = $request->input('country');

        if (!$countryName) {
            return response()->json(['error' => 'Country name is required'], 400);
        }

        try {
            $response = Http::timeout(10)->post(self::CITIES_NOW_API, [
                'country' => $countryName,
            ]);

            if (!$response->successful()) {
                \Log::warning('CountriesNow city lookup failed', [
                    'country' => $countryName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Fail gracefully so journey setup can continue with manual/Any City selection.
                return response()->json(['data' => []]);
            }

            $data = $response->json();

            if (!isset($data['data']) || !is_array($data['data'])) {
                return response()->json(['data' => []]);
            }

            $cities = $data['data'];

            return response()->json(['data' => $cities]);
        } catch (\Exception $e) {
            \Log::warning('CountriesNow city lookup exception', [
                'country' => $countryName,
                'error' => $e->getMessage(),
            ]);

            // Fail gracefully instead of returning 500.
            return response()->json(['data' => []]);
        }
    }
}
