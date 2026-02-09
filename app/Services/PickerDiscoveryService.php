<?php

namespace App\Services;

use App\Models\TravelJourney;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PickerDiscoveryService
{
    public function getAvailablePickers(string $orderId, int $page = 1, int $limit = 20): array
    {
        $order = \App\Models\Order::find($orderId);
        
        if (!$order) {
            return [
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit,
                    'has_more' => false,
                ],
            ];
        }

        $query = TravelJourney::where('departure_city', $order->origin_city)
            ->where('departure_country', $order->origin_country)
            ->where('arrival_city', $order->destination_city)
            ->where('arrival_country', $order->destination_country)
            ->where('is_active', true)
            ->with(['user'])
            ->whereHas('user', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('roles', 'like', '%PICKER%');
                });
            });
        $total = $query->count();
        $offset = ($page - 1) * $limit;
        $journeys = $query->offset($offset)
            ->limit($limit)
            ->orderBy('created_at', 'desc')
            ->get();
        $formattedPickers = $journeys->map(function ($journey) {
            $picker = $journey->user;
            $completedOrders = \App\Models\Order::where('assigned_picker_id', $picker->id)
                ->where('status', 'COMPLETED')
                ->count();
            return [
                'id' => $picker->id,
                'full_name' => $picker->full_name,
                'avatar_url' => $picker->avatar_url,
                'rating' => $this->getUserRating($picker->id),
                'completed_deliveries' => $completedOrders,
                'languages' => $picker->languages->pluck('language_name')->toArray(),
                'travel_journey' => [
                    'id' => $journey->id,
                    'departure_city' => $journey->departure_city,
                    'departure_country' => $journey->departure_country,
                    'arrival_city' => $journey->arrival_city,
                    'arrival_country' => $journey->arrival_country,
                    'departure_date' => $journey->departure_date,
                    'arrival_date' => $journey->arrival_date,
                    'luggage_weight_capacity' => $journey->luggage_weight_capacity,
                ],
            ];
        });
        return [
            'data' => $formattedPickers->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    public function getPickerDetails(string $pickerId): array
    {
        $picker = User::find($pickerId);

        if (!$picker || !in_array('PICKER', $picker->roles)) {
            return [];
        }

        $completedOrders = \App\Models\Order::where('assigned_picker_id', $pickerId)
            ->where('status', 'COMPLETED')
            ->count();

        $travelJourneys = TravelJourney::where('user_id', $pickerId)
            ->where('is_active', true)
            ->orderBy('departure_date', 'desc')
            ->get()
            ->map(function ($journey) {
                return [
                    'id' => $journey->id,
                    'departure_city' => $journey->departure_city,
                    'departure_country' => $journey->departure_country,
                    'arrival_city' => $journey->arrival_city,
                    'arrival_country' => $journey->arrival_country,
                    'departure_date' => $journey->departure_date,
                    'arrival_date' => $journey->arrival_date,
                    'luggage_weight_capacity' => $journey->luggage_weight_capacity,
                ];
            });

        return [
            'id' => $picker->id,
            'full_name' => $picker->full_name,
            'email' => $picker->email,
            'phone_number' => $picker->phone_number,
            'country' => $picker->country,
            'avatar_url' => $picker->avatar_url,
            'rating' => $this->getUserRating($pickerId),
            'completed_deliveries' => $completedOrders,
            'languages' => $picker->languages->pluck('language_name')->toArray(),
            'travel_journeys' => $travelJourneys->toArray(),
            'created_at' => $picker->created_at,
        ];
    }
    //Search pickers by name or location
    public function searchPickers(string $query, int $page = 1, int $limit = 20): array
    {
        $searchQuery = "%{$query}%";

        $pickers = User::where(function ($q) use ($searchQuery) {
                $q->where('roles', 'like', '%PICKER%');
            })
            ->where(function ($q) use ($searchQuery) {
                $q->where('full_name', 'LIKE', $searchQuery)
                    ->orWhere('country', 'LIKE', $searchQuery);
            })
            ->with(['languages', 'travelJourneys'])
            ->orderBy('created_at', 'desc');

        $total = $pickers->count();
        $offset = ($page - 1) * $limit;
        $results = $pickers->offset($offset)
            ->limit($limit)
            ->get();
        $formattedPickers = $results->map(function ($picker) {
            $completedOrders = \App\Models\Order::where('assigned_picker_id', $picker->id)
                ->where('status', 'COMPLETED')
                ->count();
            return [
                'id' => $picker->id,
                'full_name' => $picker->full_name,
                'avatar_url' => $picker->avatar_url,
                'rating' => $this->getUserRating($picker->id),
                'completed_deliveries' => $completedOrders,
                'languages' => $picker->languages->pluck('language_name')->toArray(),
                'country' => $picker->country,
            ];
        });
        return [
            'data' => $formattedPickers->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }
    //User Ratings
    private function getUserRating(string $userId): float
    {
        $avgRating = \DB::table('reviews')
            ->where('reviewee_id', $userId)
            ->avg('rating');

        return round($avgRating ?? 0, 1);
    }
}
