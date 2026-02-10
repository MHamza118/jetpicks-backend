<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $delivery)
    {
    }

    public function markDelivered(Request $request, Order $order): JsonResponse
    {
        try {
            $proofFile = $request->file('proof_of_delivery');
            $updated = $this->delivery->markDelivered($order, auth()->id(), $proofFile);

            return response()->json([
                'data' => [
                    'id' => $updated->id,
                    'status' => $updated->status,
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
                    'id' => $updated->id,
                    'status' => $updated->status,
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
                    'id' => $updated->id,
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

        $status = $this->delivery->getStatus($order);

        return response()->json(['data' => $status]);
    }
}
