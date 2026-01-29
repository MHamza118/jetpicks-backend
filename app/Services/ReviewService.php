<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ReviewService
{
    public function submit(Order $order, $reviewerId, $revieweeId, int $rating, ?string $comment): Review
    {
        if ($order->status !== 'COMPLETED') {
            throw new \Exception('Order must be completed before review');
        }

        if ($order->orderer_id !== $reviewerId && $order->assigned_picker_id !== $reviewerId) {
            throw new \Exception('Only orderer or picker can review');
        }

        if ($reviewerId === $revieweeId) {
            throw new \Exception('Cannot review yourself');
        }

        if ($rating < 1 || $rating > 5) {
            throw new \Exception('Rating must be between 1 and 5');
        }

        if (Review::where('order_id', $order->id)
            ->where('reviewer_id', $reviewerId)
            ->exists()) {
            throw new \Exception('You already reviewed this order');
        }

        return Review::create([
            'order_id' => $order->id,
            'reviewer_id' => $reviewerId,
            'reviewee_id' => $revieweeId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    public function getForOrder(Order $order): ?Review
    {
        return Review::where('order_id', $order->id)->first();
    }

    public function getForUser($userId, int $page = 1, int $limit = 20): array
    {
        $limit = min($limit, 100);
        $offset = ($page - 1) * $limit;

        $total = Review::where('reviewee_id', $userId)->count();
        $reviews = Review::where('reviewee_id', $userId)
            ->with(['reviewer', 'order'])
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'data' => $reviews->map(fn($r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'reviewer' => [
                    'id' => $r->reviewer->id,
                    'full_name' => $r->reviewer->full_name,
                ],
                'order_id' => $r->order_id,
                'created_at' => $r->created_at,
            ])->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    public function getRating($userId): array
    {
        $reviews = Review::where('reviewee_id', $userId)->get();

        if ($reviews->isEmpty()) {
            return [
                'user_id' => $userId,
                'average_rating' => 0,
                'total_reviews' => 0,
            ];
        }

        $avg = $reviews->avg('rating');
        $count = $reviews->count();

        return [
            'user_id' => $userId,
            'average_rating' => round($avg, 2),
            'total_reviews' => $count,
        ];
    }
}
