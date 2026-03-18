<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Offer;
use App\Models\TravelJourney;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function getStats(): JsonResponse
    {
        try {
            $totalUsers = User::count();
            $totalOrders = Order::count();
            $completedOrders = Order::where('status', 'completed')->count();
            $activePickers = User::where('is_active', true)->count();
            $travelJourneys = TravelJourney::where('is_active', true)->count();
            $pendingOffers = Offer::where('status', 'pending')->count();
            $avgRating = Review::avg('rating') ?? 0;

            $totalOrdersLastMonth = Order::where('created_at', '>=', now()->subMonth())->count();
            $totalOrdersLastYear = Order::where('created_at', '>=', now()->subYear())->count();
            $ordersChange = $totalOrdersLastYear > 0 ? round((($totalOrdersLastMonth / ($totalOrdersLastYear / 12)) - 1) * 100) : 0;

            $usersLastMonth = User::where('created_at', '>=', now()->subMonth())->count();
            $usersLastYear = User::where('created_at', '>=', now()->subYear())->count();
            $usersChange = $usersLastYear > 0 ? round((($usersLastMonth / ($usersLastYear / 12)) - 1) * 100) : 0;

            $conversionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;
            $conversionLastMonth = Order::where('created_at', '>=', now()->subMonth())->count();
            $conversionLastMonthCompleted = Order::where('status', 'completed')->where('created_at', '>=', now()->subMonth())->count();
            $conversionLastMonthRate = $conversionLastMonth > 0 ? round(($conversionLastMonthCompleted / $conversionLastMonth) * 100, 1) : 0;
            $conversionChange = round($conversionLastMonthRate - $conversionRate, 1);

            $pickersLastMonth = User::where('is_active', true)->where('created_at', '>=', now()->subMonth())->count();
            $pickersLastYear = User::where('is_active', true)->where('created_at', '>=', now()->subYear())->count();
            $pickersChange = $pickersLastYear > 0 ? round((($pickersLastMonth / ($pickersLastYear / 12)) - 1) * 100) : 0;

            $journeysLastMonth = TravelJourney::where('is_active', true)->where('created_at', '>=', now()->subMonth())->count();
            $journeysLastYear = TravelJourney::where('is_active', true)->where('created_at', '>=', now()->subYear())->count();
            $journeysChange = $journeysLastYear > 0 ? round((($journeysLastMonth / ($journeysLastYear / 12)) - 1) * 100) : 0;

            $offersLastMonth = Offer::where('status', 'pending')->where('created_at', '>=', now()->subMonth())->count();
            $offersLastYear = Offer::where('status', 'pending')->where('created_at', '>=', now()->subYear())->count();
            $offersChange = $offersLastYear > 0 ? round((($offersLastMonth / ($offersLastYear / 12)) - 1) * 100) : 0;

            $ratingLastMonth = Review::where('created_at', '>=', now()->subMonth())->avg('rating') ?? 0;
            $ratingChange = round($ratingLastMonth - $avgRating, 1);

            $revenue = $completedOrders * 100;
            $revenueLastMonth = Order::where('status', 'completed')->where('created_at', '>=', now()->subMonth())->count() * 100;
            $revenueLastYear = Order::where('status', 'completed')->where('created_at', '>=', now()->subYear())->count() * 100;
            $revenueChange = $revenueLastYear > 0 ? round((($revenueLastMonth / ($revenueLastYear / 12)) - 1) * 100) : 0;

            return response()->json([
                'data' => [
                    'total_users' => $totalUsers,
                    'total_users_change' => $usersChange,
                    'total_orders' => $totalOrders,
                    'total_orders_change' => $ordersChange,
                    'revenue' => $revenue,
                    'revenue_change' => $revenueChange,
                    'conversion_rate' => $conversionRate,
                    'conversion_change' => $conversionChange,
                    'active_pickers' => $activePickers,
                    'active_pickers_change' => $pickersChange,
                    'travel_journeys' => $travelJourneys,
                    'travel_journeys_change' => $journeysChange,
                    'pending_offers' => $pendingOffers,
                    'pending_offers_change' => $offersChange,
                    'avg_rating' => round($avgRating, 1),
                    'avg_rating_change' => $ratingChange,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Dashboard stats error: ' . $e->getMessage());
            return response()->json([
                'data' => [
                    'total_users' => 0,
                    'total_users_change' => 0,
                    'total_orders' => 0,
                    'total_orders_change' => 0,
                    'revenue' => 0,
                    'revenue_change' => 0,
                    'conversion_rate' => 0,
                    'conversion_change' => 0,
                    'active_pickers' => 0,
                    'active_pickers_change' => 0,
                    'travel_journeys' => 0,
                    'travel_journeys_change' => 0,
                    'pending_offers' => 0,
                    'pending_offers_change' => 0,
                    'avg_rating' => 0,
                    'avg_rating_change' => 0,
                ],
            ]);
        }
    }

    public function getRecentActivity(): JsonResponse
    {
        try {
            $recentOrders = Order::with(['orderer'])
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => '#' . substr($order->id, 0, 8),
                        'orderer' => $order->orderer?->full_name ?? 'Unknown',
                        'status' => ucfirst($order->status ?? 'pending'),
                        'updated_at' => $order->updated_at ?? now(),
                    ];
                });

            return response()->json([
                'data' => $recentOrders,
            ]);
        } catch (\Exception $e) {
            \Log::error('Recent activity error: ' . $e->getMessage());
            return response()->json([
                'data' => [],
            ]);
        }
    }
}
