<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;
    public function definition(): array
    {
        return [
            'orderer_id' => User::factory(),
            'assigned_picker_id' => null,
            'origin_country' => $this->faker->country(),
            'origin_city' => $this->faker->city(),
            'destination_country' => $this->faker->country(),
            'destination_city' => $this->faker->city(),
            'special_notes' => $this->faker->sentence(),
            'reward_amount' => $this->faker->randomFloat(2, 10, 500),
            'status' => 'PENDING',
            'delivered_at' => null,
            'delivery_confirmed_at' => null,
            'auto_confirmed' => false,
            'delivery_issue_reported' => false,
        ];
    }

    public function accepted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'assigned_picker_id' => User::factory(),
                'status' => 'ACCEPTED',
            ];
        });
    }

    public function delivered(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'assigned_picker_id' => User::factory(),
                'status' => 'DELIVERED',
                'delivered_at' => now(),
            ];
        });
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'assigned_picker_id' => User::factory(),
                'status' => 'COMPLETED',
                'delivered_at' => now()->subHours(2),
                'delivery_confirmed_at' => now(),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'CANCELLED',
            ];
        });
    }
}
