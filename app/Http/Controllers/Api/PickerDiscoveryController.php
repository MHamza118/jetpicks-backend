<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PickerDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PickerDiscoveryController extends Controller
{
    protected PickerDiscoveryService $pickerDiscoveryService;

    public function __construct(PickerDiscoveryService $pickerDiscoveryService)
    {
        $this->pickerDiscoveryService = $pickerDiscoveryService;
    }


    public function getAvailablePickers(Request $request, string $orderId): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = min((int) $request->query('limit', 20), 100);

        $pickers = $this->pickerDiscoveryService->getAvailablePickers(
            $orderId,
            $page,
            $limit
        );

        return response()->json($pickers);
    }


    public function getPickerDetails(Request $request, string $pickerId): JsonResponse
    {
        $details = $this->pickerDiscoveryService->getPickerDetails($pickerId);

        if (empty($details)) {
            return response()->json([
                'message' => 'Picker not found',
            ], 404);
        }

        return response()->json([
            'data' => $details,
        ]);
    }


    public function searchPickers(Request $request): JsonResponse
    {
        $query = $request->query('q');

        if (!$query || strlen($query) < 2) {
            return response()->json([
                'message' => 'Search query must be at least 2 characters',
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => 1,
                    'limit' => 20,
                    'has_more' => false,
                ],
            ], 400);
        }

        $page = (int) $request->query('page', 1);
        $limit = min((int) $request->query('limit', 20), 100);

        $results = $this->pickerDiscoveryService->searchPickers($query, $page, $limit);

        return response()->json($results);
    }
}
