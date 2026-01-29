<?php

namespace App\Services;

use App\Models\Notification;
use Carbon\Carbon;

class NotificationService
{
    public function create(string $userId, string $type, string $title, string $message, ?string $entityId = null, ?array $data = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'entity_id' => $entityId,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
            'notification_shown_at' => null,
        ]);
    }

    public function markRead(string $id, string $userId): Notification
    {
        $notification = $this->find($id, $userId);
        $notification->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);
        return $notification->fresh();
    }

    public function delete(string $id, string $userId): void
    {
        $notification = $this->find($id, $userId);
        $notification->delete();
    }

    public function find(string $id, string $userId): Notification
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            abort(404, 'Notification not found');
        }

        return $notification;
    }

    public function getUserNotifications(string $userId, int $page = 1, int $limit = 20): array
    {
        $notifications = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => $notifications->map(fn($n) => $this->format($n))->toArray(),
            'pagination' => [
                'total' => $notifications->total(),
                'page' => $notifications->currentPage(),
                'limit' => $notifications->perPage(),
                'has_more' => $notifications->hasMorePages(),
            ],
        ];
    }

    public function getPendingCount(string $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->whereNull('notification_shown_at')
            ->count();
    }

    public function getUnreadCount(string $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function getPendingNotifications(string $userId): array
    {
        $notifications = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->whereNull('notification_shown_at')
            ->orderBy('created_at', 'asc')
            ->get();

        return $notifications->map(fn($n) => $this->formatPending($n))->toArray();
    }

    public function markAsShown(string $id, string $userId): bool
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            abort(404, 'Notification not found');
        }

        // Idempotent: only update if not already shown
        if ($notification->notification_shown_at === null) {
            $notification->update([
                'notification_shown_at' => Carbon::now(),
            ]);
        }

        return true;
    }

    public function format(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'entity_id' => $notification->entity_id,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at,
            'notification_shown_at' => $notification->notification_shown_at,
            'created_at' => $notification->created_at,
        ];
    }

    public function formatPending(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'entity_id' => $notification->entity_id,
            'title' => $notification->title,
            'message' => $notification->message,
            'created_at' => $notification->created_at,
        ];
    }

    public function notifyOrderStatusChange(string $userId, string $orderId, string $status): void
    {
        $this->create(
            $userId,
            'ORDER_STATUS_CHANGED',
            "Order Status Updated",
            "Your order status has changed to {$status}",
            ['order_id' => $orderId, 'status' => $status]
        );
    }

    public function notifyNewMessage(string $userId, string $roomId, string $senderName): void
    {
        $this->create(
            $userId,
            'NEW_MESSAGE',
            "New Message from {$senderName}",
            "You have a new message",
            ['room_id' => $roomId, 'sender_name' => $senderName]
        );
    }

    public function notifyNewOffer(string $userId, string $offerId, string $offerAmount): void
    {
        $this->create(
            $userId,
            'NEW_OFFER',
            "New Offer Received",
            "You received a new offer for {$offerAmount}",
            $offerId,
            ['offer_id' => $offerId, 'amount' => $offerAmount]
        );
    }

    public function deleteUserNotifications(string $userId): void
    {
        Notification::where('user_id', $userId)->delete();
    }
}
