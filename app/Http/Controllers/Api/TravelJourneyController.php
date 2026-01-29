<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TravelJourney\StoreTravelJourneyRequest;
use App\Http\Resources\JsonResource;
use App\Services\TravelJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelJourneyController extends Controller
{
    protected TravelJourneyService $service;

    public function __construct(TravelJourneyService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $journeys = $this->service->getUserJourneys($request->user());
        return response()->json([
            'data' => $journeys
        ]);
    }

    public function store(StoreTravelJourneyRequest $request): JsonResponse
    {
        $journey = $this->service->createJourney($request->user(), $request->validated());
        return response()->json([
            'message' => 'Travel journey created successfully.',
            'data' => $journey
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'luggage_weight_capacity' => 'required|string|max:50',
        ]);

        $journey = $this->service->updateJourney($request->user(), $id, $validated);
        return response()->json([
            'message' => 'Travel journey updated successfully.',
            'data' => $journey
        ]);
    }
}
