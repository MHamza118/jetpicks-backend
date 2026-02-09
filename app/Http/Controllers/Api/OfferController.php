<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOfferRequest;
use App\Services\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    protected OfferService $offerService;

    public function __construct(OfferService $offerService)
    {
        $this->offerService = $offerService;
    }
    //create counter offer
    public function store(CreateOfferRequest $request): JsonResponse
    {
        try {
            \Log::info('OfferController::store called', [
                'order_id' => $request->input('order_id'),
                'offer_amount' => $request->input('offer_amount'),
                'offer_amount_type' => gettype($request->input('offer_amount')),
                'parent_offer_id' => $request->input('parent_offer_id'),
            ]);

            $offer = $this->offerService->createCounterOffer(
                $request->input('order_id'),
                $request->user()->id,
                (float) $request->input('offer_amount'),
                $request->input('parent_offer_id')
            );
            return response()->json([
                'message' => 'Counter-offer created successfully',
                'data' => [
                    'id' => $offer->id,
                    'order_id' => $offer->order_id,
                    'offered_by_user_id' => $offer->offered_by_user_id,
                    'offer_type' => $offer->offer_type,
                    'offer_amount' => $offer->offer_amount,
                    'parent_offer_id' => $offer->parent_offer_id,
                    'status' => $offer->status,
                    'created_at' => $offer->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('OfferController::store error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function accept(Request $request, string $offerId): JsonResponse
    {
        try {
            \Log::info('accept called', ['offerId' => $offerId, 'userId' => $request->user()->id]);
            
            $result = $this->offerService->acceptOffer($offerId, $request->user()->id);
            
            \Log::info('accept success', ['result' => $result]);
            
            return response()->json([
                'message' => 'Offer accepted successfully',
                'data' => [
                    'offer' => [
                        'id' => $result['offer']->id,
                        'status' => $result['offer']->status,
                    ],
                    'order' => [
                        'id' => $result['order']->id,
                        'assigned_picker_id' => $result['order']->assigned_picker_id,
                        'status' => $result['order']->status,
                        'reward_amount' => $result['order']->reward_amount,
                        'accepted_counter_offer_amount' => $result['order']->accepted_counter_offer_amount,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('accept error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

        //reject offer (picker rejecting orderer's offer)
    public function reject(Request $request, string $offerId): JsonResponse
    {
        try {
            $offer = $this->offerService->rejectOffer($offerId, $request->user()->id);

            return response()->json([
                'message' => 'Offer rejected successfully',
                'data' => [
                    'id' => $offer->id,
                    'status' => $offer->status,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get offer history for an order
     */
    public function getHistory(Request $request, string $orderId): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = min((int) $request->query('limit', 50), 100);

        $history = $this->offerService->getOfferHistory($orderId, $page, $limit);

        return response()->json($history);
    }
}
