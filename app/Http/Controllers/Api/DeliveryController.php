<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\OrderNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Account;
use Stripe\Stripe;
use Stripe\Transfer;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $delivery,
        private OrderNotificationService $notifications
    ) {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY', env('STRIPE_SECRET')));
    }

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
            $payout = [];

            try {
                $payout = $this->createPickerTransferIfNeeded($updated);
            } catch (\Throwable $payoutError) {
                \Log::error('Payout transfer failed after delivery confirmation', [
                    'order_id' => $updated->id,
                    'error' => $payoutError->getMessage(),
                ]);

                $payout = [
                    'status' => 'failed',
                    'message' => 'Order completed, but payout transfer failed due Stripe platform transfer permissions.',
                    'reason' => $payoutError->getMessage(),
                ];
            }

            return response()->json([
                'data' => [
                    'id'                    => $updated->id,
                    'status'                => $updated->status,
                    'delivery_confirmed_at' => $updated->delivery_confirmed_at,
                    'payout'                => $payout,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    private function createPickerTransferIfNeeded(Order $order): array
    {
        if ($order->status !== 'COMPLETED') {
            return ['status' => 'skipped', 'reason' => 'Order is not completed'];
        }

        if (empty($order->delivered_at)) {
            return ['status' => 'skipped', 'reason' => 'Picker delivery has not been marked as completed'];
        }

        if (empty($order->delivery_confirmed_at)) {
            return ['status' => 'skipped', 'reason' => 'Buyer delivery confirmation is missing'];
        }

        if ($order->payment_status !== 'PAID') {
            return ['status' => 'skipped', 'reason' => 'Order is not paid'];
        }

        if (!$order->assigned_picker_id) {
            return ['status' => 'skipped', 'reason' => 'No picker assigned'];
        }

        $picker = User::find($order->assigned_picker_id);
        if (!$picker) {
            throw new \Exception('Assigned picker account was not found.');
        }

        if (!$picker->stripe_connect_account_id) {
            throw new \Exception('Picker Stripe account is not connected yet.');
        }

        $connectedAccount = Account::retrieve($picker->stripe_connect_account_id);
        $chargesEnabled = !empty($connectedAccount->charges_enabled);
        $payoutsEnabled = !empty($connectedAccount->payouts_enabled);

        // Sync local status with Stripe as source of truth to avoid stale DB values blocking payouts.
        $resolvedStatus = ($chargesEnabled && $payoutsEnabled) ? 'verified' : 'pending';
        if (($picker->stripe_connect_status ?? null) !== $resolvedStatus) {
            $picker->stripe_connect_status = $resolvedStatus;
            $picker->save();
        }

        if (!$chargesEnabled || !$payoutsEnabled) {
            throw new \Exception('Picker Stripe account is not fully active yet (charges and payouts must both be enabled).');
        }

        $payment = Payment::where('order_id', $order->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$payment) {
            throw new \Exception('Payment record not found for this order.');
        }

        $metadata = [];
        if (is_array($payment->metadata)) {
            $metadata = $payment->metadata;
        } elseif (is_string($payment->metadata) && $payment->metadata !== '') {
            $decoded = json_decode($payment->metadata, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        if (!empty($metadata['stripe_transfer_id'])) {
            return [
                'status' => 'already_transferred',
                'stripe_transfer_id' => $metadata['stripe_transfer_id'],
            ];
        }

        $grossAmount = (float) ($order->accepted_counter_offer_amount ?? 0);
        if ($grossAmount <= 0) {
            $grossAmount = (float) ($order->reward_amount ?? 0);
        }

        $grossAmountPence = (int) round($grossAmount * 100);
        if ($grossAmountPence <= 0) {
            throw new \Exception('Order amount is invalid for transfer.');
        }

        $platformFeePercent = (float) env('PLATFORM_FEE_PERCENT', 10);
        $platformFeeFixedPence = (int) env('PLATFORM_FEE_FIXED_PENCE', 0);

        $platformFeePence = (int) round(($grossAmountPence * $platformFeePercent / 100) + $platformFeeFixedPence);
        $pickerAmountPence = max($grossAmountPence - $platformFeePence, 0);

        if ($pickerAmountPence <= 0) {
            throw new \Exception('Calculated payout amount is zero. Check fee configuration.');
        }

        $transfer = Transfer::create([
            'amount' => $pickerAmountPence,
            'currency' => strtolower((string) ($order->currency ?: 'gbp')),
            'destination' => $picker->stripe_connect_account_id,
            'metadata' => [
                'order_id' => $order->id,
                'picker_id' => $picker->id,
                'gross_amount_pence' => (string) $grossAmountPence,
                'platform_fee_pence' => (string) $platformFeePence,
            ],
        ], [
            'idempotency_key' => 'order-payout-' . $order->id,
        ]);

        $metadata['stripe_transfer_id'] = $transfer->id;
        $metadata['payout_status'] = 'TRANSFERRED';
        $metadata['gross_amount_pence'] = $grossAmountPence;
        $metadata['platform_fee_pence'] = $platformFeePence;
        $metadata['picker_payout_amount_pence'] = $pickerAmountPence;

        $payment->metadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payment->save();

        return [
            'status' => 'transferred',
            'stripe_transfer_id' => $transfer->id,
            'destination_account' => $picker->stripe_connect_account_id,
            'gross_amount_pence' => $grossAmountPence,
            'platform_fee_pence' => $platformFeePence,
            'picker_amount_pence' => $pickerAmountPence,
            'currency' => $transfer->currency,
        ];
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
