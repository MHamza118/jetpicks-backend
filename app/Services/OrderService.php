<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Offer;
use Illuminate\Pagination\Paginator;

class OrderService
{
    protected NotificationService $notificationService;
    protected OrderNotificationService $orderNotificationService;

    public function __construct(
        NotificationService $notificationService,
        OrderNotificationService $orderNotificationService
    ) {
        $this->notificationService = $notificationService;
        $this->orderNotificationService = $orderNotificationService;
    }
    public function createOrder(string $ordererId, array $data): Order
    {
        // If picker_id is provided, order should be ACCEPTED, otherwise DRAFT
        $status = isset($data['picker_id']) && $data['picker_id'] ? 'ACCEPTED' : ($data['status'] ?? 'DRAFT');
        
        $order = Order::create([
            'orderer_id' => $ordererId,
            'assigned_picker_id' => $data['picker_id'] ?? null,
            'origin_country' => $data['origin_country'],
            'origin_city' => $data['origin_city'],
            'destination_country' => $data['destination_country'],
            'destination_city' => $data['destination_city'],
            'special_notes' => $data['special_notes'] ?? null,
            'reward_amount' => 0,
            'status' => $status,
            'waiting_days' => $data['waiting_days'] ?? null,
            'accepted_at' => isset($data['picker_id']) && $data['picker_id'] ? now() : null,
        ]);

        return $order;
    }

    public function deleteOrderItems(string $orderId): void
    {
        OrderItem::where('order_id', $orderId)->delete();
    }

    public function addOrderItem(string $orderId, array $data): OrderItem
    {
        \Log::info('addOrderItem called with data:', $data);
        
        $imageUrls = [];
        if (isset($data['product_images']) && is_array($data['product_images'])) {
            foreach ($data['product_images'] as $image) {
                if ($image instanceof \Illuminate\Http\UploadedFile) {
                    $path = $image->store('order-items', 'public');
                    $imageUrls[] = '/storage/' . $path;
                }
            }
        }

        $itemData = [
            'order_id' => $orderId,
            'item_name' => $data['item_name'],
            'weight' => $data['weight'] ?? null,
            'price' => $data['price'],
            'quantity' => $data['quantity'] ?? 1,
            'currency' => $data['currency'] ?? null,
            'special_notes' => $data['special_notes'] ?? null,
            'store_link' => $data['store_link'] ?? null,
            'product_images' => !empty($imageUrls) ? $imageUrls : null,
        ];
        
        \Log::info('Creating OrderItem with data:', $itemData);
        
        $item = OrderItem::create($itemData);
        
        // Always update the order's currency to match the item's currency
        // This ensures both orders.currency and order_items.currency are always in sync
        if ($data['currency'] ?? null) {
            $order = Order::findOrFail($orderId);
            $order->update(['currency' => $data['currency']]);
            \Log::info('Updated order currency to:', ['currency' => $data['currency'], 'order_id' => $orderId]);
        }
        
        return $item;
    }

    public function updateOrderItem(string $itemId, array $data): OrderItem
    {
        $item = OrderItem::findOrFail($itemId);
        
        $updateData = [];
        if (isset($data['item_name'])) {
            $updateData['item_name'] = $data['item_name'];
        }
        if (isset($data['quantity'])) {
            $updateData['quantity'] = $data['quantity'];
        }
        if (isset($data['weight'])) {
            $updateData['weight'] = $data['weight'];
        }
        if (isset($data['price'])) {
            $updateData['price'] = $data['price'];
        }
        if (isset($data['store_link'])) {
            $updateData['store_link'] = $data['store_link'];
        }
        
        $item->update($updateData);
        
        return $item;
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
        $order = Order::with('orderer')->findOrFail($orderId);
        
        // Only change status to PENDING if it's currently DRAFT
        // If it's already ACCEPTED (from select jetpicker flow), keep it as ACCEPTED
        if ($order->status === 'DRAFT') {
            $order->update([
                'status' => 'PENDING',
            ]);

            // Notify all matching pickers about the new order
            $this->orderNotificationService->notifyPickersForNewOrder($order);
        }

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

        $orders = $query->orderBy('created_at', 'desc')
            ->offset($offset)
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
                'items_cost' => $itemsCost,
                'reward_amount' => $order->reward_amount,
                'accepted_counter_offer_amount' => $order->accepted_counter_offer_amount,
                'currency' => $order->currency,
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
            'waiting_days' => $order->waiting_days,
            'status' => $order->status,
            'items_count' => $order->items->count(),
            'items_cost' => $itemsCost,
            'currency' => $order->currency,
            'chat_room_id' => $chatRoom?->id,
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'weight' => $item->weight,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'currency' => $item->currency,
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

    public function cancelOrder(string $orderId, string $pickerId): Order
    {
        $order = Order::findOrFail($orderId);
        
        $order->update([
            'assigned_picker_id' => $order->assigned_picker_id ?? $pickerId,
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

        // Notify orderer that their order has been accepted
        $this->orderNotificationService->notifyOrdererOrderAccepted($order);

        return $order;
    }

    public function getPickerOrders(string $pickerId, ?string $status = null, int $page = 1, int $limit = 20): array
    {
        $query = Order::where('status', '!=', 'DRAFT')
            ->with(['items', 'orderer', 'offers']);

        // Build the picker assignment condition
        // Include: orders assigned to this picker, PENDING orders, or CANCELLED orders assigned to this picker
        $query->where(function ($q) use ($pickerId) {
            $q->where('assigned_picker_id', $pickerId)  // Orders assigned to this picker (any status)
              ->orWhere('status', 'PENDING');            // PENDING orders (available for all pickers)
        });

        // If a specific status is requested, apply additional filter
        if ($status) {
            $query->where('status', $status);
        }

        $offset = ($page - 1) * $limit;

        $orders = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $total = $query->count();

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
                'currency' => $order->currency,
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
