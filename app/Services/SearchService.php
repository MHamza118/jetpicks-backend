<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\TravelJourney;

class SearchService
{
    public function searchUsers(string $query, int $page = 1, int $limit = 20): array
    {
        $searchTerm = "%{$query}%";

        $users = User::where('full_name', 'LIKE', $searchTerm)
            ->orWhere('email', 'LIKE', $searchTerm)
            ->paginate($limit, ['*'], 'page', $page);

        return $this->formatPagination($users, $this->formatUser($users));
    }

    public function searchOrders(string $query, array $filters = [], int $page = 1, int $limit = 20): array
    {
        $searchTerm = "%{$query}%";
        $lowerSearchTerm = strtolower($query);

        $orders = Order::where('status', 'PENDING')
            ->whereDoesntHave('picker')
            ->with(['orderer', 'items', 'offers'])
            ->where(function ($q) use ($lowerSearchTerm) {
                $q->whereHas('orderer', function ($sub) use ($lowerSearchTerm) {
                    $sub->whereRaw('LOWER(full_name) LIKE ?', ["%{$lowerSearchTerm}%"]);
                })
                ->orWhereHas('items', function ($sub) use ($lowerSearchTerm) {
                    $sub->whereRaw('LOWER(item_name) LIKE ?', ["%{$lowerSearchTerm}%"]);
                })
                ->orWhereRaw('LOWER(origin_city) LIKE ?', ["%{$lowerSearchTerm}%"])
                ->orWhereRaw('LOWER(destination_city) LIKE ?', ["%{$lowerSearchTerm}%"])
                ->orWhereRaw('LOWER(origin_country) LIKE ?', ["%{$lowerSearchTerm}%"])
                ->orWhereRaw('LOWER(destination_country) LIKE ?', ["%{$lowerSearchTerm}%"]);
            });

        if (isset($filters['status'])) {
            $orders->where('status', $filters['status']);
        }

        if (isset($filters['min_reward'])) {
            $orders->where('reward_amount', '>=', $filters['min_reward']);
        }

        if (isset($filters['max_reward'])) {
            $orders->where('reward_amount', '<=', $filters['max_reward']);
        }

        $orders = $orders->paginate($limit, ['*'], 'page', $page);

        return $this->formatPagination($orders, $this->formatOrder($orders));
    }

    public function searchPickers(string $query, array $filters = [], int $page = 1, int $limit = 20): array
    {
        $searchTerm = "%{$query}%";
        $lowerSearchTerm = strtolower($query);

        $pickers = User::with('travelJourneys')
            ->where(function ($q) use ($searchTerm, $lowerSearchTerm) {
                // Search by picker name or email (case-insensitive)
                $q->whereRaw('LOWER(full_name) LIKE ?', ["%{$lowerSearchTerm}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$lowerSearchTerm}%"]);
            })
            ->orWhere(function ($q) use ($lowerSearchTerm) {
                // Search by travel journey cities and countries (case-insensitive)
                $q->whereHas('travelJourneys', function ($sub) use ($lowerSearchTerm) {
                    $sub->whereRaw('LOWER(departure_city) LIKE ?', ["%{$lowerSearchTerm}%"])
                        ->orWhereRaw('LOWER(departure_country) LIKE ?', ["%{$lowerSearchTerm}%"])
                        ->orWhereRaw('LOWER(arrival_city) LIKE ?', ["%{$lowerSearchTerm}%"])
                        ->orWhereRaw('LOWER(arrival_country) LIKE ?', ["%{$lowerSearchTerm}%"]);
                });
            })
            ->with('travelJourneys');

        // Apply additional filters if provided
        if (isset($filters['origin_city'])) {
            $originCityLower = strtolower($filters['origin_city']);
            $pickers->whereHas('travelJourneys', function ($q) use ($originCityLower) {
                $q->whereRaw('LOWER(departure_city) LIKE ?', ["%{$originCityLower}%"]);
            });
        }
        if (isset($filters['destination_city'])) {
            $destinationCityLower = strtolower($filters['destination_city']);
            $pickers->whereHas('travelJourneys', function ($q) use ($destinationCityLower) {
                $q->whereRaw('LOWER(arrival_city) LIKE ?', ["%{$destinationCityLower}%"]);
            });
        }

        $pickers = $pickers->distinct()->paginate($limit, ['*'], 'page', $page);

        return $this->formatPagination($pickers, $this->formatPicker($pickers));
    }

    private function formatUser($users): array
    {
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'rating' => $this->getUserRating($user->id),
            ];
        })->toArray();
    }

    private function formatOrder($orders): array
    {
        return $orders->map(function ($order) {
            $itemsCost = $order->items->sum(fn($item) => $item->price * $item->quantity);

            return [
                'id' => $order->id,
                'orderer' => [
                    'id' => $order->orderer->id,
                    'full_name' => $order->orderer->full_name,
                    'avatar_url' => $order->orderer->avatar_url,
                ],
                'origin_city' => $order->origin_city,
                'origin_country' => $order->origin_country,
                'destination_city' => $order->destination_city,
                'destination_country' => $order->destination_country,
                'items_count' => $order->items->count(),
                'items_cost' => $itemsCost,
                'reward_amount' => $order->reward_amount,
                'status' => $order->status,
                'created_at' => $order->created_at,
            ];
        })->toArray();
    }

    private function formatPicker($pickers): array
    {
        return $pickers->map(function ($picker) {
            $completedOrders = Order::where('assigned_picker_id', $picker->id)
                ->where('status', 'COMPLETED')
                ->count();

            return [
                'id' => $picker->id,
                'full_name' => $picker->full_name,
                'avatar_url' => $picker->avatar_url,
                'rating' => $this->getUserRating($picker->id),
                'completed_orders' => $completedOrders,
                'journeys_count' => $picker->travelJourneys->count(),
                'travelJourneys' => $picker->travelJourneys->map(function ($journey) {
                    return [
                        'id' => $journey->id,
                        'departure_country' => $journey->departure_country,
                        'departure_city' => $journey->departure_city,
                        'departure_date' => $journey->departure_date,
                        'arrival_country' => $journey->arrival_country,
                        'arrival_city' => $journey->arrival_city,
                        'arrival_date' => $journey->arrival_date,
                        'luggage_weight_capacity' => $journey->luggage_weight_capacity,
                    ];
                })->toArray(),
            ];
        })->toArray();
    }

    private function formatPagination($paginated, array $data): array
    {
        return [
            'data' => $data,
            'pagination' => [
                'total' => $paginated->total(),
                'page' => $paginated->currentPage(),
                'limit' => $paginated->perPage(),
                'has_more' => $paginated->hasMorePages(),
            ],
        ];
    }

    private function getUserRating(string $userId): float
    {
        $avgRating = \DB::table('reviews')
            ->where('reviewee_id', $userId)
            ->avg('rating');

        return round($avgRating ?? 0, 1);
    }
}
