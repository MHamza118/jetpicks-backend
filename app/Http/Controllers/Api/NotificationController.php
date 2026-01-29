<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $notifications = $this->service->getUserNotifications(auth()->id(), $page, $limit);

        return response()->json($notifications);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = $this->service->markRead($id, auth()->id());

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $this->service->format($notification),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id, auth()->id());

        return response()->json([
            'message' => 'Notification deleted',
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = $this->service->getUnreadCount(auth()->id());

        return response()->json([
            'unread_count' => $count,
        ]);
    }
}
