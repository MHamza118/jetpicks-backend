<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with('user');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($subQ) use ($search) {
                      $subQ->where('full_name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('is_read') && $request->is_read !== '') {
            $query->where('is_read', $request->is_read === 'true' ? 1 : 0);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')
                               ->paginate($request->get('per_page', 15));

        $items = collect($notifications->items())->map(function ($notification) {
            return [
                'id' => $notification->id,
                'user' => $notification->user ? [
                    'id' => $notification->user->id,
                    'full_name' => $notification->user->full_name,
                    'email' => $notification->user->email,
                ] : null,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $notification = Notification::with('user')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $notification->id,
                'user' => $notification->user ? [
                    'id' => $notification->user->id,
                    'full_name' => $notification->user->full_name,
                    'email' => $notification->user->email,
                ] : null,
                'type' => $notification->type,
                'entity_id' => $notification->entity_id,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ],
        ]);
    }
}
