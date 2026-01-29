<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class OfferService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function createInitialOffer(Order $order, string $ordererId, float $rewardAmount): Offer
    {
        return Offer::create([
            'order_id' => $order->id,
            'offered_by_user_id' => $ordererId,
            'offer_type' => 'INITIAL',
            'offer_amount' => $rewardAmount,
            'status' => 'PENDING',
            'parent_offer_id' => null,
        ]);
    }

    public function createCounterOffer(string $orderId, string $pickerId, float $offerAmount, ?string $parentOfferId = null): Offer
    {
        $order = Order::find($orderId);
        if (!$order) {
            throw new \Exception('Order not found');
        }

        if (!$parentOfferId) {
            $latestOffer = Offer::where('order_id', $orderId)
                ->orderBy('created_at', 'desc')
                ->first();
            $parentOfferId = $latestOffer?->id;
        }

        $counterOffer = Offer::create([
            'order_id' => $orderId,
            'offered_by_user_id' => $pickerId,
            'offer_type' => 'COUNTER',
            'offer_amount' => $offerAmount,
            'status' => 'PENDING',
            'parent_offer_id' => $parentOfferId,
        ]);

        // Create notification for orderer
        $picker = $counterOffer->offeredBy;
        $this->notificationService->create(
            $order->orderer_id,
            'COUNTER_OFFER_RECEIVED',
            'Counter Offer Received',
            "{$picker->full_name} sent you a counter offer for \${$offerAmount}",
            $counterOffer->id,
            ['order_id' => $orderId, 'offer_id' => $counterOffer->id, 'amount' => $offerAmount, 'picker_name' => $picker->full_name]
        );

        return $counterOffer;
    }

    public function acceptOffer(string $offerId, string $userId): array
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            throw new \Exception('Offer not found');
        }

        $order = $offer->order;
        if ($offer->offer_type === 'INITIAL' && $order->orderer_id !== $userId) {
            throw new \Exception('Only orderer can accept initial offer');
        }
        if ($offer->offer_type === 'COUNTER' && $order->orderer_id !== $userId) {
            throw new \Exception('Only orderer can accept counter-offer');
        }

        $offer->update(['status' => 'ACCEPTED']);

        if ($offer->parent_offer_id) {
            Offer::where('id', $offer->parent_offer_id)
                ->orWhere('parent_offer_id', $offer->parent_offer_id)
                ->where('id', '!=', $offerId)
                ->update(['status' => 'SUPERSEDED']);
        }

        Offer::where('order_id', $order->id)
            ->where('id', '!=', $offerId)
            ->where('status', 'PENDING')
            ->update(['status' => 'SUPERSEDED']);

        $order->update([
            'assigned_picker_id' => $offer->offered_by_user_id,
            'status' => 'ACCEPTED',
            'reward_amount' => $offer->offer_amount,
        ]);

        $chatService = app(ChatService::class);
        $chatRoom = $chatService->createChatRoom(
            $order->id,
            $order->orderer_id,
            $offer->offered_by_user_id
        );

        // Create notification for picker
        $orderer = $order->orderer;
        $this->notificationService->create(
            $offer->offered_by_user_id,
            'ORDER_ACCEPTED',
            'Order Accepted',
            "{$orderer->full_name} has accepted your offer",
            $order->id,
            ['order_id' => $order->id, 'offer_id' => $offerId, 'picker_name' => $orderer->full_name]
        );

        return [
            'offer' => $offer,
            'order' => $order->fresh(),
            'chat_room' => $chatRoom,
        ];
    }

    public function rejectOffer(string $offerId, string $userId): Offer
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            throw new \Exception('Offer not found');
        }

        $order = $offer->order;
        if ($order->orderer_id !== $userId) {
            throw new \Exception('Only orderer can reject offers');
        }

        $offer->update(['status' => 'REJECTED']);

        return $offer;
    }

    public function getOfferHistory(string $orderId, int $page = 1, int $limit = 50): array
    {
        $query = Offer::where('order_id', $orderId)
            ->with(['offeredBy'])
            ->orderBy('created_at', 'asc');

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $offers = $query->offset($offset)
            ->limit($limit)
            ->get();

        $formattedOffers = $offers->map(function ($offer) {
            return [
                'id' => $offer->id,
                'offer_type' => $offer->offer_type,
                'offer_amount' => $offer->offer_amount,
                'offered_by' => [
                    'id' => $offer->offeredBy->id,
                    'full_name' => $offer->offeredBy->full_name,
                    'avatar_url' => $offer->offeredBy->avatar_url,
                ],
                'status' => $offer->status,
                'parent_offer_id' => $offer->parent_offer_id,
                'created_at' => $offer->created_at,
            ];
        });

        return [
            'data' => $formattedOffers->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    public function getCurrentOffer(string $orderId): ?Offer
    {
        return Offer::where('order_id', $orderId)
            ->whereIn('status', ['PENDING', 'ACCEPTED'])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getPendingOffers(string $orderId): Collection
    {
        return Offer::where('order_id', $orderId)
            ->where('status', 'PENDING')
            ->with(['offeredBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
