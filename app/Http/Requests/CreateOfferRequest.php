<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOfferRequest extends FormRequest
{
    //check authorization
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|uuid|exists:orders,id',
            'offer_amount' => 'required|numeric|min:0.01|max:999999.99',
            'parent_offer_id' => 'nullable|uuid|exists:offers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required',
            'order_id.uuid' => 'Order ID must be a valid UUID',
            'order_id.exists' => 'Order not found',
            'offer_amount.required' => 'Offer amount is required',
            'offer_amount.numeric' => 'Offer amount must be a number',
            'offer_amount.min' => 'Offer amount must be at least 0.01',
            'offer_amount.max' => 'Offer amount cannot exceed 999999.99',
            'parent_offer_id.uuid' => 'Parent offer ID must be a valid UUID',
            'parent_offer_id.exists' => 'Parent offer not found',
        ];
    }
}
