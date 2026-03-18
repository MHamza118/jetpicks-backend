<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['user', 'order']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $payments = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));

        $items = collect($payments->items())->map(function ($payment) {
            return [
                'id' => $payment->id,
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'full_name' => $payment->user->full_name,
                    'email' => $payment->user->email,
                ] : null,
                'order' => $payment->order ? [
                    'id' => $payment->order->id,
                ] : null,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_method_type' => $payment->payment_method_type,
                'card_last_four' => $payment->card_last_four,
                'card_brand' => $payment->card_brand,
                'paid_at' => $payment->paid_at,
                'failed_at' => $payment->failed_at,
                'created_at' => $payment->created_at,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $payment = Payment::with(['user', 'order'])->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'full_name' => $payment->user->full_name,
                    'email' => $payment->user->email,
                ] : null,
                'order' => $payment->order ? [
                    'id' => $payment->order->id,
                ] : null,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_method_type' => $payment->payment_method_type,
                'card_last_four' => $payment->card_last_four,
                'card_brand' => $payment->card_brand,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'error_message' => $payment->error_message,
                'paid_at' => $payment->paid_at,
                'failed_at' => $payment->failed_at,
                'refunded_at' => $payment->refunded_at,
                'created_at' => $payment->created_at,
            ],
        ]);
    }
}
