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
        // Find all pickers with active travel journeys matching this order's route (by country only)
        // SECURITY: Exclude the orderer from receiving notifications about their own order
        $matchingJourneys = TravelJourney::where('is_active', true)
            ->where('departure_country', $order->origin_country)
            ->where('arrival_country', $order->destination_country)
            ->where('user_id', '!=', $order->orderer_id) // Exclude orderer's own picker profile
            ->with('user')
            ->get();

        // Create notification for each matching picker
        foreach ($matchingJourneys as $journey) {
            $this->notificationService->create(
                $journey->user_id,
                'NEW_ORDER_AVAILABLE',
                'New Order Available',
                "A new order from {$order->origin_country} to {$order->destination_country} is available",
                $order->id,
                [
                    'order_id' => $order->id,
                    'orderer_name' => $order->orderer->full_name ?? 'Unknown',
                    'origin_city' => $order->origin_city,
                    'origin_country' => $order->origin_country,
                    'destination_city' => $order->destination_city,
                    'destination_country' => $order->destination_country,
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
    public function notifyOrdererCounterOfferReceived(string $orderId, string $offerId, float $offerAmount, string $pickerName, ?string $note = null): void
    {
        $order = Order::findOrFail($orderId);

        // Include a note preview in the notification body if provided
        $body = "{$pickerName} sent a counter offer of \${$offerAmount}";
        if ($note) {
            $preview = strlen($note) > 60 ? substr($note, 0, 60) . '...' : $note;
            $body .= " — \"{$preview}\"";
        }

        $this->notificationService->create(
            $order->orderer_id,
            'COUNTER_OFFER_RECEIVED',
            'Counter Offer Received',
            $body,
            $offerId,
            [
                'order_id'    => $orderId,
                'offer_id'    => $offerId,
                'offer_amount' => $offerAmount,
                'picker_name' => $pickerName,
                'note'        => $note,
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

    /**
     * Notify Jetpicker when Jetbuyer updates a delivery milestone
     */
    public function notifyMilestoneUpdate(Order $order, string $milestone): void
    {
        $milestoneMessages = [
            'items_purchased'   => 'Your items have been purchased and are ready to travel!',
            'departed'          => 'Your Jetbuyer has departed and is on their way.',
            'dropped_at_locker' => 'Your items have been dropped at the InPost locker. Check for your collection code.',
            'ready_to_meet'     => 'Your Jetbuyer has arrived and is ready to meet you.',
        ];

        $milestoneTypes = [
            'items_purchased'   => 'ITEMS_PURCHASED',
            'departed'          => 'JETBUYER_DEPARTED',
            'dropped_at_locker' => 'DROPPED_AT_LOCKER',
            'ready_to_meet'     => 'READY_TO_MEET',
        ];

        $message = $milestoneMessages[$milestone] ?? "Delivery status updated: {$milestone}";
        $type    = $milestoneTypes[$milestone] ?? 'MILESTONE_UPDATE';

        $this->notificationService->create(
            $order->orderer_id,
            $type,
            'Order Update',
            $message,
            $order->id,
            ['order_id' => $order->id, 'milestone' => $milestone]
        );
    }

    /**
     * Notify Jetpicker when Jetbuyer submits delivery outcome
     */
    public function notifyDeliverySubmitted(Order $order): void
    {
        $outcomeMessages = [
            'all_delivered'     => 'Your Jetbuyer has marked all items as delivered. Please confirm receipt.',
            'partial_delivery'  => 'Your Jetbuyer has reported a partial delivery. Please review and confirm.',
            'unable_to_deliver' => 'Your Jetbuyer was unable to complete the delivery. Please review the notes.',
        ];

        $message = $outcomeMessages[$order->delivery_outcome ?? 'all_delivered']
            ?? 'Your Jetbuyer has submitted delivery. Please confirm.';

        $this->notificationService->create(
            $order->orderer_id,
            'DELIVERY_SUBMITTED',
            'Delivery Update',
            $message,
            $order->id,
            [
                'order_id'        => $order->id,
                'delivery_outcome' => $order->delivery_outcome,
                'delivery_notes'  => $order->delivery_notes,
            ]
        );
    }

    /**
     * Notify Jetbuyer when Jetpicker chooses a delivery method
     */
    public function notifyDeliveryMethodChosen(Order $order): void
    {
        if (!$order->assigned_picker_id) return;

        $methodMessages = [
            'meet_in_person' => 'Your Jetpicker would like to meet in person to hand over the items.',
            'inpost_locker'  => 'Your Jetpicker has chosen InPost locker collection. Please select a convenient locker.',
            'inpost_home'    => 'Your Jetpicker has chosen InPost home delivery. Drop off at a locker on arrival.',
        ];

        $message = $methodMessages[$order->delivery_method ?? 'meet_in_person']
            ?? 'Your Jetpicker has chosen a delivery method.';

        $this->notificationService->create(
            $order->assigned_picker_id,
            'DELIVERY_METHOD_CHOSEN',
            'Delivery Method Set',
            $message,
            $order->id,
            ['order_id' => $order->id, 'delivery_method' => $order->delivery_method]
        );
    }

    /**
     * Notify Jetpicker when Jetbuyer selects a specific InPost locker
     */
    public function notifyLockerSelected(Order $order): void
    {
        $this->notificationService->create(
            $order->orderer_id,
            'LOCKER_SELECTED',
            'Locker Selected',
            "Your Jetbuyer has selected an InPost locker for drop-off. You'll receive a collection code when items arrive.",
            $order->id,
            ['order_id' => $order->id, 'inpost_locker_id' => $order->inpost_locker_id]
        );
    }
}
