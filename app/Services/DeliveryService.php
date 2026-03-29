<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DeliveryService
{
    const MILESTONES = [
        'pending','accepted','payment_secured','items_purchased',
        'departed','dropped_at_locker','ready_to_meet','delivered','completed',
    ];

    const OUTCOMES = ['all_delivered', 'partial_delivery', 'unable_to_deliver'];
    const METHODS  = ['meet_in_person', 'inpost_locker', 'inpost_home'];

    // ── Milestone updates (Jetbuyer) ───────────────────────────────────────

    public function markItemsPurchased(Order $order, string $userId): Order
    {
        $this->assertPicker($order, $userId);
        $this->assertStatus($order, ['ACCEPTED']);
        $order->update(['delivery_milestone' => 'items_purchased', 'items_purchased_at' => now()]);
        return $order->fresh();
    }

    public function markDeparted(Order $order, ?string $userId = null): Order
    {
        if ($userId) $this->assertPicker($order, $userId);
        $this->assertStatus($order, ['ACCEPTED']);
        $order->update(['delivery_milestone' => 'departed', 'departed_at' => now()]);
        return $order->fresh();
    }

    public function markDroppedAtLocker(Order $order, string $userId): Order
    {
        $this->assertPicker($order, $userId);
        $this->assertStatus($order, ['ACCEPTED']);
        $order->update(['delivery_milestone' => 'dropped_at_locker', 'dropped_at_locker_at' => now()]);
        return $order->fresh();
    }

    public function markReadyToMeet(Order $order, string $userId): Order
    {
        $this->assertPicker($order, $userId);
        $this->assertStatus($order, ['ACCEPTED']);
        $order->update(['delivery_milestone' => 'ready_to_meet', 'ready_to_meet_at' => now()]);
        return $order->fresh();
    }

    // ── 3-option delivery outcome (Jetbuyer) ──────────────────────────────

    public function submitDeliveryOutcome(
        Order $order,
        string $userId,
        string $outcome,
        ?string $notes = null,
        ?array $itemStatuses = null,
        ?UploadedFile $proofFile = null
    ): Order {
        $this->assertPicker($order, $userId);
        $this->assertStatus($order, ['ACCEPTED']);

        if (!in_array($outcome, self::OUTCOMES)) {
            throw new \Exception('Invalid delivery outcome. Must be: all_delivered, partial_delivery, or unable_to_deliver');
        }

        if (in_array($outcome, ['partial_delivery', 'unable_to_deliver']) && empty($notes)) {
            throw new \Exception('Notes are required for partial delivery or unable to deliver');
        }

        $updateData = [
            'delivery_outcome'  => $outcome,
            'delivery_notes'    => $notes,
            'delivery_milestone' => 'delivered',
            'status'            => 'DELIVERED',
            'delivered_at'      => now(),
        ];

        if ($outcome === 'partial_delivery' && !empty($itemStatuses)) {
            $updateData['item_delivery_statuses'] = $itemStatuses;
        }

        if (in_array($outcome, ['partial_delivery', 'unable_to_deliver'])) {
            $updateData['delivery_issue_reported'] = true;
        }

        if ($proofFile) {
            $updateData['proof_of_delivery'] = $this->storeProofFile($proofFile);
        }

        $order->update($updateData);
        return $order->fresh();
    }

    // ── Legacy (backward compat) ───────────────────────────────────────────

    public function markDelivered(Order $order, $userId, ?UploadedFile $proofFile = null): Order
    {
        return $this->submitDeliveryOutcome($order, $userId, 'all_delivered', null, null, $proofFile);
    }

    // ── Confirmation (Jetpicker) ───────────────────────────────────────────

    public function confirmDelivery(Order $order, $userId): Order
    {
        if ($order->orderer_id !== $userId) {
            throw new \Exception('Only the Jetpicker can confirm delivery');
        }

        if (!in_array($order->status, ['ACCEPTED', 'DELIVERED'])) {
            throw new \Exception('Order must be ACCEPTED or DELIVERED to confirm');
        }

        if ($order->status === 'ACCEPTED' && $order->payment_status !== 'PAID') {
            throw new \Exception('Payment must be completed before confirming delivery');
        }

        $order->update([
            'status'                  => 'COMPLETED',
            'delivery_milestone'      => 'completed',
            'delivery_confirmed_at'   => now(),
            'auto_confirmed'          => false,
        ]);

        return $order->fresh();
    }

    public function reportIssue(Order $order, $userId): Order
    {
        if ($order->orderer_id !== $userId) {
            throw new \Exception('Only the Jetpicker can report an issue');
        }

        if (!in_array($order->status, ['ACCEPTED', 'DELIVERED'])) {
            throw new \Exception('Can only report issue on ACCEPTED or DELIVERED orders');
        }

        $order->update(['delivery_issue_reported' => true]);
        return $order->fresh();
    }

    // ── Delivery method (set at order accepted) ────────────────────────────

    public function setDeliveryMethod(
        Order $order,
        string $userId,
        string $method,
        ?string $lockerId = null,
        ?string $deliveryAddress = null
    ): Order {
        if ($order->orderer_id !== $userId) {
            throw new \Exception('Only the Jetpicker can set the delivery method');
        }

        if (!in_array($method, self::METHODS)) {
            throw new \Exception('Invalid delivery method');
        }

        $updateData = ['delivery_method' => $method];

        if ($method === 'inpost_home' && $deliveryAddress) {
            $updateData['delivery_address'] = $deliveryAddress;
        }

        if ($lockerId) {
            $updateData['inpost_locker_id'] = $lockerId;
        }

        $order->update($updateData);
        return $order->fresh();
    }

    public function setInpostLocker(Order $order, string $userId, string $lockerId): Order
    {
        $this->assertPicker($order, $userId);
        $order->update(['inpost_locker_id' => $lockerId]);
        return $order->fresh();
    }

    // ── Status / tracking ──────────────────────────────────────────────────

    public function getStatus(Order $order): array
    {
        $data = [
            'id'                      => $order->id,
            'status'                  => $order->status,
            'delivery_milestone'      => $order->delivery_milestone ?? 'pending',
            'delivery_method'         => $order->delivery_method ?? 'meet_in_person',
            'delivery_outcome'        => $order->delivery_outcome,
            'delivery_notes'          => $order->delivery_notes,
            'item_delivery_statuses'  => $order->item_delivery_statuses,
            'inpost_locker_id'        => $order->inpost_locker_id,
            'inpost_tracking_number'  => $order->inpost_tracking_number,
            'items_purchased_at'      => $order->items_purchased_at?->toIso8601String(),
            'departed_at'             => $order->departed_at?->toIso8601String(),
            'dropped_at_locker_at'    => $order->dropped_at_locker_at?->toIso8601String(),
            'ready_to_meet_at'        => $order->ready_to_meet_at?->toIso8601String(),
            'delivered_at'            => $order->delivered_at?->toIso8601String(),
            'delivery_confirmed_at'   => $order->delivery_confirmed_at?->toIso8601String(),
            'auto_confirmed'          => $order->auto_confirmed,
            'delivery_issue_reported' => $order->delivery_issue_reported,
        ];

        if ($order->status === 'DELIVERED' && !$order->delivery_confirmed_at) {
            $deadline = Carbon::parse($order->delivered_at)->addHours(48);
            $data['confirmation_deadline'] = $deadline->toIso8601String();
            $data['hours_remaining'] = max(0, ceil($deadline->diffInSeconds(now(), false) / 3600));
        }

        return $data;
    }

    public function autoConfirmExpired(): int
    {
        return Order::where('status', 'DELIVERED')
            ->where('delivered_at', '<=', now()->subHours(48))
            ->whereNull('delivery_confirmed_at')
            ->where('delivery_issue_reported', false)
            ->update([
                'status'                => 'COMPLETED',
                'delivery_milestone'    => 'completed',
                'auto_confirmed'        => true,
                'delivery_confirmed_at' => now(),
            ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function assertPicker(Order $order, string $userId): void
    {
        if ($order->assigned_picker_id !== $userId) {
            throw new \Exception('Only the assigned Jetbuyer can perform this action');
        }
    }

    private function assertStatus(Order $order, array $allowed): void
    {
        if (!in_array($order->status, $allowed)) {
            throw new \Exception(
                'Order status must be one of: ' . implode(', ', $allowed) .
                '. Current: ' . $order->status
            );
        }
    }

    private function storeProofFile(UploadedFile $file): string
    {
        if ($file->getSize() > 100 * 1024 * 1024) {
            throw new \Exception('File size exceeds 100MB limit');
        }

        $allowedMimes = [
            'image/jpeg','image/png','image/webp','image/gif',
            'image/heic','image/heif','image/x-heic','image/x-heif','application/pdf',
        ];
        $validExts = ['.jpg','.jpeg','.png','.webp','.gif','.heic','.heif','.pdf'];
        $fileName  = strtolower($file->getClientOriginalName());
        $validExt  = collect($validExts)->contains(fn($e) => str_ends_with($fileName, $e));

        if (!in_array($file->getMimeType(), $allowedMimes) && !$validExt) {
            throw new \Exception('Invalid file type. Allowed: JPEG, PNG, WebP, GIF, HEIC, PDF');
        }

        return $file->store('delivery-proofs', 'public');
    }
}
