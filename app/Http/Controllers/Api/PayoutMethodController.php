<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayoutMethodRequest;
use App\Http\Requests\UpdatePayoutMethodRequest;
use App\Services\PayoutMethodService;
use Illuminate\Http\JsonResponse;

class PayoutMethodController extends Controller
{
    public function __construct(private PayoutMethodService $service) {}

    public function store(StorePayoutMethodRequest $request): JsonResponse
    {
        $method = $this->service->create(
            auth()->id(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Payout method created successfully',
            'data' => $this->service->format($method),
        ], 201);
    }

    public function index(): JsonResponse
    {
        $methods = $this->service->getUserMethods(auth()->id());

        return response()->json([
            'data' => $methods->map(fn($m) => $this->service->format($m))->toArray(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $method = $this->service->find($id, auth()->id());

        return response()->json([
            'data' => $this->service->format($method),
        ]);
    }

    public function update(UpdatePayoutMethodRequest $request, string $id): JsonResponse
    {
        $method = $this->service->update($id, auth()->id(), $request->validated());

        return response()->json([
            'message' => 'Payout method updated successfully',
            'data' => $this->service->format($method),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id, auth()->id());

        return response()->json([
            'message' => 'Payout method deleted successfully',
        ]);
    }

    public function setDefault(string $id): JsonResponse
    {
        $method = $this->service->setDefault($id, auth()->id());

        return response()->json([
            'message' => 'Default payout method updated',
            'data' => $this->service->format($method),
        ]);
    }
}
