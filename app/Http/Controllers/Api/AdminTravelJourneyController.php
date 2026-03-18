<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelJourney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTravelJourneyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TravelJourney::with('user');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('is_active', $request->status === 'active' ? 1 : 0);
        }

        $journeys = $query->orderBy('departure_date', 'desc')
                          ->paginate($request->get('per_page', 15));

        $items = collect($journeys->items())->map(function ($journey) {
            return [
                'id' => $journey->id,
                'picker' => $journey->user ? [
                    'id' => $journey->user->id,
                    'full_name' => $journey->user->full_name,
                    'email' => $journey->user->email,
                ] : null,
                'departure_country' => $journey->departure_country,
                'departure_city' => $journey->departure_city,
                'departure_date' => $journey->departure_date,
                'arrival_country' => $journey->arrival_country,
                'arrival_city' => $journey->arrival_city,
                'arrival_date' => $journey->arrival_date,
                'luggage_weight_capacity' => $journey->luggage_weight_capacity,
                'is_active' => $journey->is_active,
                'created_at' => $journey->created_at,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $journeys->currentPage(),
                'last_page' => $journeys->lastPage(),
                'per_page' => $journeys->perPage(),
                'total' => $journeys->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $journey = TravelJourney::with('user')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $journey->id,
                'picker' => $journey->user ? [
                    'id' => $journey->user->id,
                    'full_name' => $journey->user->full_name,
                    'email' => $journey->user->email,
                ] : null,
                'departure_country' => $journey->departure_country,
                'departure_city' => $journey->departure_city,
                'departure_date' => $journey->departure_date,
                'arrival_country' => $journey->arrival_country,
                'arrival_city' => $journey->arrival_city,
                'arrival_date' => $journey->arrival_date,
                'luggage_weight_capacity' => $journey->luggage_weight_capacity,
                'is_active' => $journey->is_active,
                'created_at' => $journey->created_at,
            ],
        ]);
    }
}
