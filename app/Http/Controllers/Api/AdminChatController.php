<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ChatRoom::with(['orderer', 'picker', 'order'])
            ->withCount('messages')
            ->with(['messages' => function ($q) {
                $q->latest()->limit(1);
            }]);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('orderer', function ($subQ) use ($search) {
                    $subQ->where('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('picker', function ($subQ) use ($search) {
                    $subQ->where('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        $chatRooms = $query->orderBy('updated_at', 'desc')
                           ->paginate($request->get('per_page', 15));

        $items = collect($chatRooms->items())->map(function ($room) {
            $lastMessage = $room->messages->first();
            
            return [
                'id' => $room->id,
                'orderer' => $room->orderer ? [
                    'id' => $room->orderer->id,
                    'full_name' => $room->orderer->full_name,
                    'email' => $room->orderer->email,
                ] : null,
                'picker' => $room->picker ? [
                    'id' => $room->picker->id,
                    'full_name' => $room->picker->full_name,
                    'email' => $room->picker->email,
                ] : null,
                'order_id' => $room->order_id,
                'messages_count' => $room->messages_count,
                'last_message' => $lastMessage ? $lastMessage->content_original : null,
                'last_activity' => $room->updated_at,
                'created_at' => $room->created_at,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $chatRooms->currentPage(),
                'last_page' => $chatRooms->lastPage(),
                'per_page' => $chatRooms->perPage(),
                'total' => $chatRooms->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $room = ChatRoom::with(['orderer', 'picker', 'order', 'messages.sender'])
            ->withCount('messages')
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $room->id,
                'orderer' => $room->orderer ? [
                    'id' => $room->orderer->id,
                    'full_name' => $room->orderer->full_name,
                    'email' => $room->orderer->email,
                ] : null,
                'picker' => $room->picker ? [
                    'id' => $room->picker->id,
                    'full_name' => $room->picker->full_name,
                    'email' => $room->picker->email,
                ] : null,
                'order_id' => $room->order_id,
                'messages_count' => $room->messages_count,
                'messages' => $room->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'sender' => [
                            'id' => $message->sender->id,
                            'full_name' => $message->sender->full_name,
                        ],
                        'content' => $message->content_original,
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at,
                    ];
                }),
                'created_at' => $room->created_at,
                'updated_at' => $room->updated_at,
            ],
        ]);
    }
}
