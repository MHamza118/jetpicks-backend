<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Services\ChatService;
use App\Models\ChatRoom;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService)
    {
    }

    //get all chat rooms only for the authenticated user(s)
    public function getRooms(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 20);
        $limit = min($limit, 100);
        $limit = max($limit, 1);
        $page = max($page, 1);

        $result = $this->chatService->getChatRooms($userId, $page, $limit);

        return response()->json($result);
    }

    //Messages in a chat room for the authenticated user(s)
    public function getMessages(string $roomId, Request $request): JsonResponse
    {
        $userId = auth()->id();
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 50);
        $limit = min($limit, 100);
        $limit = max($limit, 1);
        $page = max($page, 1);
        $room = ChatRoom::find($roomId);
        if (!$room) {
            return response()->json(['message' => 'Chat room not found'], 404);
        }
        if ($room->orderer_id !== $userId && $room->picker_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->chatService->getMessages($roomId, $page, $limit);
        return response()->json($result);
    }

    //send messages
    public function sendMessage(string $roomId, SendMessageRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $room = ChatRoom::find($roomId);
        if (!$room) {
            return response()->json(['message' => 'Chat room not found'], 404);
        }
        if ($room->orderer_id !== $userId && $room->picker_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $translateMessage = $request->input('translate', false);
        $message = $this->chatService->sendMessage(
            $roomId,
            $userId,
            $request->input('content'),
            $translateMessage
        );
        return response()->json([
            'message' => 'Message sent successfully',
            'data' => [
                'id' => $message->id,
                'chat_room_id' => $message->chat_room_id,
                'sender_id' => $message->sender_id,
                'content_original' => $message->content_original,
                'content_translated' => $message->content_translated,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    //mark message as read
    public function markAsRead(string $messageId): JsonResponse
    {
        $userId = auth()->id();
        $message = ChatMessage::with('chatRoom')->find($messageId);
        if (!$message) {
            return response()->json(['message' => 'Message not found'], 404);
        }
        $room = $message->chatRoom;
        if ($room->orderer_id !== $userId && $room->picker_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updatedMessage = $this->chatService->markMessageAsRead($messageId);
        return response()->json([
            'message' => 'Message marked as read',
            'data' => [
                'id' => $updatedMessage->id,
                'is_read' => $updatedMessage->is_read,
            ],
        ]);
    }
    //get or create chat room for order
    public function getOrCreateChatRoom(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $orderId = $request->input('order_id');
            $pickerId = $request->input('picker_id');

            if (!$orderId || !$pickerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'order_id and picker_id are required'
                ], 400);
            }

            // Check if chat room already exists between this orderer and picker
            // Handle both directions: orderer->picker and picker->orderer
            $existingRoom = ChatRoom::where(function ($query) use ($userId, $pickerId) {
                $query->where(function ($q) use ($userId, $pickerId) {
                    $q->where('orderer_id', $userId)
                      ->where('picker_id', $pickerId);
                })->orWhere(function ($q) use ($userId, $pickerId) {
                    $q->where('orderer_id', $pickerId)
                      ->where('picker_id', $userId);
                });
            })->first();
            
            if ($existingRoom) {
                return response()->json([
                    'success' => true,
                    'chatRoomId' => $existingRoom->id,
                    'message' => 'Chat room already exists'
                ]);
            }

            // Create new chat room
            $chatService = app(ChatService::class);
            $newRoom = $chatService->createChatRoom($orderId, $userId, $pickerId);

            return response()->json([
                'success' => true,
                'chatRoomId' => $newRoom->id,
                'message' => 'Chat room created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //get chat room details e.g: unread count
    public function getChatRoom(string $roomId): JsonResponse
    {
        $userId = auth()->id();
        $room = ChatRoom::with(['order', 'orderer', 'picker'])->find($roomId);
        if (!$room) {
            return response()->json(['message' => 'Chat room not found'], 404);
        }
        if ($room->orderer_id !== $userId && $room->picker_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $otherUser = $room->orderer_id === $userId ? $room->picker : $room->orderer;
        $unreadCount = ChatMessage::where('chat_room_id', $room->id)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
        return response()->json([
            'data' => [
                'id' => $room->id,
                'order_id' => $room->order_id,
                'orderer' => [
                    'id' => $room->orderer->id,
                    'full_name' => $room->orderer->full_name,
                    'avatar_url' => $room->orderer->avatar_url,
                ],
                'picker' => [
                    'id' => $room->picker->id,
                    'full_name' => $room->picker->full_name,
                    'avatar_url' => $room->picker->avatar_url,
                ],
                'other_user' => [
                    'id' => $otherUser->id,
                    'full_name' => $otherUser->full_name,
                    'avatar_url' => $otherUser->avatar_url,
                ],
                'unread_count' => $unreadCount,
                'created_at' => $room->created_at,
                'updated_at' => $room->updated_at,
            ],
        ]);
    }

    //get chat room by order id
    public function getChatRoomByOrderId(string $orderId): JsonResponse
    {
        $userId = auth()->id();
        $chatService = app(ChatService::class);
        $room = $chatService->getChatRoomByOrderId($orderId, $userId);
        
        if (!$room) {
            return response()->json(['message' => 'Chat room not found'], 404);
        }

        $room->load(['order', 'orderer', 'picker']);
        $otherUser = $room->orderer_id === $userId ? $room->picker : $room->orderer;
        $unreadCount = ChatMessage::where('chat_room_id', $room->id)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
        
        return response()->json([
            'data' => [
                'id' => $room->id,
                'order_id' => $room->order_id,
                'orderer' => [
                    'id' => $room->orderer->id,
                    'full_name' => $room->orderer->full_name,
                    'avatar_url' => $room->orderer->avatar_url,
                ],
                'picker' => [
                    'id' => $room->picker->id,
                    'full_name' => $room->picker->full_name,
                    'avatar_url' => $room->picker->avatar_url,
                ],
                'other_user' => [
                    'id' => $otherUser->id,
                    'full_name' => $otherUser->full_name,
                    'avatar_url' => $otherUser->avatar_url,
                ],
                'unread_count' => $unreadCount,
                'created_at' => $room->created_at,
                'updated_at' => $room->updated_at,
            ],
        ]);
    }
}
