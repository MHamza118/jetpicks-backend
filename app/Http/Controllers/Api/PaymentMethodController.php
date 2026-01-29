<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(private PaymentMethodService $service) {}

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $method = $this->service->create(auth()->user(), $request->validated());

        return response()->json([
            'data' => [
                'id' => $method->id,
                'method_type' => $method->method_type,
                'card_holder_name' => $method->card_holder_name,
                'card_last_four' => $method->card_last_four,
                'card_brand' => $method->card_brand,
                'expiry_month' => $method->expiry_month,
                'expiry_year' => $method->expiry_year,
                'paypal_email' => $method->paypal_email,
                'is_default' => $method->is_default,
                'created_at' => $method->created_at,
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $limit = min((int)$request->query('limit', 20), 100);
        $page = max((int)$request->query('page', 1), 1);

        $result = $this->service->getUserMethods(auth()->user(), $limit, $page);

        return response()->json([
            'data' => $result['data']->map(fn($m) => [
                'id' => $m->id,
                'method_type' => $m->method_type,
                'card_holder_name' => $m->card_holder_name,
                'card_last_four' => $m->card_last_four,
                'card_brand' => $m->card_brand,
                'expiry_month' => $m->expiry_month,
                'expiry_year' => $m->expiry_year,
                'paypal_email' => $m->paypal_email,
                'is_default' => $m->is_default,
                'created_at' => $m->created_at,
            ]),
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(PaymentMethod $method): JsonResponse
    {
        if ($method->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => [
                'id' => $method->id,
                'method_type' => $method->method_type,
                'card_holder_name' => $method->card_holder_name,
                'card_last_four' => $method->card_last_four,
                'card_brand' => $method->card_brand,
                'expiry_month' => $method->expiry_month,
                'expiry_year' => $method->expiry_year,
                'paypal_email' => $method->paypal_email,
                'is_default' => $method->is_default,
                'created_at' => $method->created_at,
            ],
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $method): JsonResponse
    {
        if ($method->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $method = $this->service->update($method, $request->validated());

        return response()->json([
            'data' => [
                'id' => $method->id,
                'method_type' => $method->method_type,
                'card_holder_name' => $method->card_holder_name,
                'card_last_four' => $method->card_last_four,
                'card_brand' => $method->card_brand,
                'expiry_month' => $method->expiry_month,
                'expiry_year' => $method->expiry_year,
                'paypal_email' => $method->paypal_email,
                'is_default' => $method->is_default,
                'created_at' => $method->created_at,
            ],
        ]);
    }

    public function destroy(PaymentMethod $method): JsonResponse
    {
        if ($method->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->service->delete($method);

        return response()->json(null, 204);
    }

    public function setDefault(PaymentMethod $method): JsonResponse
    {
        if ($method->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $method = $this->service->setDefault($method);

        return response()->json([
            'data' => [
                'id' => $method->id,
                'method_type' => $method->method_type,
                'card_holder_name' => $method->card_holder_name,
                'card_last_four' => $method->card_last_four,
                'card_brand' => $method->card_brand,
                'expiry_month' => $method->expiry_month,
                'expiry_year' => $method->expiry_year,
                'paypal_email' => $method->paypal_email,
                'is_default' => $method->is_default,
                'created_at' => $method->created_at,
            ],
        ]);
    }
}
