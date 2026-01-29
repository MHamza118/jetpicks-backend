<?php

namespace App\Services;

use App\Models\ChatRoom;
use App\Models\ChatMessage;

class ChatService
{
    public function createChatRoom(string $orderId, string $ordererId, string $pickerId): ChatRoom
    {
        $existingRoom = ChatRoom::where('order_id', $orderId)->first();
        if ($existingRoom) {
            return $existingRoom;
        }
        return ChatRoom::create([
            'order_id' => $orderId,
            'orderer_id' => $ordererId,
            'picker_id' => $pickerId,
        ]);
    }

    public function getChatRooms(string $userId, int $page = 1, int $limit = 20): array
    {
        $query = ChatRoom::where(function ($q) use ($userId) {
            $q->where('orderer_id', $userId)
                ->orWhere('picker_id', $userId);
        })
        ->with(['order', 'orderer', 'picker'])
        ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $rooms = $query->offset($offset)
            ->limit($limit)
            ->get();

        $formattedRooms = $rooms->map(function ($room) use ($userId) {
            $otherUser = $room->orderer_id === $userId ? $room->picker : $room->orderer;

            $lastMessage = ChatMessage::where('chat_room_id', $room->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $unreadCount = ChatMessage::where('chat_room_id', $room->id)
                ->where('sender_id', '!=', $userId)
                ->where('is_read', false)
                ->count();

            return [
                'id' => $room->id,
                'order_id' => $room->order_id,
                'other_user' => [
                    'id' => $otherUser->id,
                    'full_name' => $otherUser->full_name,
                    'avatar_url' => $otherUser->avatar_url,
                ],
                'last_message' => $lastMessage?->content_original,
                'last_message_time' => $lastMessage?->created_at,
                'unread_count' => $unreadCount,
            ];
        });

        return [
            'data' => $formattedRooms->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    public function getMessages(string $roomId, int $page = 1, int $limit = 50): array
    {
        $query = ChatMessage::where('chat_room_id', $roomId)
            ->with(['sender'])
            ->orderBy('created_at', 'asc');

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $messages = $query->offset($offset)
            ->limit($limit)
            ->get();

        $formattedMessages = $messages->map(function ($message) {
            return [
                'id' => $message->id,
                'chat_room_id' => $message->chat_room_id,
                'sender_id' => $message->sender_id,
                'sender' => [
                    'id' => $message->sender->id,
                    'full_name' => $message->sender->full_name,
                    'avatar_url' => $message->sender->avatar_url,
                ],
                'content_original' => $message->content_original,
                'content_translated' => $message->content_translated,
                'translation_enabled' => (bool) $message->content_translated,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at,
            ];
        });

        return [
            'data' => $formattedMessages->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    public function sendMessage(string $roomId, string $senderId, string $content, bool $translateMessage = false): ChatMessage
    {
        $translatedContent = null;

        if ($translateMessage) {
            $translatedContent = $this->translateMessage($content);
        }

        $message = ChatMessage::create([
            'chat_room_id' => $roomId,
            'sender_id' => $senderId,
            'content_original' => $content,
            'content_translated' => $translatedContent,
            'is_read' => false,
        ]);

        ChatRoom::find($roomId)->touch();

        return $message;
    }

    /**
     * For now this is a placeholder for translation service integration
     * In production, (any translation API provided by the client)
     */
    private function translateMessage(string $content): ?string
    {
        // Just a placeholder for now
        // this would call Translation API, in production.
        return null;
    }

    public function markMessageAsRead(string $messageId): ChatMessage
    {
        $message = ChatMessage::find($messageId);
        if ($message) {
            $message->update(['is_read' => true]);
        }
        return $message;
    }

    public function markRoomAsRead(string $roomId, string $userId): void
    {
        ChatMessage::where('chat_room_id', $roomId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}
