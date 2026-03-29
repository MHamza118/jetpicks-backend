<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderNotificationService;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    /**
     * Create a payment intent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPaymentIntent(Request $request)
    {
        // make sure we at least have a Stripe secret configured, otherwise the SDK will explode
        if (empty(env('STRIPE_SECRET'))) {
            \Log::error('StripeController::createPaymentIntent called without STRIPE_SECRET');
            return response()->json([
                'success' => false,
                'error' => 'Stripe configuration error',
                'message' => 'STRIPE_SECRET is not set on the backend.',
            ], 500);
        }

        try {
            $validated = $request->validate([
                'amount' => 'required|integer|min:1',
                'currency' => 'required|string|size:3',
                'description' => 'nullable|string',
                'customer_email' => 'nullable|email',
                'metadata' => 'nullable|array',
                'order_id' => 'nullable|string|exists:orders,id',
            ]);

            // Prepare metadata
            $metadata = $validated['metadata'] ?? [];
            
            // If order_id provided, check if it exists and get order details
            $order = null;
            if (!empty($validated['order_id'])) {
                $order = Order::find($validated['order_id']);
                
                // Check if order already paid
                if ($order && $order->payment_status === 'PAID') {
                    return response()->json([
                        'success' => false,
                        'error' => 'Order already paid',
                        'message' => 'This order has already been paid.',
                    ], 400);
                }
                
                if ($order) {
                    $metadata['order_id'] = $order->id;
                    $metadata['orderer_id'] = $order->orderer_id;
                }
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? null,
                'receipt_email' => $validated['customer_email'] ?? null,
                'metadata' => $metadata,
            ]);

            // Create payment record in database only if we have user_id
            $userId = auth()->id();
            if ($order) {
                $userId = $userId ?? $order->orderer_id;
            }
            
            if ($userId) {
                try {
                    Payment::create([
                        'order_id' => $order?->id,
                        'user_id' => $userId,
                        'stripe_payment_intent_id' => $paymentIntent->id,
                        'amount' => $validated['amount'] / 100, // Convert cents to dollars
                        'currency' => $validated['currency'],
                        'status' => 'PENDING',
                        'metadata' => json_encode($metadata),
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to create payment record: ' . $e->getMessage());
                    // Continue anyway - payment intent was created successfully
                }
            }

            // Update order if exists
            if ($order) {
                $order->update([
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'payment_status' => 'PENDING',
                ]);
            }

            return response()->json([
                'success' => true,
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('StripeController validation failed: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'error' => 'Validation error',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // This usually happens when Stripe rejects currency/amount (unsupported currency or too small)
            \Log::warning('StripeController invalid request: ' . $e->getMessage());

            $msg = $e->getMessage();
            // detect minimum-amount error and give friendlier message
            if (str_contains($msg, 'Amount must convert to at least')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Amount too small',
                    'message' => 'The order total ('.$validated['amount'].' '.$validated['currency'].') is too small for Stripe (minimum 50 cents USD). Please choose a supported currency or increase the amount.',
                ], 400);
            }

            // fall back to generic message
            return response()->json([
                'success' => false,
                'error' => 'Invalid payment parameters',
                'message' => $msg,
            ], 400);
        } catch (\Exception $e) {
            // log the exact Stripe / internal error so we can inspect backend logs
            \Log::error('StripeController::createPaymentIntent exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Payment intent creation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm payment intent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'paymentIntentId' => 'required|string',
            ]);

            $paymentIntent = PaymentIntent::retrieve($validated['paymentIntentId']);

            // If payment succeeded, update our database immediately
            if ($paymentIntent->status === 'succeeded') {
                $this->updatePaymentStatus($paymentIntent->id, 'SUCCEEDED');
            }

            return response()->json([
                'success' => true,
                'status' => $paymentIntent->status,
                'paymentIntentId' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to confirm payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update payment status in database
     */
    private function updatePaymentStatus(string $paymentIntentId, string $status)
    {
        try {
            // Find payment record
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
            
            if ($payment) {
                // Update payment status
                $updateData = ['status' => $status];
                
                if ($status === 'SUCCEEDED') {
                    $updateData['paid_at'] = now();
                } elseif ($status === 'FAILED') {
                    $updateData['failed_at'] = now();
                }
                
                $payment->update($updateData);

                // Update order payment status
                if ($payment->order_id) {
                    $order = Order::find($payment->order_id);
                    if ($order) {
                        $previousPaymentStatus = $order->payment_status;
                        $orderStatus = $status === 'SUCCEEDED' ? 'PAID' : 
                                      ($status === 'FAILED' ? 'FAILED' : 'PENDING');
                        
                        $orderUpdate = ['payment_status' => $orderStatus];
                        
                        if ($status === 'SUCCEEDED') {
                            $orderUpdate['payment_completed_at'] = now();
                        }
                        
                        $order->update($orderUpdate);

                        // Notify assigned picker only when payment transitions to PAID.
                        if ($previousPaymentStatus !== 'PAID' && $orderStatus === 'PAID') {
                            app(OrderNotificationService::class)->notifyPickerPaymentConfirmed($order);
                        }
                        
                        \Log::info("Order {$order->id} payment status updated to {$orderStatus}");
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error updating payment status: ' . $e->getMessage());
        }
    }

    /**
     * Check order payment status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkOrderPaymentStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string|exists:orders,id',
            ]);

            $order = Order::with('payments')->find($validated['order_id']);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Order not found',
                ], 404);
            }

            // Check if user has access to this order
            $user = auth()->user();
            if ($user && $order->orderer_id !== $user->id && $order->assigned_picker_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to order',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'payment_status' => $order->payment_status,
                'is_paid' => $order->isPaid(),
                'payment_completed_at' => $order->payment_completed_at,
                'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                'order_status' => $order->status,
                'latest_payment' => $order->payments()->latest()->first(),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to check payment status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Stripe webhook
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleWebhook(Request $request)
    {
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');
        
        $sig_header = $request->header('Stripe-Signature');
        $body = $request->getContent();

        try {
            $event = Webhook::constructEvent(
                $body,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response('', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return response('', 400);
        }

        // Handle the event
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event['data']['object'];
                $this->handlePaymentSucceeded($paymentIntent);
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event['data']['object'];
                $this->handlePaymentFailed($paymentIntent);
                break;

            case 'charge.refunded':
                $charge = $event['data']['object'];
                $this->handleChargeRefunded($charge);
                break;

            default:
                // Unhandled event type
                break;
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSucceeded($paymentIntent)
    {
        \Log::info('Payment succeeded: ' . $paymentIntent['id']);

        try {
            $this->updatePaymentStatus($paymentIntent['id'], 'SUCCEEDED');
            
            // Send confirmation email if needed
            // TODO: Implement email notification
            
        } catch (\Exception $e) {
            \Log::error('Error handling payment success: ' . $e->getMessage());
        }
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($paymentIntent)
    {
        \Log::info('Payment failed: ' . $paymentIntent['id']);

        try {
            $this->updatePaymentStatus($paymentIntent['id'], 'FAILED');
            
            // Send failure notification
            // TODO: Implement failure notification
            
        } catch (\Exception $e) {
            \Log::error('Error handling payment failure: ' . $e->getMessage());
        }
    }

    /**
     * Handle refunded charge
     */
    private function handleChargeRefunded($charge)
    {
        \Log::info('Charge refunded: ' . $charge['id']);

        try {
            // Find payment record by payment intent
            $paymentIntentId = $charge['payment_intent'] ?? null;
            
            if ($paymentIntentId) {
                $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
                
                if ($payment) {
                    // Update payment status
                    $payment->update([
                        'status' => 'REFUNDED',
                        'refunded_at' => now(),
                    ]);

                    // Update order payment status
                    if ($payment->order_id) {
                        $order = Order::find($payment->order_id);
                        if ($order) {
                            $order->update([
                                'payment_status' => 'REFUNDED',
                            ]);
                        }
                    }
                }
            }

            // Send refund notification to user
            // TODO: Implement refund notification
            
        } catch (\Exception $e) {
            \Log::error('Error handling refund: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STRIPE CONNECT — Bank onboarding for Jetbuyers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/stripe/onboarding-link
     *
     * Generates a Stripe hosted onboarding URL for the Jetbuyer.
     * The app opens this URL in a webview. Stripe handles KYC,
     * bank account verification, and identity verification.
     *
     * On return from Stripe, the app calls /stripe/onboarding-status
     * to check verification state.
     */
    public function createOnboardingLink(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();

            // Create a Stripe Express Connected Account if the user doesn't have one yet
            if (!$user->stripe_connect_account_id) {
                $account = \Stripe\Account::create([
                    'type'    => 'express',
                    'country' => $this->resolveStripeCountry($user->country),
                    'email'   => $user->email,
                    'capabilities' => [
                        'transfers' => ['requested' => true],
                        'card_payments' => ['requested' => false],
                    ],
                    'metadata' => [
                        'jetpicks_user_id' => $user->id,
                    ],
                ]);

                $user->update([
                    'stripe_connect_account_id' => $account->id,
                    'stripe_connect_status'     => 'pending',
                ]);
            }

            // Create an Account Link (hosted onboarding URL)
            $accountLink = \Stripe\AccountLink::create([
                'account'     => $user->stripe_connect_account_id,
                'refresh_url' => env('APP_URL') . '/api/stripe/onboarding-refresh',
                'return_url'  => env('APP_URL') . '/api/stripe/onboarding-return',
                'type'        => 'account_onboarding',
            ]);

            return response()->json([
                'url'        => $accountLink->url,
                'expires_at' => $accountLink->expires_at,
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Stripe onboarding link failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to generate onboarding link: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/stripe/onboarding-status
     *
     * Check the verification status of the user's Stripe Connect account.
     * Called by the app after the user returns from Stripe onboarding webview.
     */
    public function getOnboardingStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (!$user->stripe_connect_account_id) {
            return response()->json([
                'status'     => 'not_started',
                'is_verified' => false,
            ]);
        }

        try {
            $account = \Stripe\Account::retrieve($user->stripe_connect_account_id);

            $payoutsEnabled  = $account->payouts_enabled ?? false;
            $detailsSubmitted = $account->details_submitted ?? false;
            $status = $payoutsEnabled ? 'verified' : ($detailsSubmitted ? 'pending' : 'incomplete');

            // Update local status
            $user->update(['stripe_connect_status' => $status]);

            // Get bank account details (masked)
            $bankAccount = null;
            if ($account->external_accounts && $account->external_accounts->total_count > 0) {
                $ext = $account->external_accounts->data[0];
                $bankAccount = [
                    'bank_name'      => $ext->bank_name ?? null,
                    'last4'          => $ext->last4 ?? null,
                    'routing_number' => isset($ext->routing_number)
                        ? '••-' . substr($ext->routing_number, -2)
                        : null,
                    'currency'       => $ext->currency ?? null,
                    'country'        => $ext->country ?? null,
                ];
            }

            return response()->json([
                'status'           => $status,
                'is_verified'      => $payoutsEnabled,
                'details_submitted' => $detailsSubmitted,
                'payouts_enabled'  => $payoutsEnabled,
                'bank_account'     => $bankAccount,
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Stripe account retrieve failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not retrieve account status'], 500);
        }
    }

    /**
     * POST /api/stripe/payouts
     *
     * Trigger a manual payout from the Jetbuyer's Stripe Connect account
     * to their linked bank account. This is Option B (manual payouts).
     *
     * Body: { "amount_pence": 2500, "currency": "gbp" }
     *       Leave amount empty to withdraw full available balance.
     */
    public function createPayout(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'amount_pence' => 'nullable|integer|min:100', // min £1.00
            'currency'     => 'nullable|string|size:3',
        ]);

        $user = $request->user();

        if (!$user->stripe_connect_account_id) {
            return response()->json(['message' => 'Bank account not set up. Please complete bank verification first.'], 400);
        }

        if ($user->stripe_connect_status !== 'verified') {
            return response()->json(['message' => 'Bank account not yet verified. Please complete the Stripe onboarding.'], 400);
        }

        try {
            // Get available balance on the connected account
            $balance = \Stripe\Balance::retrieve([], [
                'stripe_account' => $user->stripe_connect_account_id,
            ]);

            $available = collect($balance->available)->firstWhere('currency', $request->input('currency', $user->wallet_currency ?? 'gbp'));
            $availableAmount = $available['amount'] ?? 0;

            if ($availableAmount <= 0) {
                return response()->json(['message' => 'No funds available to withdraw.'], 400);
            }

            $payoutAmount = $request->input('amount_pence') ?? $availableAmount;

            if ($payoutAmount > $availableAmount) {
                return response()->json([
                    'message'   => 'Requested amount exceeds available balance.',
                    'available' => $availableAmount,
                ], 400);
            }

            $payout = \Stripe\Payout::create([
                'amount'   => $payoutAmount,
                'currency' => $request->input('currency', $user->wallet_currency ?? 'gbp'),
            ], [
                'stripe_account' => $user->stripe_connect_account_id,
            ]);

            \Log::info('Stripe payout created', [
                'user_id'       => $user->id,
                'payout_id'     => $payout->id,
                'amount_pence'  => $payoutAmount,
            ]);

            return response()->json([
                'message'        => 'Payout initiated. Funds typically arrive within 1–2 business days.',
                'payout_id'      => $payout->id,
                'amount_pence'   => $payout->amount,
                'currency'       => $payout->currency,
                'arrival_date'   => date('Y-m-d', $payout->arrival_date),
                'status'         => $payout->status,
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Stripe payout failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Payout failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/stripe/wallet
     * Get the Jetbuyer's current wallet balance from their Stripe Connect account.
     */
    public function getWalletBalance(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        if (!$user->stripe_connect_account_id || $user->stripe_connect_status !== 'verified') {
            return response()->json([
                'available_pence' => 0,
                'pending_pence'   => 0,
                'currency'        => $user->wallet_currency ?? 'gbp',
                'is_verified'     => false,
            ]);
        }

        try {
            $balance  = \Stripe\Balance::retrieve([], ['stripe_account' => $user->stripe_connect_account_id]);
            $currency = $user->wallet_currency ?? 'gbp';

            $available = collect($balance->available)->firstWhere('currency', $currency);
            $pending   = collect($balance->pending)->firstWhere('currency', $currency);

            return response()->json([
                'available_pence' => $available['amount'] ?? 0,
                'pending_pence'   => $pending['amount'] ?? 0,
                'currency'        => $currency,
                'is_verified'     => true,
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json(['message' => 'Could not retrieve balance'], 500);
        }
    }

    /**
     * Resolve country name to Stripe-compatible country code.
     */
    private function resolveStripeCountry(?string $country): string
    {
        $map = [
            'United Kingdom' => 'GB',
            'United States'  => 'US',
            'Poland'         => 'PL',
            'Italy'          => 'IT',
            'France'         => 'FR',
            'Germany'        => 'DE',
            'Spain'          => 'ES',
            'Romania'        => 'RO',
            'Hungary'        => 'HU',
        ];
        return $map[$country] ?? 'GB';
    }
}