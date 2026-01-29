<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tip;

class TipService
{
    private const PREDEFINED_AMOUNTS = [5.00, 10.00];
    private const MIN_AMOUNT = 0.01;
    private const MAX_AMOUNT = 999.99;

    public function create(Order $order, $fromUserId, float $amount): Tip
    {
        if ($order->orderer_id !== $fromUserId) {
            throw new \Exception('Only orderer can give tips');
        }

        if ($order->status !== 'COMPLETED') {
            throw new \Exception('Can only tip on COMPLETED orders');
        }

        if (!$this->isValidAmount($amount)) {
            throw new \Exception('Tip amount must be between ' . self::MIN_AMOUNT . ' and ' . self::MAX_AMOUNT);
        }

        $toUserId = $order->assigned_picker_id;
        if (!$toUserId) {
            throw new \Exception('Order has no assigned picker');
        }

        return Tip::create([
            'order_id' => $order->id,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'amount' => round($amount, 2),
        ]);
    }

    public function getOrderTips(Order $order): array
    {
        $tips = $order->tips()->with('fromUser')->get();

        return $tips->map(fn($tip) => [
            'id' => $tip->id,
            'amount' => $tip->amount,
            'from_user' => [
                'id' => $tip->fromUser->id,
                'full_name' => $tip->fromUser->full_name,
            ],
            'created_at' => $tip->created_at,
        ])->toArray();
    }

    public function getUserTipsReceived($userId, int $page = 1, int $limit = 20): array
    {
        $limit = min($limit, 100);
        $limit = max($limit, 1);
        $page = max($page, 1);

        $query = Tip::where('to_user_id', $userId)
            ->with(['order', 'fromUser'])
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $tips = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return [
            'data' => $tips->map(fn($tip) => [
                'id' => $tip->id,
                'amount' => $tip->amount,
                'order_id' => $tip->order_id,
                'from_user' => [
                    'id' => $tip->fromUser->id,
                    'full_name' => $tip->fromUser->full_name,
                ],
                'created_at' => $tip->created_at,
            ])->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($page * $limit) < $total,
            ],
        ];
    }

    public function getTotalTipsReceived($userId): float
    {
        return (float) Tip::where('to_user_id', $userId)->sum('amount');
    }

    private function isValidAmount(float $amount): bool
    {
        return $amount >= self::MIN_AMOUNT && $amount <= self::MAX_AMOUNT;
    }

    public function isPredefinedAmount(float $amount): bool
    {
        return in_array(round($amount, 2), self::PREDEFINED_AMOUNTS);
    }
}
