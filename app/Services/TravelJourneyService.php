<?php

namespace App\Services;

use App\Models\TravelJourney;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TravelJourneyService
{
    public function createJourney(User $user, array $data): TravelJourney
    {
        // Mark all existing journeys as inactive
        $user->travelJourneys()->update(['is_active' => false]);
        
        // Create the new journey as active
        $data['is_active'] = true;
        return $user->travelJourneys()->create($data);
    }

    public function getUserJourneys(User $user): Collection
    {
        return $user->travelJourneys()
            ->where('is_active', true)
            ->orderBy('departure_date', 'asc')
            ->get();
    }

    public function updateJourney(User $user, string $journeyId, array $data): TravelJourney
    {
        $journey = $user->travelJourneys()
            ->where('id', $journeyId)
            ->where('is_active', true)
            ->firstOrFail();

        $journey->update($data);
        return $journey;
    }
}
