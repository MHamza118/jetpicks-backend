<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrdersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['orderer', 'picker', 'items']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('orderer', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($request->get('per_page', 15));

        $items = collect($orders->items())->map(function ($order) {
            return [
                'id' => $order->id,
                'orderer' => $order->orderer ? [
                    'id' => $order->orderer->id,
                    'full_name' => $order->orderer->full_name,
                    'email' => $order->orderer->email,
                ] : null,
                'picker' => $order->picker ? [
                    'id' => $order->picker->id,
                    'full_name' => $order->picker->full_name,
                    'email' => $order->picker->email,
                ] : null,
                'origin_country' => $order->origin_country,
                'origin_city' => $order->origin_city,
                'destination_country' => $order->destination_country,
                'destination_city' => $order->destination_city,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'reward_amount' => $order->reward_amount,
                'currency' => $order->currency,
                'total_items_amount' => $this->calculateTotal($order),
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at,
                'delivered_at' => $order->delivered_at,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with(['orderer', 'picker', 'items'])
                      ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'orderer' => $order->orderer ? [
                    'id' => $order->orderer->id,
                    'full_name' => $order->orderer->full_name,
                    'email' => $order->orderer->email,
                ] : null,
                'picker' => $order->picker ? [
                    'id' => $order->picker->id,
                    'full_name' => $order->picker->full_name,
                    'email' => $order->picker->email,
                ] : null,
                'origin_country' => $order->origin_country,
                'origin_city' => $order->origin_city,
                'destination_country' => $order->destination_country,
                'destination_city' => $order->destination_city,
                'special_notes' => $order->special_notes,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'reward_amount' => $order->reward_amount,
                'currency' => $order->currency,
                'total_items_amount' => $this->calculateTotal($order),
                'items' => $order->items->map(fn($item) => [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'weight' => $item->weight,
                    'store_link' => $item->store_link,
                    'currency' => $item->currency,
                ]),
                'created_at' => $order->created_at,
                'delivered_at' => $order->delivered_at,
                'delivery_confirmed_at' => $order->delivery_confirmed_at,
            ],
        ]);
    }

    private function calculateTotal(Order $order): float
    {
        $itemsCost = $order->items->sum(fn($item) => $item->price * $item->quantity);
        $reward = $order->accepted_counter_offer_amount > 0
            ? (float) $order->accepted_counter_offer_amount
            : (float) $order->reward_amount;
        $subtotal = $itemsCost + $reward;
        return round($subtotal * 1.105, 2);
    }
}