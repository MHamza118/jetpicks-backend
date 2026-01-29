<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTipRequest;
use App\Models\Order;
use App\Services\TipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipController extends Controller
{
    public function __construct(private TipService $tips)
    {
    }

    public function store(StoreTipRequest $request): JsonResponse
    {
        try {
            $order = Order::findOrFail($request->input('order_id'));
            $tip = $this->tips->create($order, auth()->id(), $request->input('amount'));

            return response()->json([
                'data' => [
                    'id' => $tip->id,
                    'amount' => $tip->amount,
                    'order_id' => $tip->order_id,
                    'created_at' => $tip->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getOrderTips(Order $order): JsonResponse
    {
        $userId = auth()->id();
        if ($order->orderer_id !== $userId && $order->assigned_picker_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tips = $this->tips->getOrderTips($order);

        return response()->json(['data' => $tips]);
    }

    public function getUserTipsReceived(int $userId, Request $request): JsonResponse
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 20);

        $result = $this->tips->getUserTipsReceived($userId, $page, $limit);

        return response()->json($result);
    }
}
