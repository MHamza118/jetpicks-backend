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

        $orders = Order::where('status', 'PENDING')
            ->whereDoesntHave('picker')
            ->with(['orderer', 'items', 'offers'])
            ->where(function ($q) use ($searchTerm) {
                $q->whereHas('orderer', function ($sub) use ($searchTerm) {
                    $sub->where('full_name', 'LIKE', $searchTerm);
                })
                ->orWhereHas('items', function ($sub) use ($searchTerm) {
                    $sub->where('item_name', 'LIKE', $searchTerm);
                })
                ->orWhere('origin_city', 'LIKE', $searchTerm)
                ->orWhere('destination_city', 'LIKE', $searchTerm)
                ->orWhere('origin_country', 'LIKE', $searchTerm)
                ->orWhere('destination_country', 'LIKE', $searchTerm);
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

        $pickers = User::where('full_name', 'LIKE', $searchTerm)
            ->orWhere('email', 'LIKE', $searchTerm)
            ->with('travelJourneys')
            ->whereHas('travelJourneys', function ($q) use ($filters) {
                if (isset($filters['origin_city'])) {
                    $q->where('departure_city', 'LIKE', "%{$filters['origin_city']}%");
                }
                if (isset($filters['destination_city'])) {
                    $q->where('arrival_city', 'LIKE', "%{$filters['destination_city']}%");
                }
            });

        $pickers = $pickers->paginate($limit, ['*'], 'page', $page);

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
