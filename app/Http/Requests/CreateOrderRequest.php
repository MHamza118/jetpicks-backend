<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origin_country' => 'required|string|max:100',
            'origin_city' => 'required|string|max:100',
            'destination_country' => 'required|string|max:100',
            'destination_city' => 'required|string|max:100',
            'special_notes' => 'sometimes|string|max:1000',
            'picker_id' => 'sometimes|string|exists:users,id',
            'status' => 'sometimes|in:DRAFT,PENDING',
        ];
    }

    public function messages(): array
    {
        return [
            'origin_country.required' => 'Origin country is required',
            'origin_city.required' => 'Origin city is required',
            'destination_country.required' => 'Destination country is required',
            'destination_city.required' => 'Destination city is required',
        ];
    }
}
