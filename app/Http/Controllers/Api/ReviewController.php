<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews)
    {
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        try {
            $order = Order::findOrFail($request->order_id);
            $review = $this->reviews->submit(
                $order,
                auth()->id(),
                $request->reviewee_id,
                $request->rating,
                $request->comment
            );

            return response()->json([
                'data' => [
                    'id' => $review->id,
                    'order_id' => $review->order_id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getForOrder(Order $order): JsonResponse
    {
        $review = $this->reviews->getForOrder($order);

        if (!$review) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'reviewer' => [
                    'id' => $review->reviewer->id,
                    'full_name' => $review->reviewer->full_name,
                ],
                'created_at' => $review->created_at,
            ],
        ]);
    }

    public function getUserReviews(User $user, Request $request): JsonResponse
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 20);

        $result = $this->reviews->getForUser($user->id, $page, $limit);

        return response()->json($result);
    }

    public function getUserRating(User $user): JsonResponse
    {
        $rating = $this->reviews->getRating($user->id);

        return response()->json(['data' => $rating]);
    }
}
