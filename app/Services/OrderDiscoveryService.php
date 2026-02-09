<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TravelJourney;
use Illuminate\Database\Eloquent\Collection;

class OrderDiscoveryService
{
    public function getAvailableOrders(string $pickerId, int $page = 1, int $limit = 20): array
    {
        $journeys = TravelJourney::where('user_id', $pickerId)
            ->where('is_active', true)
            ->get();

        if ($journeys->isEmpty()) {
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

        $query = Order::where('status', 'PENDING')
            ->with(['orderer', 'items', 'offers'])
            ->whereDoesntHave('picker')
            ->has('items');

        $query->where(function ($q) use ($journeys) {
            foreach ($journeys as $journey) {
                $q->orWhere(function ($subQ) use ($journey) {
                    $subQ->whereRaw('LOWER(origin_city) = ?', [strtolower($journey->departure_city)])
                        ->whereRaw('LOWER(origin_country) = ?', [strtolower($journey->departure_country)])
                        ->whereRaw('LOWER(destination_city) = ?', [strtolower($journey->arrival_city)])
                        ->whereRaw('LOWER(destination_country) = ?', [strtolower($journey->arrival_country)]);
                });
            }
        });

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $orders = $query->offset($offset)
            ->limit($limit)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            $itemsCost = $order->items->sum(fn($item) => $item->price * $item->quantity);
            
            // Extract all product images from items
            $itemsImages = [];
            foreach ($order->items as $item) {
                if ($item->product_images && is_array($item->product_images)) {
                    $itemsImages = array_merge($itemsImages, $item->product_images);
                }
            }
            
            return [
                'id' => $order->id,
                'orderer' => [
                    'id' => $order->orderer->id,
                    'full_name' => $order->orderer->full_name,
                    'avatar_url' => $order->orderer->avatar_url,
                    'rating' => $this->getUserRating($order->orderer->id),
                ],
                'origin_city' => $order->origin_city,
                'origin_country' => $order->origin_country,
                'destination_city' => $order->destination_city,
                'destination_country' => $order->destination_country,
                'items_count' => $order->items->count(),
                'items_cost' => $itemsCost,
                'items_images' => $itemsImages,
                'reward_amount' => $order->reward_amount,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'earliest_delivery_date' => $order->created_at,
            ];
        });

        return [
            'data' => $formattedOrders->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    public function searchOrders(string $query, int $page = 1, int $limit = 20): array
    {
        $searchQuery = "%{$query}%";

        $orders = Order::where('status', 'PENDING')
            ->with(['orderer', 'items', 'offers'])
            ->whereDoesntHave('picker')
            ->has('items') // Only show orders that have items
            ->where(function ($q) use ($searchQuery) {
                $q->whereHas('orderer', function ($subQ) use ($searchQuery) {
                    $subQ->where('full_name', 'ILIKE', $searchQuery);
                })
                ->orWhereHas('items', function ($subQ) use ($searchQuery) {
                    $subQ->where('item_name', 'ILIKE', $searchQuery);
                })
                ->orWhereRaw('LOWER(origin_city) LIKE ?', [strtolower($searchQuery)])
                ->orWhereRaw('LOWER(destination_city) LIKE ?', [strtolower($searchQuery)])
                ->orWhereRaw('LOWER(origin_country) LIKE ?', [strtolower($searchQuery)])
                ->orWhereRaw('LOWER(destination_country) LIKE ?', [strtolower($searchQuery)]);
            });

        $total = $orders->count();
        $offset = ($page - 1) * $limit;

        $results = $orders->offset($offset)
            ->limit($limit)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedOrders = $results->map(function ($order) {
            $itemsCost = $order->items->sum(fn($item) => $item->price * $item->quantity);
            
            // Extract all product images from items
            $itemsImages = [];
            foreach ($order->items as $item) {
                if ($item->product_images && is_array($item->product_images)) {
                    $itemsImages = array_merge($itemsImages, $item->product_images);
                }
            }
            
            return [
                'id' => $order->id,
                'orderer' => [
                    'id' => $order->orderer->id,
                    'full_name' => $order->orderer->full_name,
                    'avatar_url' => $order->orderer->avatar_url,
                    'rating' => $this->getUserRating($order->orderer->id),
                ],
                'origin_city' => $order->origin_city,
                'origin_country' => $order->origin_country,
                'destination_city' => $order->destination_city,
                'destination_country' => $order->destination_country,
                'items_count' => $order->items->count(),
                'items_cost' => $itemsCost,
                'items_images' => $itemsImages,
                'reward_amount' => $order->reward_amount,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'earliest_delivery_date' => $order->created_at,
            ];
        });

        return [
            'data' => $formattedOrders->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
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
