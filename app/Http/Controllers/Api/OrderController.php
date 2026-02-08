<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\AddOrderItemRequest;
use App\Http\Requests\SetOrderRewardRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOrder(
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Order created successfully',
            'data' => [
                'id' => $order->id,
                'orderer_id' => $order->orderer_id,
                'origin_country' => $order->origin_country,
                'origin_city' => $order->origin_city,
                'destination_country' => $order->destination_country,
                'destination_city' => $order->destination_city,
                'special_notes' => $order->special_notes,
                'status' => $order->status,
                'created_at' => $order->created_at,
            ],
        ], 201);
    }

    public function storeItems(Order $order, AddOrderItemRequest $request): JsonResponse
    {
        $item = $this->orderService->addOrderItem($order->id, $request->validated());

        return response()->json([
            'message' => 'Item added successfully',
            'data' => [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'item_name' => $item->item_name,
                'weight' => $item->weight,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'special_notes' => $item->special_notes,
                'store_link' => $item->store_link,
                'product_images' => $item->product_images,
            ],
        ], 201);
    }

    public function setReward(Order $order, SetOrderRewardRequest $request): JsonResponse
    {
        $updated = $this->orderService->setReward($order->id, $request->validated()['reward_amount']);

        return response()->json([
            'message' => 'Reward set successfully',
            'data' => [
                'id' => $updated->id,
                'reward_amount' => $updated->reward_amount,
                'status' => $updated->status,
            ],
        ]);
    }

    public function finalize(Order $order): JsonResponse
    {
        $finalized = $this->orderService->finalizeOrder($order->id);

        return response()->json([
            'message' => 'Order finalized successfully',
            'data' => [
                'id' => $finalized->id,
                'status' => $finalized->status,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $page = (int) $request->query('page', 1);
        $limit = min((int) $request->query('limit', 20), 100);

        $orders = $this->orderService->getOrdererOrders(
            $request->user()->id,
            $status,
            $page,
            $limit
        );

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        $orderData = $this->orderService->getOrderDetails($order->id);

        return response()->json([
            'data' => $orderData,
        ]);
    }

    public function update(Order $order, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'waiting_days' => 'nullable|integer|min:1',
        ]);

        $order->update($validated);

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => [
                'id' => $order->id,
                'waiting_days' => $order->waiting_days,
            ],
        ]);
    }

    public function destroy(Order $order, Request $request): JsonResponse
    {
        $cancelled = $this->orderService->cancelOrder($order->id, $request->user()->id);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'data' => [
                'id' => $cancelled->id,
                'status' => $cancelled->status,
            ],
        ]);
    }

    public function acceptDelivery(Order $order, Request $request): JsonResponse
    {
        $accepted = $this->orderService->acceptOrder($order->id, $request->user()->id);

        return response()->json([
            'message' => 'Order accepted successfully',
            'data' => [
                'id' => $accepted->id,
                'status' => $accepted->status,
                'assigned_picker_id' => $accepted->assigned_picker_id,
                'accepted_at' => $accepted->accepted_at,
            ],
        ]);
    }

    public function getPickerOrders(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $page = (int) $request->query('page', 1);
        $limit = min((int) $request->query('limit', 20), 100);

        $orders = $this->orderService->getPickerOrders(
            $request->user()->id,
            $status,
            $page,
            $limit
        );

        return response()->json($orders);
    }
}
