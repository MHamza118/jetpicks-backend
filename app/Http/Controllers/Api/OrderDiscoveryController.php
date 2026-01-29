<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderDiscoveryService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderDiscoveryController extends Controller
{
    protected OrderDiscoveryService $orderDiscoveryService;
    protected DashboardService $dashboardService;
    public function __construct(
        OrderDiscoveryService $orderDiscoveryService,
        DashboardService $dashboardService
    ) {
        $this->orderDiscoveryService = $orderDiscoveryService;
        $this->dashboardService = $dashboardService;
    }

    
    public function getAvailableOrders(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = min((int) $request->query('limit', 20), 100);

        $orders = $this->orderDiscoveryService->getAvailableOrders(
            $request->user()->id,
            $page,
            $limit
        );

        return response()->json($orders);
    }

    public function searchOrders(Request $request): JsonResponse
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
        $results = $this->orderDiscoveryService->searchOrders($query, $page, $limit);
        return response()->json($results);
    }

    public function getPickerDashboard(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = min((int) $request->query('limit', 20), 100);

        $dashboard = $this->dashboardService->getPickerDashboard(
            $request->user()->id,
            $page,
            $limit
        );

        return response()->json([
            'data' => $dashboard
        ]);
    }

    public function getOrdererDashboard(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = min((int) $request->query('limit', 20), 100);

        $dashboard = $this->dashboardService->getOrdererDashboard(
            $request->user()->id,
            $page,
            $limit
        );

        return response()->json([
            'data' => $dashboard
        ]);
    }
}
