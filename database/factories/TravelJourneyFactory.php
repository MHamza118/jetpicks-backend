<?php

namespace Database\Factories;

use App\Models\TravelJourney;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TravelJourneyFactory extends Factory
{
    protected $model = TravelJourney::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'departure_country' => $this->faker->country(),
            'departure_city' => $this->faker->city(),
            'departure_date' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
            'arrival_country' => $this->faker->country(),
            'arrival_city' => $this->faker->city(),
            'arrival_date' => $this->faker->dateTimeBetween('+31 days', '+60 days'),
            'luggage_weight_capacity' => $this->faker->randomElement(['5kg', '10kg', '15kg', '20kg', '25kg']),
        ];
    }
}
