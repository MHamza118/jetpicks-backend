<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TravelJourney;

class DashboardService
{
    protected OrderDiscoveryService $orderDiscoveryService;

    public function __construct(OrderDiscoveryService $orderDiscoveryService)
    {
        $this->orderDiscoveryService = $orderDiscoveryService;
    }

    public function getPickerDashboard(string $pickerId, int $page = 1, int $limit = 20): array
    {
        $availableOrders = $this->orderDiscoveryService->getAvailableOrders($pickerId, $page, $limit);
        $journeys = TravelJourney::where('user_id', $pickerId)
            ->where('is_active', true)
            ->orderBy('departure_date', 'asc')
            ->get()
            ->map(function ($journey) {
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
            });

        $stats = [
            'total_available_orders' => $availableOrders['pagination']['total'],
            'active_journeys' => $journeys->count(),
            'completed_deliveries' => Order::where('assigned_picker_id', $pickerId)
                ->where('status', 'COMPLETED')
                ->count(),
        ];

        return [
            'available_orders' => $availableOrders,
            'travel_journeys' => $journeys->toArray(),
            'statistics' => $stats,
        ];
    }

    public function getOrdererDashboard(string $ordererId, int $page = 1, int $limit = 20): array
    {
        $recentOrders = Order::where('orderer_id', $ordererId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $query = TravelJourney::with('user')
            ->where('is_active', true)
            ->where('arrival_date', '>=', now()->toDateString())
            ->orderBy('departure_date', 'asc');

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $pickers = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($journey) {
                return [
                    'id' => $journey->id,
                    'picker' => [
                        'id' => $journey->user->id,
                        'full_name' => $journey->user->full_name,
                        'avatar_url' => $journey->user->avatar_url,
                        'rating' => $this->getUserRating($journey->user->id),
                        'completed_deliveries' => Order::where('assigned_picker_id', $journey->user->id)
                            ->where('status', 'COMPLETED')
                            ->count(),
                    ],
                    'departure_country' => $journey->departure_country,
                    'departure_city' => $journey->departure_city,
                    'departure_date' => $journey->departure_date,
                    'arrival_country' => $journey->arrival_country,
                    'arrival_city' => $journey->arrival_city,
                    'arrival_date' => $journey->arrival_date,
                    'luggage_weight_capacity' => $journey->luggage_weight_capacity,
                ];
            });

        $stats = [
            'total_available_pickers' => $total,
            'active_orders' => Order::where('orderer_id', $ordererId)
                ->where('status', 'PENDING')
                ->count(),
            'completed_orders' => Order::where('orderer_id', $ordererId)
                ->where('status', 'COMPLETED')
                ->count(),
        ];

        return [
            'available_pickers' => [
                'data' => $pickers->toArray(),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'has_more' => ($offset + $limit) < $total,
                ],
            ],
            'recent_orders' => $recentOrders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'destination_city' => $order->destination_city,
                    'reward_amount' => $order->reward_amount,
                ];
            })->toArray(),
            'statistics' => $stats,
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
