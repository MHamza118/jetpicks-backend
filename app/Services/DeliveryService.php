<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DeliveryService
{
    public function markDelivered(Order $order, $userId, ?UploadedFile $proofFile = null): Order
    {
        if ($order->assigned_picker_id !== $userId) {
            throw new \Exception('Only assigned picker can mark as delivered');
        }

        if ($order->status !== 'ACCEPTED') {
            throw new \Exception('Order must be ACCEPTED to mark as delivered');
        }

        $updateData = [
            'status' => 'DELIVERED',
            'delivered_at' => now(),
        ];

        // Handle proof of delivery file upload
        if ($proofFile) {
            // Validate file size (max 100MB)
            $maxSize = 100 * 1024 * 1024; // 100MB
            if ($proofFile->getSize() > $maxSize) {
                throw new \Exception('File size exceeds 100MB limit');
            }

            // Validate file type - accept all common image formats and PDF
            $allowedMimes = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
                'image/heic',
                'image/heif',
                'image/x-heic',
                'image/x-heif',
                'application/pdf'
            ];
            
            $mimeType = $proofFile->getMimeType();
            
            // Also check by file extension for better compatibility
            $fileName = strtolower($proofFile->getClientOriginalName());
            $validExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.heic', '.heif', '.pdf'];
            $hasValidExtension = false;
            
            foreach ($validExtensions as $ext) {
                if (str_ends_with($fileName, $ext)) {
                    $hasValidExtension = true;
                    break;
                }
            }
            
            if (!in_array($mimeType, $allowedMimes) && !$hasValidExtension) {
                throw new \Exception('Invalid file type. Allowed: JPEG, PNG, WebP, GIF, HEIC, PDF');
            }

            // Store the file
            $path = $proofFile->store('delivery-proofs', 'public');
            $updateData['proof_of_delivery'] = $path;
        }

        $order->update($updateData);

        return $order;
    }

    public function confirmDelivery(Order $order, $userId): Order
    {
        if ($order->orderer_id !== $userId) {
            throw new \Exception('Only orderer can confirm delivery');
        }

        if ($order->status !== 'DELIVERED') {
            throw new \Exception('Order must be DELIVERED to confirm');
        }

        if ($order->delivery_issue_reported) {
            throw new \Exception('Cannot confirm delivery with reported issue');
        }

        $order->update([
            'status' => 'COMPLETED',
            'delivery_confirmed_at' => now(),
            'auto_confirmed' => false,
        ]);

        return $order;
    }

    public function reportIssue(Order $order, $userId): Order
    {
        if ($order->orderer_id !== $userId) {
            throw new \Exception('Only orderer can report issue');
        }

        if ($order->status !== 'DELIVERED') {
            throw new \Exception('Can only report issue on DELIVERED orders');
        }

        $order->update([
            'delivery_issue_reported' => true,
        ]);

        return $order;
    }

    public function getStatus(Order $order): array
    {
        $status = [
            'id' => $order->id,
            'status' => $order->status,
            'delivered_at' => $order->delivered_at,
            'delivery_confirmed_at' => $order->delivery_confirmed_at,
            'auto_confirmed' => $order->auto_confirmed,
            'delivery_issue_reported' => $order->delivery_issue_reported,
        ];

        if ($order->status === 'DELIVERED' && !$order->delivery_confirmed_at) {
            $deliveredAt = Carbon::parse($order->delivered_at);
            $deadline = $deliveredAt->addHours(48);
            $remaining = $deadline->diffInSeconds(now(), false);

            $status['confirmation_deadline'] = $deadline->toIso8601String();
            $status['hours_remaining'] = max(0, ceil($remaining / 3600));
        }

        return $status;
    }

    public function autoConfirmExpired(): int
    {
        $cutoff = now()->subHours(48);

        $count = Order::where('status', 'DELIVERED')
            ->where('delivered_at', '<=', $cutoff)
            ->where('delivery_confirmed_at', null)
            ->where('delivery_issue_reported', false)
            ->update([
                'status' => 'COMPLETED',
                'auto_confirmed' => true,
                'delivery_confirmed_at' => now(),
            ]);

        return $count;
    }
}
