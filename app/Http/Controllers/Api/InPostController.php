<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InPostController extends Controller
{
    public function __construct(private InPostService $inpost)
    {
    }

    /**
     * GET /api/inpost/lockers
     * Find InPost lockers near a city.
     *
     * Query params:
     *   city         — city name
     *   country      — country name or ISO code (e.g. "United Kingdom" or "GB")
     *   lat          — latitude (optional, more precise than city)
     *   lng          — longitude (optional)
     *   limit        — max results (default 10)
     */
    public function findLockers(Request $request): JsonResponse
    {
        $request->validate([
            'city'    => 'required_without:lat|string',
            'country' => 'required|string',
            'lat'     => 'nullable|numeric',
            'lng'     => 'nullable|numeric',
            'limit'   => 'nullable|integer|min:1|max:20',
        ]);

        $countryCode = $this->inpost->resolveCountryCode($request->input('country'));

        if (!$countryCode) {
            return response()->json([
                'message' => 'Country not recognised',
                'data'    => [],
            ], 400);
        }

        if (!$this->inpost->isAvailableIn($countryCode)) {
            return response()->json([
                'message' => 'InPost is not available in this country',
                'supported_countries' => InPostService::SUPPORTED_COUNTRIES,
                'data'    => [],
            ]);
        }

        $lockers = $this->inpost->findLockers(
            city:        $request->input('city', ''),
            countryCode: $countryCode,
            lat:         (float) $request->input('lat', 0),
            lng:         (float) $request->input('lng', 0),
            limit:       (int) $request->input('limit', 10),
        );

        return response()->json([
            'data'  => $lockers,
            'count' => count($lockers),
        ]);
    }

    /**
     * POST /api/inpost/shipments
     * Create an InPost shipment after Jetbuyer selects a locker.
     * Called by Jetbuyer when they confirm their locker selection.
     *
     * Body:
     *   order_id   — JetPicks order UUID
     *   locker_id  — InPost locker point name/ID
     */
    public function createShipment(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'  => 'required|string|exists:orders,id',
            'locker_id' => 'required|string',
        ]);

        $userId = auth()->id();
        $order  = Order::with(['orderer', 'picker'])->findOrFail($request->input('order_id'));

        // Only the assigned Jetbuyer (picker) can create the shipment
        if ($order->assigned_picker_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($order->delivery_method, ['inpost_locker', 'inpost_home'])) {
            return response()->json([
                'message' => 'Delivery method must be inpost_locker or inpost_home to create an InPost shipment',
            ], 400);
        }

        if ($order->inpost_tracking_number) {
            return response()->json([
                'message' => 'An InPost shipment has already been created for this order',
                'data'    => [
                    'tracking_number' => $order->inpost_tracking_number,
                    'locker_id'       => $order->inpost_locker_id,
                ],
            ]);
        }

        try {
            $lockerId  = $request->input('locker_id');
            $orderer   = $order->orderer;
            $picker    = $order->picker;

            $shipment = $this->inpost->createShipment(
                lockerId:       $lockerId,
                recipientEmail: $orderer->email,
                recipientPhone: $orderer->phone ?? '',
                recipientName:  $orderer->full_name ?? 'JetPicks User',
                senderName:     $picker->full_name ?? 'JetPicks Jetbuyer',
                orderId:        $order->id,
            );

            // Save tracking number and locker to the order
            $order->update([
                'inpost_locker_id'       => $lockerId,
                'inpost_tracking_number' => $shipment['tracking_number'],
                'delivery_milestone'     => 'items_purchased', // Jetbuyer is ready to drop off
            ]);

            return response()->json([
                'message' => 'InPost shipment created successfully',
                'data'    => $shipment,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/inpost/tracking/{trackingNumber}
     * Get tracking status for an InPost shipment.
     */
    public function getTracking(string $trackingNumber): JsonResponse
    {
        $tracking = $this->inpost->getTracking($trackingNumber);

        if (!$tracking) {
            return response()->json(['message' => 'Tracking information not available'], 404);
        }

        return response()->json(['data' => $tracking]);
    }

    /**
     * GET /api/inpost/availability
     * Check if InPost is available for a country (used by the app
     * to decide whether to show the InPost delivery option).
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $country = $request->query('country', '');
        $available = $this->inpost->isAvailableIn($country);

        return response()->json([
            'available'           => $available,
            'country'             => $country,
            'supported_countries' => InPostService::SUPPORTED_COUNTRIES,
            'fees'                => [
                'locker_collect'  => InPostService::lockerFee(),
                'home_delivery'   => InPostService::homeFee(),
                'currency'        => 'GBP', // Will expand to per-country currencies
            ],
        ]);
    }
}
