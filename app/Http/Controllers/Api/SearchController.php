<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private SearchService $service) {}

    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $results = $this->service->searchUsers($query, $page, $limit);

        return response()->json($results);
    }

    public function searchOrders(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $status = $request->query('status');
        $minReward = $request->query('min_reward');
        $maxReward = $request->query('max_reward');
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $filters = array_filter([
            'status' => $status,
            'min_reward' => $minReward,
            'max_reward' => $maxReward,
        ]);

        $results = $this->service->searchOrders($query, $filters, $page, $limit);

        return response()->json($results);
    }

    public function searchPickers(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $originCity = $request->query('origin_city');
        $destinationCity = $request->query('destination_city');
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $filters = array_filter([
            'origin_city' => $originCity,
            'destination_city' => $destinationCity,
        ]);

        $results = $this->service->searchPickers($query, $filters, $page, $limit);

        return response()->json($results);
    }
}
