<?php

namespace App\Http\Requests\TravelJourney;

use Illuminate\Foundation\Http\FormRequest;

class StoreTravelJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'departure_country' => ['required', 'string', 'max:100'],
            'departure_city' => ['required', 'string', 'max:100'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'arrival_country' => ['required', 'string', 'max:100'],
            'arrival_city' => ['required', 'string', 'max:100'],
            'arrival_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'luggage_weight_capacity' => ['required', 'string', 'max:50'],
        ];
    }
}
