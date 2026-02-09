<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OfferService
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

        // Check if picker already sent a counter offer for this order
        $existingCounterOffer = Offer::where('order_id', $orderId)
            ->where('offered_by_user_id', $pickerId)
            ->where('offer_type', 'COUNTER')
            ->whereIn('status', ['PENDING', 'ACCEPTED'])
            ->first();

        if ($existingCounterOffer) {
            throw new \Exception('You have already sent a counter offer for this order. Wait for the orderer to respond.');
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

        // Notify orderer about counter offer
        $picker = User::find($pickerId);
        if ($picker) {
            $this->orderNotificationService->notifyOrdererCounterOfferReceived(
                $orderId,
                $counterOffer->id,
                $offerAmount,
                $picker->full_name
            );
        }

        return $counterOffer;
    }

    public function acceptOffer(string $offerId, string $userId): array
    {
        \Log::info('OfferService::acceptOffer start', ['offerId' => $offerId, 'userId' => $userId]);
        
        $offer = Offer::with(['order', 'order.orderer'])->find($offerId);
        \Log::info('Offer found', ['offer' => $offer ? 'yes' : 'no']);
        
        if (!$offer) {
            throw new \Exception('Offer not found');
        }

        $order = $offer->order;
        \Log::info('Order found', ['order' => $order ? 'yes' : 'no', 'order_id' => $order?->id]);
        
        if (!$order) {
            throw new \Exception('Order not found');
        }

        \Log::info('Checking permissions', [
            'offer_type' => $offer->offer_type,
            'order_orderer_id' => $order->orderer_id,
            'user_id' => $userId,
            'match' => $order->orderer_id === $userId
        ]);

        if ($offer->offer_type === 'INITIAL' && $order->orderer_id !== $userId) {
            throw new \Exception('Only orderer can accept initial offer');
        }
        if ($offer->offer_type === 'COUNTER' && $order->orderer_id !== $userId) {
            throw new \Exception('Only orderer can accept counter-offer');
        }

        $offer->update(['status' => 'ACCEPTED']);
        \Log::info('Offer status updated to ACCEPTED');

        if ($offer->parent_offer_id) {
            Offer::where('id', $offer->parent_offer_id)
                ->orWhere('parent_offer_id', $offer->parent_offer_id)
                ->where('id', '!=', $offerId)
                ->update(['status' => 'SUPERSEDED']);
            \Log::info('Parent offers marked as SUPERSEDED');
        }

        Offer::where('order_id', $order->id)
            ->where('id', '!=', $offerId)
            ->where('status', 'PENDING')
            ->update(['status' => 'SUPERSEDED']);
        \Log::info('Other pending offers marked as SUPERSEDED');

        // For COUNTER offers, store the accepted amount separately
        // Do NOT change order status or assign picker
        if ($offer->offer_type === 'COUNTER') {
            \Log::info('Processing COUNTER offer', ['amount' => $offer->offer_amount]);
            
            $order->accepted_counter_offer_amount = $offer->offer_amount;
            $order->save();
            \Log::info('Order saved with accepted_counter_offer_amount', ['value' => $order->accepted_counter_offer_amount]);

            // Notify picker about counter offer acceptance
            $orderer = $order->orderer;
            \Log::info('Orderer loaded', ['orderer' => $orderer ? 'yes' : 'no']);
            
            if ($orderer) {
                \Log::info('Creating notification for picker', ['picker_id' => $offer->offered_by_user_id]);
                $this->orderNotificationService->notifyPickerCounterOfferAccepted(
                    $order->id,
                    $offerId,
                    $offer->offer_amount,
                    $orderer->full_name
                );
            }
        }

        \Log::info('OfferService::acceptOffer complete');
        
        // Refresh order to get latest data from database
        $order = $order->fresh();
        \Log::info('Order refreshed', ['accepted_counter_offer_amount' => $order->accepted_counter_offer_amount]);
        
        return [
            'offer' => $offer,
            'order' => $order,
            'chat_room' => null,
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
