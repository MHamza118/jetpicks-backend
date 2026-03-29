<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryService;
use App\Services\OrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $delivery,
        private OrderNotificationService $notifications
    ) {}

    // ── Milestone updates (Jetbuyer taps in app) ───────────────────────────

    /**
     * POST /orders/{order}/milestone
     * Body: { "milestone": "items_purchased" | "departed" | "dropped_at_locker" | "ready_to_meet" }
     */
    public function updateMilestone(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'milestone' => 'required|string|in:items_purchased,departed,dropped_at_locker,ready_to_meet',
        ]);

        try {
            $userId = auth()->id();
            $milestone = $request->input('milestone');

            $updated = match ($milestone) {
                'items_purchased'    => $this->delivery->markItemsPurchased($order, $userId),
                'departed'           => $this->delivery->markDeparted($order, $userId),
                'dropped_at_locker'  => $this->delivery->markDroppedAtLocker($order, $userId),
                'ready_to_meet'      => $this->delivery->markReadyToMeet($order, $userId),
            };

            // Notify Jetpicker of milestone update
            $this->notifications->notifyMilestoneUpdate($updated, $milestone);

            return response()->json([
                'data' => $this->delivery->getStatus($updated),
                'message' => 'Milestone updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /orders/{order}/deliver
     * Body: {
     *   "outcome": "all_delivered" | "partial_delivery" | "unable_to_deliver",
     *   "notes": "string (required for partial/unable)",
     *   "item_statuses": [{"item_id": "uuid", "delivered": true}],  // for partial
     *   "proof_of_delivery": file (multipart)
     * }
     */
    public function submitDelivery(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'outcome'            => 'required|string|in:all_delivered,partial_delivery,unable_to_deliver',
            'notes'              => 'nullable|string|max:2000',
            'item_statuses'      => 'nullable|array',
            'item_statuses.*.item_id'   => 'required_with:item_statuses|string',
            'item_statuses.*.delivered' => 'required_with:item_statuses|boolean',
            'proof_of_delivery'  => 'nullable|file|max:102400',
        ]);

        try {
            $updated = $this->delivery->submitDeliveryOutcome(
                $order,
                auth()->id(),
                $request->input('outcome'),
                $request->input('notes'),
                $request->input('item_statuses'),
                $request->file('proof_of_delivery')
            );

            // Notify Jetpicker of delivery submission
            $this->notifications->notifyDeliverySubmitted($updated);

            return response()->json([
                'data'    => $this->delivery->getStatus($updated),
                'message' => 'Delivery submitted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // ── Delivery method (Jetpicker sets on Order Accepted screen) ──────────

    /**
     * PUT /orders/{order}/delivery-method
     * Body: {
     *   "method": "meet_in_person" | "inpost_locker" | "inpost_home",
     *   "delivery_address": "string (for inpost_home)"
     * }
     */
    public function setDeliveryMethod(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'method'           => 'required|string|in:meet_in_person,inpost_locker,inpost_home',
            'delivery_address' => 'nullable|string|max:500',
        ]);

        try {
            $updated = $this->delivery->setDeliveryMethod(
                $order,
                auth()->id(),
                $request->input('method'),
                null,
                $request->input('delivery_address')
            );

            // Notify Jetbuyer of chosen delivery method
            $this->notifications->notifyDeliveryMethodChosen($updated);

            return response()->json([
                'data'    => $this->delivery->getStatus($updated),
                'message' => 'Delivery method set',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /orders/{order}/inpost-locker
     * Body: { "locker_id": "string" }
     * Called by Jetbuyer after agreeing on locker with Jetpicker.
     */
    public function setInpostLocker(Request $request, Order $order): JsonResponse
    {
        $request->validate(['locker_id' => 'required|string']);

        try {
            $updated = $this->delivery->setInpostLocker(
                $order,
                auth()->id(),
                $request->input('locker_id')
            );

            // Notify Jetpicker of selected locker
            $this->notifications->notifyLockerSelected($updated);

            return response()->json([
                'data'    => $this->delivery->getStatus($updated),
                'message' => 'Locker selected',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // ── Legacy endpoints (kept for backward compat with existing app) ──────

    public function markDelivered(Request $request, Order $order): JsonResponse
    {
        try {
            $updated = $this->delivery->markDelivered($order, auth()->id(), $request->file('proof_of_delivery'));
            $this->notifications->notifyDeliverySubmitted($updated);

            return response()->json([
                'data' => [
                    'id'           => $updated->id,
                    'status'       => $updated->status,
                    'delivered_at' => $updated->delivered_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function confirmDelivery(Order $order): JsonResponse
    {
        try {
            $updated = $this->delivery->confirmDelivery($order, auth()->id());

            return response()->json([
                'data' => [
                    'id'                    => $updated->id,
                    'status'                => $updated->status,
                    'delivery_confirmed_at' => $updated->delivery_confirmed_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function reportIssue(Order $order): JsonResponse
    {
        try {
            $updated = $this->delivery->reportIssue($order, auth()->id());

            return response()->json([
                'data' => [
                    'id'                      => $updated->id,
                    'delivery_issue_reported' => $updated->delivery_issue_reported,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getStatus(Order $order): JsonResponse
    {
        $userId = auth()->id();
        if ($order->orderer_id !== $userId && $order->assigned_picker_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $this->delivery->getStatus($order)]);
    }
}
