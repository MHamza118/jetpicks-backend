<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOffersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Offer::with(['order', 'offeredBy', 'order.orderer'])
                      ->whereNull('parent_offer_id');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('order', function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%");
            })->orWhereHas('offeredBy', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $offers = $query->orderBy('created_at', 'desc')
                        ->paginate($request->get('per_page', 15));

        $items = collect($offers->items())->map(function ($offer) {
            return [
                'id' => $offer->id,
                'order' => $offer->order ? [
                    'id' => $offer->order->id,
                    'orderer' => $offer->order->orderer ? [
                        'id' => $offer->order->orderer->id,
                        'full_name' => $offer->order->orderer->full_name,
                        'email' => $offer->order->orderer->email,
                    ] : null,
                ] : null,
                'picker' => $offer->offeredBy ? [
                    'id' => $offer->offeredBy->id,
                    'full_name' => $offer->offeredBy->full_name,
                    'email' => $offer->offeredBy->email,
                ] : null,
                'offer_type' => $offer->offer_type,
                'offer_amount' => $offer->offer_amount,
                'status' => $offer->status,
                'counter_offers_count' => $offer->childOffers()->count(),
                'created_at' => $offer->created_at,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $offer = Offer::with(['order', 'offeredBy', 'order.orderer', 'childOffers.offeredBy'])
                      ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $offer->id,
                'order' => $offer->order ? [
                    'id' => $offer->order->id,
                    'orderer' => $offer->order->orderer ? [
                        'id' => $offer->order->orderer->id,
                        'full_name' => $offer->order->orderer->full_name,
                        'email' => $offer->order->orderer->email,
                    ] : null,
                ] : null,
                'picker' => $offer->offeredBy ? [
                    'id' => $offer->offeredBy->id,
                    'full_name' => $offer->offeredBy->full_name,
                    'email' => $offer->offeredBy->email,
                ] : null,
                'offer_type' => $offer->offer_type,
                'offer_amount' => $offer->offer_amount,
                'status' => $offer->status,
                'counter_offers' => $offer->childOffers->map(fn($child) => [
                    'id' => $child->id,
                    'picker' => $child->offeredBy ? [
                        'id' => $child->offeredBy->id,
                        'full_name' => $child->offeredBy->full_name,
                        'email' => $child->offeredBy->email,
                    ] : null,
                    'offer_amount' => $child->offer_amount,
                    'status' => $child->status,
                    'created_at' => $child->created_at,
                ]),
                'created_at' => $offer->created_at,
            ],
        ]);
    }
}
