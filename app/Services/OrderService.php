<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Offer;
use Illuminate\Pagination\Paginator;

class OrderService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function createOrder(string $ordererId, array $data): Order
    {
        $order = Order::create([
            'orderer_id' => $ordererId,
            'assigned_picker_id' => $data['picker_id'] ?? null,
            'origin_country' => $data['origin_country'],
            'origin_city' => $data['origin_city'],
            'destination_country' => $data['destination_country'],
            'destination_city' => $data['destination_city'],
            'special_notes' => $data['special_notes'] ?? null,
            'reward_amount' => 0,
            'status' => $data['status'] ?? 'DRAFT',
        ]);

        return $order;
    }

    public function addOrderItem(string $orderId, array $data): OrderItem
    {
        $imageUrls = [];
        if (isset($data['product_images']) && is_array($data['product_images'])) {
            foreach ($data['product_images'] as $image) {
                if ($image instanceof \Illuminate\Http\UploadedFile) {
                    $path = $image->store('order-items', 'public');
                    $imageUrls[] = '/storage/' . $path;
                }
            }
        }

        return OrderItem::create([
            'order_id' => $orderId,
            'item_name' => $data['item_name'],
            'weight' => $data['weight'],
            'price' => $data['price'],
            'quantity' => $data['quantity'] ?? 1,
            'special_notes' => $data['special_notes'] ?? null,
            'store_link' => $data['store_link'] ?? null,
            'product_images' => !empty($imageUrls) ? $imageUrls : null,
        ]);
    }

    public function setReward(string $orderId, float $rewardAmount): Order
    {
        $order = Order::findOrFail($orderId);
        
        $order->update([
            'reward_amount' => $rewardAmount,
        ]);

        Offer::create([
            'order_id' => $orderId,
            'offered_by_user_id' => $order->orderer_id,
            'offer_type' => 'INITIAL',
            'offer_amount' => $rewardAmount,
            'status' => 'PENDING',
        ]);

        return $order;
    }

    public function finalizeOrder(string $orderId): Order
    {
        $order = Order::findOrFail($orderId);
        
        $order->update([
            'status' => 'PENDING',
        ]);

        return $order;
    }

    public function getOrdererOrders(string $ordererId, ?string $status = null, int $page = 1, int $limit = 20): array
    {
        $query = Order::where('orderer_id', $ordererId)
            ->with(['items', 'picker', 'offers']);

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $orders = $query->offset($offset)
            ->limit($limit)
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            $itemsCost = $order->items->sum(fn($item) => $item->price * $item->quantity);
            
            return [
                'id' => $order->id,
                'picker_id' => $order->assigned_picker_id,
                'origin_city' => $order->origin_city,
                'destination_city' => $order->destination_city,
                'status' => $order->status,
                'items_count' => $order->items->count(),
                'total_cost' => $itemsCost,
                'created_at' => $order->created_at,
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

    public function getOrderDetails(string $orderId): array
    {
        $order = Order::with(['items', 'orderer', 'picker', 'offers'])
            ->findOrFail($orderId);

        $itemsCost = $order->items->sum(fn($item) => $item->price * $item->quantity);

        // Get chat room if order is accepted
        $chatRoom = null;
        if ($order->status === 'ACCEPTED') {
            $chatRoom = \App\Models\ChatRoom::where('order_id', $orderId)->first();
        }

        return [
            'id' => $order->id,
            'orderer_id' => $order->orderer_id,
            'assigned_picker_id' => $order->assigned_picker_id,
            'origin_country' => $order->origin_country,
            'origin_city' => $order->origin_city,
            'destination_country' => $order->destination_country,
            'destination_city' => $order->destination_city,
            'special_notes' => $order->special_notes,
            'reward_amount' => $order->reward_amount,
            'accepted_counter_offer_amount' => $order->accepted_counter_offer_amount,
            'status' => $order->status,
            'items_count' => $order->items->count(),
            'items_cost' => $itemsCost,
            'chat_room_id' => $chatRoom?->id,
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'weight' => $item->weight,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'special_notes' => $item->special_notes,
                'store_link' => $item->store_link,
                'product_images' => $item->product_images,
            ])->toArray(),
            'orderer' => $order->orderer ? [
                'id' => $order->orderer->id,
                'full_name' => $order->orderer->full_name,
                'avatar_url' => $order->orderer->avatar_url,
            ] : null,
            'picker' => $order->picker ? [
                'id' => $order->picker->id,
                'full_name' => $order->picker->full_name,
                'avatar_url' => $order->picker->avatar_url,
                'rating' => $this->getUserRating($order->picker->id),
            ] : null,
            'offers' => $order->offers->map(fn($offer) => [
                'id' => $offer->id,
                'offer_type' => $offer->offer_type,
                'offer_amount' => $offer->offer_amount,
                'status' => $offer->status,
                'created_at' => $offer->created_at,
            ])->toArray(),
            'created_at' => $order->created_at,
        ];
    }

    public function cancelOrder(string $orderId): Order
    {
        $order = Order::findOrFail($orderId);
        
        $order->update([
            'status' => 'CANCELLED',
        ]);

        return $order;
    }

    public function acceptOrder(string $orderId, string $pickerId): Order
    {
        $order = Order::findOrFail($orderId);
        
        $order->update([
            'assigned_picker_id' => $pickerId,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        // Create notification for orderer
        $picker = $order->picker;
        $this->notificationService->create(
            $order->orderer_id,
            'ORDER_ACCEPTED',
            'Order Accepted',
            "{$picker->full_name} has accepted your order",
            $order->id,
            ['order_id' => $order->id]
        );

        return $order;
    }

    public function getPickerOrders(string $pickerId, ?string $status = null, int $page = 1, int $limit = 20): array
    {
        $query = Order::where(function ($q) use ($pickerId) {
            // Include orders assigned to this picker OR PENDING orders (available to all pickers)
            // Exclude DRAFT orders (they're not visible to pickers yet)
            $q->where('assigned_picker_id', $pickerId)
              ->orWhere(function ($q2) {
                  $q2->where('status', 'PENDING');
              });
        })
        ->where('status', '!=', 'DRAFT')
        ->with(['items', 'orderer', 'offers']);

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $orders = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            $itemsCost = $order->items->sum(fn($item) => $item->price * $item->quantity);
            
            return [
                'id' => $order->id,
                'orderer_id' => $order->orderer_id,
                'orderer' => $order->orderer ? [
                    'id' => $order->orderer->id,
                    'full_name' => $order->orderer->full_name,
                    'avatar_url' => $order->orderer->avatar_url,
                    'rating' => $this->getUserRating($order->orderer->id),
                ] : null,
                'origin_city' => $order->origin_city,
                'destination_city' => $order->destination_city,
                'status' => $order->status,
                'items_count' => $order->items->count(),
                'items_cost' => $itemsCost,
                'reward_amount' => $order->reward_amount,
                'items' => $order->items->map(fn($item) => [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'product_images' => $item->product_images,
                ])->toArray(),
                'created_at' => $order->created_at,
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
