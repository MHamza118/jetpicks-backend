<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TravelJourney;
use App\Models\User;

class OrderNotificationService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // Notify all pickers with matching travel journeys about a new order
    public function notifyPickersForNewOrder(Order $order): void
    {
        // Find all pickers with active travel journeys matching this order's route
        $matchingJourneys = TravelJourney::where('is_active', true)
            ->where('departure_city', $order->origin_city)
            ->where('departure_country', $order->origin_country)
            ->where('arrival_city', $order->destination_city)
            ->where('arrival_country', $order->destination_country)
            ->with('user')
            ->get();

        // Create notification for each matching picker
        foreach ($matchingJourneys as $journey) {
            $this->notificationService->create(
                $journey->user_id,
                'NEW_ORDER_AVAILABLE',
                'New Order Available',
                "A new order from {$order->origin_city} to {$order->destination_city} is available",
                $order->id,
                [
                    'order_id' => $order->id,
                    'orderer_name' => $order->orderer->full_name ?? 'Unknown',
                    'origin_city' => $order->origin_city,
                    'destination_city' => $order->destination_city,
                    'reward_amount' => $order->reward_amount,
                ]
            );
        }
    }

    /**
     * Notify orderer when their order is accepted by a picker
     */
    public function notifyOrdererOrderAccepted(Order $order): void
    {
        $picker = $order->picker;
        
        if (!$picker) {
            return;
        }

        $this->notificationService->create(
            $order->orderer_id,
            'ORDER_ACCEPTED',
            'Order Accepted',
            "{$picker->full_name} has accepted your order",
            $order->id,
            [
                'order_id' => $order->id,
                'picker_id' => $picker->id,
                'picker_name' => $picker->full_name,
            ]
        );
    }

    /**
     * Notify picker when order is cancelled
     */
    public function notifyPickerOrderCancelled(Order $order): void
    {
        if (!$order->assigned_picker_id) {
            return;
        }

        $orderer = $order->orderer;

        $this->notificationService->create(
            $order->assigned_picker_id,
            'ORDER_CANCELLED',
            'Order Cancelled',
            "Order from {$orderer->full_name} has been cancelled",
            $order->id,
            [
                'order_id' => $order->id,
                'orderer_id' => $orderer->id,
                'orderer_name' => $orderer->full_name,
            ]
        );
    }

    /**
     * Notify orderer when order is delivered
     */
    public function notifyOrdererOrderDelivered(Order $order): void
    {
        $picker = $order->picker;

        if (!$picker) {
            return;
        }

        $this->notificationService->create(
            $order->orderer_id,
            'ORDER_DELIVERED',
            'Order Delivered',
            "{$picker->full_name} has delivered your order",
            $order->id,
            [
                'order_id' => $order->id,
                'picker_id' => $picker->id,
                'picker_name' => $picker->full_name,
            ]
        );
    }

    /**
     * Notify picker when payment is confirmed
     */
    public function notifyPickerPaymentConfirmed(Order $order): void
    {
        if (!$order->assigned_picker_id) {
            return;
        }

        $this->notificationService->create(
            $order->assigned_picker_id,
            'PAYMENT_CONFIRMED',
            'Payment Confirmed',
            "Payment confirmed for order from {$order->origin_city} to {$order->destination_city}",
            $order->id,
            [
                'order_id' => $order->id,
                'reward_amount' => $order->reward_amount,
            ]
        );
    }

    /**
     * Notify orderer when counter offer is received
     */
    public function notifyOrdererCounterOfferReceived(string $orderId, string $offerId, float $offerAmount, string $pickerName): void
    {
        $order = Order::findOrFail($orderId);

        $this->notificationService->create(
            $order->orderer_id,
            'COUNTER_OFFER_RECEIVED',
            'Counter Offer Received',
            "{$pickerName} has submitted a counter offer of \${$offerAmount}",
            $offerId,
            [
                'order_id' => $orderId,
                'offer_id' => $offerId,
                'offer_amount' => $offerAmount,
                'picker_name' => $pickerName,
            ]
        );
    }

    /**
     * Notify picker when counter offer is accepted
     */
    public function notifyPickerCounterOfferAccepted(string $orderId, string $offerId, float $acceptedAmount, string $ordererName): void
    {
        $order = Order::findOrFail($orderId);

        if (!$order->assigned_picker_id) {
            return;
        }

        $this->notificationService->create(
            $order->assigned_picker_id,
            'COUNTER_OFFER_ACCEPTED',
            'Counter Offer Accepted',
            "{$ordererName} has accepted your counter offer of ${$acceptedAmount}",
            $offerId,
            [
                'order_id' => $orderId,
                'offer_id' => $offerId,
                'accepted_amount' => $acceptedAmount,
                'orderer_name' => $ordererName,
            ]
        );
    }
}
