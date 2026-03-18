<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Review::with(['reviewer', 'reviewee', 'order']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('reviewer', function ($subQ) use ($search) {
                    $subQ->where('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('reviewee', function ($subQ) use ($search) {
                    $subQ->where('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        if ($request->has('rating') && $request->rating) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 15));

        $items = collect($reviews->items())->map(function ($review) {
            return [
                'id' => $review->id,
                'reviewer' => $review->reviewer ? [
                    'id' => $review->reviewer->id,
                    'full_name' => $review->reviewer->full_name,
                    'email' => $review->reviewer->email,
                ] : null,
                'reviewee' => $review->reviewee ? [
                    'id' => $review->reviewee->id,
                    'full_name' => $review->reviewee->full_name,
                    'email' => $review->reviewee->email,
                ] : null,
                'order_id' => $review->order_id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $review = Review::with(['reviewer', 'reviewee', 'order'])->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $review->id,
                'reviewer' => $review->reviewer ? [
                    'id' => $review->reviewer->id,
                    'full_name' => $review->reviewer->full_name,
                    'email' => $review->reviewer->email,
                ] : null,
                'reviewee' => $review->reviewee ? [
                    'id' => $review->reviewee->id,
                    'full_name' => $review->reviewee->full_name,
                    'email' => $review->reviewee->email,
                ] : null,
                'order_id' => $review->order_id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
            ],
        ]);
    }
}
